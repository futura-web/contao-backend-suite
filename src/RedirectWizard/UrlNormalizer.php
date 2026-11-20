<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\RedirectWizard;

/**
 * Zerlegt und normalisiert die URLs des Altprojekts.
 *
 * Zwei verschiedene Normalisierungen, die bewusst nicht vermischt werden:
 *
 * - parse() liefert den rohen Pfad, genau so wie er in der alten URL stand
 *   (inklusive Groß-/Kleinschreibung). Daraus wird spaeter der Alias der
 *   Weiterleitungsseite gebaut, und tl_page.alias ist eine BINARY-Spalte,
 *   d.h. Contao findet die Route nur bei exakt gleicher Schreibweise.
 *   Hier darf also nichts "verschoenert" werden.
 *
 * - comparable()/tokens() liefern eine aggressiv vereinfachte Form, die nur
 *   fuer den Ähnlichkeitsvergleich in TargetMatcher benutzt wird und nie in
 *   die Datenbank wandert.
 */
final class UrlNormalizer
{
    /**
     * Dateiendungen, die nie eine inhaltliche Seite waren. Greift vor allem
     * bei Wayback-Importen, deren MIME-Filter nicht immer sauber ist.
     */
    private const ASSET_EXTENSIONS = [
        'css', 'js', 'map', 'json', 'xml', 'txt', 'ico', 'webmanifest',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'tif', 'tiff',
        'pdf', 'zip', 'gz', 'tgz', 'rar', '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'mp3', 'mp4', 'webm', 'ogg', 'ogv', 'avi', 'mov', 'wmv', 'woff', 'woff2', 'ttf', 'eot',
    ];

    /**
     * Dateiendungen von Seiten des Altsystems. Gebraucht nur, um bei einer
     * Zeile ohne Schema und ohne fuehrenden Schraegstrich zu entscheiden,
     * ob das erste Segment ein Hostname ist ("alt.de/shop/x") oder bereits
     * zum Pfad gehoert ("alte-seite.html").
     */
    private const PAGE_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'shtml', 'shtm', 'phtml',
        'php', 'php3', 'php4', 'php5', 'php7', 'php8',
        'asp', 'aspx', 'jsp', 'cfm', 'cgi', 'pl', 'do', 'action',
    ];

    private const UMLAUTS = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    /**
     * Akzeptiert sowohl vollstaendige URLs ("https://alt.de/shop/x.html") als
     * auch reine Pfade ("/shop/x.html" oder "shop/x.html").
     *
     * @return array{path: string, query: string}|null null, wenn die Zeile
     *                                                 gar keinen Pfad enthaelt
     */
    public function parse(string $raw): array|null
    {
        $raw = trim($raw);

        if ('' === $raw || str_starts_with($raw, '#')) {
            return null;
        }

        // Ohne Schema interpretiert parse_url() "alt.de/x" als Pfad, nicht
        // als Host - deshalb erst normalisieren, dann parsen.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
            $parts = parse_url($raw);
        } elseif (str_starts_with($raw, '/') || !$this->startsWithHost($raw)) {
            $parts = parse_url('http://localhost/'.ltrim($raw, '/'));
        } else {
            $parts = parse_url('http://'.$raw);
        }

        if (false === $parts || null === $parts) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        // Prozent-Kodierung aufloesen, damit "/ueber-uns%2Dwir" und Umlaute
        // in der Form "%C3%BC" wie der Klartext behandelt werden.
        $decoded = rawurldecode($path);

        if ('' !== $decoded) {
            $path = $decoded;
        }

        if (!str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return [
            'path' => $path,
            'query' => $parts['query'] ?? '',
        ];
    }

    /**
     * Zerlegt eine mehrzeilige Eingabe (eine URL pro Zeile) in eindeutige,
     * geparste Pfade. Reihenfolge bleibt erhalten.
     *
     * @return array<string, array{path: string, query: string}> Pfad+Query als Schluessel
     */
    public function parseList(string $input): array
    {
        $result = [];

        foreach (preg_split('/\r\n|\r|\n/', $input) ?: [] as $line) {
            $parsed = $this->parse($line);

            if (null === $parsed) {
                continue;
            }

            $key = $parsed['path'].('' !== $parsed['query'] ? '?'.$parsed['query'] : '');
            $result[$key] ??= $parsed;
        }

        return $result;
    }

    /**
     * Beginnt die Zeile mit einem Hostnamen statt mit dem Pfad?
     *
     * Ohne diese Unterscheidung wuerde parse_url() aus "shop/x.html" den
     * Host "shop" und den Pfad "/x.html" machen - die Zeile verliert
     * stillschweigend ihr erstes Segment und die Weiterleitung entstuende
     * fuer die falsche Adresse.
     */
    private function startsWithHost(string $raw): bool
    {
        $first = explode('/', $raw, 2)[0];

        if (!str_contains($first, '.')) {
            return false;
        }

        if (1 !== preg_match('/\.([a-z]{2,24})$/i', $first, $matches)) {
            return false;
        }

        $extension = strtolower($matches[1]);

        return !\in_array($extension, self::PAGE_EXTENSIONS, true)
            && !\in_array($extension, self::ASSET_EXTENSIONS, true);
    }

    public function isAssetPath(string $path): bool
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return '' !== $extension && \in_array($extension, self::ASSET_EXTENSIONS, true);
    }

    /**
     * Letztes, inhaltlich tragendes Pfadsegment: aus "/shop/stuehle/x.html"
     * wird "x". Leere Segmente und ein evtl. vorhandenes "index" werden
     * uebersprungen, damit "/shop/index.html" als "shop" vergleichbar wird.
     */
    public function lastSegment(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn ($s) => '' !== $s));

        while ([] !== $segments) {
            $segment = array_pop($segments);
            $withoutExtension = preg_replace('/\.[a-z0-9]{1,6}$/i', '', $segment) ?? $segment;

            if ('' !== $withoutExtension && !\in_array(strtolower($withoutExtension), ['index', 'default', 'home'], true)) {
                return $withoutExtension;
            }
        }

        return '';
    }

    /**
     * Vergleichsform: klein, ohne Umlaute, ohne Trenn- und Sonderzeichen,
     * Woerter durch einzelne Leerzeichen getrennt. Zahlen bleiben erhalten,
     * weil sie in Altsystem-URLs ("/9-html5/1-das-nackte-dokument") oft die
     * einzige Unterscheidung sind.
     */
    public function comparable(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, self::UMLAUTS);

        // Dateiendung am Ende entfernen, sie sagt nichts ueber den Inhalt.
        $text = preg_replace('/\.[a-z0-9]{1,6}$/', '', $text) ?? $text;

        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $comparable = $this->comparable($text);

        if ('' === $comparable) {
            return [];
        }

        return array_values(array_unique(explode(' ', $comparable)));
    }
}
