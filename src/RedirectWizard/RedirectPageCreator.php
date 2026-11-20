<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\RedirectWizard;

use Doctrine\DBAL\Connection;

/**
 * Legt die eigentlichen Weiterleitungsseiten im Seitenbaum an.
 *
 * Warum dieser Weg ueberhaupt funktioniert, ohne .htaccess und ohne
 * robots.txt - drei im Contao-Core nachgelesene Eigenschaften:
 *
 * 1. Statuscode: Contao\CoreBundle\Controller\Page\RedirectPageController
 *    liefert 301 (HTTP_MOVED_PERMANENTLY), sofern tl_page.redirect nicht
 *    auf "temporary" steht. Der Spaltendefault ist "permanent".
 *
 * 2. Sitemap: derselbe Controller ist mit #[AsPage(contentComposition:
 *    false)] registriert, und SitemapController nimmt eine Seite nur auf,
 *    wenn PageRegistry::supportsContentComposition() wahr ist.
 *    Weiterleitungsseiten erscheinen also nie in der sitemap.xml. Wir
 *    setzen "sitemap" und "robots" trotzdem defensiv mit, damit auch
 *    Drittanbieter-Sitemaps und Crawler sie ignorieren.
 *
 * 3. Ziel: PageResolver gibt fuer type=redirect den Inhalt von tl_page.url
 *    an StringResolver weiter, der ihn durch den InsertTagParser schickt.
 *    Deshalb wird hier "{{link_url::<id>}}" statt eines festen Pfads
 *    gespeichert - die Weiterleitung bleibt damit auch dann richtig, wenn
 *    sich der Alias der Zielseite spaeter aendert.
 *
 * "hide" ist Pflicht und kein Detail: ohne das Flag stehen alle
 * Weiterleitungsseiten in jedem Navigationsmodul.
 */
final class RedirectPageCreator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TargetMatcher $matcher,
    ) {
    }

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function createForImport(int $importId): array
    {
        $import = $this->connection->fetchAssociative(
            'SELECT id, rootPage, targetFolder FROM tl_bs_redirect_import WHERE id = ?',
            [$importId],
        );

        if (!$import) {
            throw new \RuntimeException('Import-Datensatz nicht gefunden.');
        }

        $folderId = (int) ($import['targetFolder'] ?? 0);

        $folder = $this->connection->fetchAssociative(
            'SELECT id FROM tl_page WHERE id = ?',
            [$folderId],
        );

        if (!$folder) {
            throw new \RuntimeException('Es ist keine gültige Zielseite für die Ablage ausgewählt.');
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, source, alias, targetPage FROM tl_bs_redirect
             WHERE pid = ? AND state IN (?, ?) AND alias != \'\' AND targetPage > 0
             ORDER BY sorting',
            [$importId, CandidateImporter::STATE_READY, CandidateImporter::STATE_REVIEW],
        );

        $result = ['created' => 0, 'skipped' => 0, 'errors' => []];

        if ([] === $rows) {
            return $result;
        }

        $sorting = (int) $this->connection->fetchOne(
            'SELECT MAX(sorting) FROM tl_page WHERE pid = ?',
            [$folderId],
        );

        // Alias-Eindeutigkeit gilt pro Startpunkt, nicht global: auf einer
        // Mehrdomain-Installation darf "kontakt" in jedem Baum einmal
        // vorkommen. Einmal einlesen und waehrend der Schleife mitpflegen,
        // damit auch zwei gleiche Aliasse innerhalb eines Laufs auffallen.
        $rootId = (int) ($import['rootPage'] ?? 0);
        $takenAliases = [];
        $treeIds = [$rootId => true];

        foreach ($this->matcher->loadPages($rootId) as $page) {
            $treeIds[$page['id']] = true;

            if ('' !== $page['alias']) {
                $takenAliases[$page['alias']] = true;
            }
        }

        // Die Ablage muss im Baum des gewaehlten Startpunkts liegen: der
        // Alias ist mit dessen urlPrefix und urlSuffix berechnet, unter
        // einem anderen Startpunkt wuerde die Seite daher unter einer
        // anderen Adresse antworten als der, die weitergeleitet werden
        // soll.
        if (!isset($treeIds[$folderId])) {
            throw new \RuntimeException(
                'Die gewählte Ablage liegt nicht im Seitenbaum des gewählten Startpunkts. '
                .'Da URL-Prefix und Endung am Startpunkt hängen, würden die Weiterleitungsseiten '
                .'dort unter anderen Adressen antworten.',
            );
        }

        $now = time();

        foreach ($rows as $row) {
            $alias = (string) $row['alias'];
            $targetPage = (int) $row['targetPage'];

            // Zwischen Import und Anlegen kann sich der Baum geaendert
            // haben, deshalb beide Pruefungen hier erneut.
            $targetExists = (bool) $this->connection->fetchOne(
                'SELECT id FROM tl_page WHERE id = ?',
                [$targetPage],
            );

            if (!$targetExists) {
                ++$result['skipped'];
                $result['errors'][] = \sprintf('%s: die Zielseite existiert nicht mehr.', $row['source']);
                continue;
            }

            if (isset($takenAliases[$alias])) {
                ++$result['skipped'];
                $this->connection->update(
                    'tl_bs_redirect',
                    ['state' => CandidateImporter::STATE_EXISTS, 'tstamp' => $now],
                    ['id' => (int) $row['id']],
                );
                $result['errors'][] = \sprintf('%s: der Alias "%s" ist inzwischen belegt.', $row['source'], $alias);
                continue;
            }

            $this->connection->insert('tl_page', [
                'pid' => $folderId,
                'sorting' => ($sorting += 128),
                'tstamp' => $now,
                'title' => $this->buildTitle((string) $row['source']),
                'alias' => $alias,
                'type' => 'redirect',
                'redirect' => 'permanent',
                'url' => \sprintf('{{link_url::%d}}', $targetPage),
                'target' => 0,
                'robots' => 'noindex,nofollow',
                'sitemap' => 'map_never',
                'hide' => 1,
                'published' => 1,
            ]);

            $pageId = (int) $this->connection->lastInsertId();
            $takenAliases[$alias] = true;

            $this->connection->update(
                'tl_bs_redirect',
                [
                    'state' => CandidateImporter::STATE_CREATED,
                    'createdPage' => $pageId,
                    'tstamp' => $now,
                ],
                ['id' => (int) $row['id']],
            );

            ++$result['created'];
        }

        return $result;
    }

    /**
     * Der Seitentitel ist im Baum das einzige, woran der Redakteur die
     * Weiterleitung wiedererkennt - deshalb die alte Adresse selbst, nicht
     * der Titel der Zielseite.
     */
    private function buildTitle(string $source): string
    {
        $title = '→ '.ltrim($source, '/');

        if (mb_strlen($title, 'UTF-8') > 255) {
            $title = mb_substr($title, 0, 254, 'UTF-8').'…';
        }

        return $title;
    }
}
