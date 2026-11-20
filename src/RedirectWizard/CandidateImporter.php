<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\RedirectWizard;

use Doctrine\DBAL\Connection;

/**
 * Baut aus einer Quelle (eingefuegte URL-Liste oder Wayback-Abruf) die
 * Vorschlagsliste in tl_bs_redirect.
 *
 * Erzeugt ausdruecklich noch keine Seiten im Seitenbaum - das macht erst
 * RedirectPageCreator, nachdem der Redakteur die Vorschlaege durchgesehen
 * hat.
 */
final class CandidateImporter
{
    public const STATE_READY = 'ready';
    public const STATE_REVIEW = 'review';
    public const STATE_NOTARGET = 'notarget';
    public const STATE_EXISTS = 'exists';
    public const STATE_CREATED = 'created';

    /** Alle Zustaende, aus denen keine Seite entstehen kann. */
    public const BLOCKED_STATES = [
        'skip_query' => AliasBuilder::PROBLEM_QUERY,
        'skip_prefix' => AliasBuilder::PROBLEM_PREFIX,
        'skip_suffix' => AliasBuilder::PROBLEM_SUFFIX,
        'skip_invalid' => AliasBuilder::PROBLEM_INVALID,
        'skip_root' => AliasBuilder::PROBLEM_ROOT,
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly UrlNormalizer $normalizer,
        private readonly AliasBuilder $aliasBuilder,
        private readonly TargetMatcher $matcher,
        private readonly WaybackClient $wayback,
    ) {
    }

    /**
     * @return array{total: int, ready: int, review: int, notarget: int, exists: int, blocked: int, skippedAssets: int}
     *
     * @throws WaybackException
     * @throws \RuntimeException wenn der Startpunkt fehlt oder kein Startpunkt ist
     */
    public function import(int $importId): array
    {
        $import = $this->connection->fetchAssociative(
            'SELECT * FROM tl_bs_redirect_import WHERE id = ?',
            [$importId],
        );

        if (!$import) {
            throw new \RuntimeException('Import-Datensatz nicht gefunden.');
        }

        $rootId = (int) ($import['rootPage'] ?? 0);

        $root = $this->connection->fetchAssociative(
            'SELECT id, type, urlPrefix, urlSuffix FROM tl_page WHERE id = ?',
            [$rootId],
        );

        if (!$root || 'root' !== $root['type']) {
            throw new \RuntimeException('Es ist kein gültiger Startpunkt ausgewählt.');
        }

        if ('wayback' === ($import['sourceType'] ?? '')) {
            $raw = implode("\n", $this->wayback->fetchUrls(
                (string) ($import['waybackDomain'] ?? ''),
                (int) ($import['waybackLimit'] ?? 1000),
            ));
        } else {
            $raw = (string) ($import['urlList'] ?? '');
        }

        $parsed = $this->normalizer->parseList($raw);

        // Ein Baumdurchlauf, zwei Auswertungen: die Vorschlagsziele
        // (nur Inhaltsseiten) und die bereits belegten Aliasse (alle
        // Seitentypen, inklusive der Weiterleitungsseiten aus einem
        // frueheren Lauf - sonst entstehen doppelte Aliasse).
        $pages = $this->matcher->loadPages($rootId);
        $candidates = $this->matcher->filterTargets($pages);
        $existingAliases = $this->collectAliases($pages);

        // Ein erneuter Lauf darf keine Entscheidung ueberschreiben, die ein
        // Mensch getroffen hat: verworfen werden nur Zeilen ohne Ziel, also
        // die ohne Treffer und die nicht abbildbaren. Die werden neu
        // berechnet, was nach einer Korrektur am Seitenbaum oder an der
        // URL-Endung des Startpunkts auch noetig ist. Alles mit gesetztem
        // Ziel - vom Treffer bis zur handverlesenen Zuordnung - und alles
        // bereits Angelegte bleibt unberuehrt.
        $this->connection->executeStatement(
            'DELETE FROM tl_bs_redirect WHERE pid = ? AND targetPage = 0 AND state != ?',
            [$importId, self::STATE_CREATED],
        );

        $alreadyLogged = $this->connection->fetchFirstColumn(
            'SELECT source FROM tl_bs_redirect WHERE pid = ?',
            [$importId],
        );
        $alreadyLogged = array_flip(array_map('strval', $alreadyLogged));

        $stats = [
            'total' => 0,
            'ready' => 0,
            'review' => 0,
            'notarget' => 0,
            'exists' => 0,
            'blocked' => 0,
            'skippedAssets' => 0,
        ];

        $now = time();
        $sorting = 0;

        foreach ($parsed as $source => $info) {
            if ($this->normalizer->isAssetPath($info['path'])) {
                ++$stats['skippedAssets'];
                continue;
            }

            if (isset($alreadyLogged[$source])) {
                continue;
            }

            $built = $this->aliasBuilder->build(
                $info['path'],
                $info['query'],
                (string) $root['urlPrefix'],
                (string) $root['urlSuffix'],
            );

            $alias = $built['alias'];
            $targetPage = 0;
            $score = 0;

            if (null !== $built['problem']) {
                $state = array_search($built['problem'], self::BLOCKED_STATES, true) ?: 'skip_invalid';
                ++$stats['blocked'];
            } elseif (isset($existingAliases[$alias])) {
                // Unter dieser Adresse antwortet im neuen Baum schon eine
                // Seite - eine zweite mit gleichem Alias wuerde Contao als
                // Routenkonflikt melden.
                $state = self::STATE_EXISTS;
                $targetPage = $existingAliases[$alias];
                ++$stats['exists'];
            } else {
                $match = $this->matcher->match($info['path'], $candidates);
                $targetPage = $match['pageId'];
                $score = $match['score'];

                if (0 === $targetPage) {
                    $state = self::STATE_NOTARGET;
                    ++$stats['notarget'];
                } elseif ($score >= TargetMatcher::THRESHOLD_AUTO) {
                    $state = self::STATE_READY;
                    ++$stats['ready'];
                } else {
                    $state = self::STATE_REVIEW;
                    ++$stats['review'];
                }
            }

            $this->connection->insert('tl_bs_redirect', [
                'pid' => $importId,
                'tstamp' => $now,
                'sorting' => ($sorting += 128),
                'source' => $source,
                'alias' => $alias,
                'targetPage' => $targetPage,
                'score' => $score,
                'state' => $state,
                'createdPage' => 0,
            ]);

            ++$stats['total'];
        }

        return $stats;
    }

    /**
     * Alias => Seiten-ID aller bestehenden Seiten dieses Startpunkts.
     *
     * @param list<array{id: int, alias: string, title: string, pageTitle: string, type: string}> $pages
     *
     * @return array<string, int>
     */
    private function collectAliases(array $pages): array
    {
        $aliases = [];

        foreach ($pages as $page) {
            if ('' !== $page['alias']) {
                $aliases[$page['alias']] ??= $page['id'];
            }
        }

        return $aliases;
    }
}
