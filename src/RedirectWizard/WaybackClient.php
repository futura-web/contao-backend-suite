<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\RedirectWizard;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Holt die Liste der je archivierten URLs einer Domain von der CDX-API des
 * Internet Archive.
 *
 * Warum nicht Google: es gibt keine API, die den Google-Index einer Domain
 * auflistet. Die Search-Console-API (searchanalytics.query) liefert nur
 * URLs mit Impressionen, laut Doku ausdruecklich nicht alle, sondern die
 * oberen, und setzt voraus, dass die ALTE Domain dort noch verifiziert ist -
 * nach einem Relaunch selten der Fall. Die URL-Inspection-API prueft nur
 * einzelne URLs und kann nicht auflisten. Das Abgreifen von
 * "site:"-Suchergebnissen verstoesst gegen die Nutzungsbedingungen und
 * bricht nach wenigen hundert Treffern ab.
 *
 * Die CDX-API braucht dagegen keinen Key, kennt die Domain auch dann noch,
 * wenn sie längst offline ist, und liefert Pfade inklusive der
 * Unterverzeichnisse des Altsystems.
 */
final class WaybackClient
{
    private const ENDPOINT = 'https://web.archive.org/cdx/search/cdx';

    public const MAX_LIMIT = 10000;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return list<string> Absolute URLs, dedupliziert durch collapse=urlkey
     *
     * @throws WaybackException bei Netz-, Timeout- oder Formatfehlern
     */
    public function fetchUrls(string $domain, int $limit): array
    {
        $domain = $this->normalizeDomain($domain);

        if ('' === $domain) {
            throw new WaybackException('Keine gültige Domain angegeben.');
        }

        $limit = max(1, min($limit, self::MAX_LIMIT));

        // Query-String von Hand: die CDX-API erwartet "filter" mehrfach,
        // Symfonys Query-Array-Serialisierung wuerde daraus filter[0]/filter[1]
        // machen, was der Dienst ignoriert.
        $query = implode('&', [
            'url='.rawurlencode($domain),
            'matchType=domain',
            'output=json',
            'fl=original',
            'collapse=urlkey',
            'filter=statuscode:200',
            'filter=mimetype:text/html',
            'limit='.$limit,
        ]);

        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT.'?'.$query, [
                'timeout' => 30,
                'max_duration' => 60,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $status = $response->getStatusCode();

            if (200 !== $status) {
                throw new WaybackException(\sprintf('Das Internet Archive antwortete mit HTTP-Status %d.', $status));
            }

            $body = $response->getContent();
        } catch (TransportException|HttpExceptionInterface $e) {
            throw new WaybackException('Das Internet Archive war nicht erreichbar: '.$e->getMessage(), 0, $e);
        }

        if ('' === trim($body)) {
            return [];
        }

        try {
            $rows = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WaybackException('Die Antwort des Internet Archive war kein gültiges JSON.', 0, $e);
        }

        if (!\is_array($rows)) {
            return [];
        }

        $urls = [];

        foreach ($rows as $index => $row) {
            // Die erste Zeile ist die Spaltenbeschriftung ("original").
            if (0 === $index || !\is_array($row) || !isset($row[0]) || !\is_string($row[0])) {
                continue;
            }

            $urls[] = $row[0];
        }

        return array_values(array_unique($urls));
    }

    /**
     * Akzeptiert "https://alt.de/", "www.alt.de" und "alt.de" gleichermassen.
     * matchType=domain deckt Subdomains mit ab, "www." muss also nicht
     * gesondert behandelt werden.
     */
    private function normalizeDomain(string $raw): string
    {
        $raw = trim($raw);

        if ('' === $raw) {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
            $raw = (string) parse_url($raw, PHP_URL_HOST);
        }

        $raw = strtolower(trim($raw, '/ '));
        $raw = preg_replace('#/.*$#', '', $raw) ?? $raw;

        if (1 !== preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $raw)) {
            return '';
        }

        return $raw;
    }
}
