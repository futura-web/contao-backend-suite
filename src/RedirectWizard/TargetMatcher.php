<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\RedirectWizard;

use Doctrine\DBAL\Connection;

/**
 * Schlaegt zu einer alten URL die passende neue Seite vor.
 *
 * Bewusst nur ein Vorschlag: der Importer setzt ein Ziel nie ohne
 * Rueckfrage um, sondern markiert alles unterhalb von THRESHOLD_AUTO als
 * "pruefen". Eine falsche 301-Weiterleitung ist schaedlicher als ein 404,
 * weil sie niemandem auffaellt - weder dem Redakteur noch der Suchmaschine,
 * die den Zielinhalt dann als irrelevant fuer die alte URL bewertet.
 */
final class TargetMatcher
{
    /** Ab diesem Score gilt der Treffer als sicher genug zum Uebernehmen. */
    public const THRESHOLD_AUTO = 90;

    /** Darunter wird kein Ziel mehr vorgeschlagen. */
    public const THRESHOLD_SUGGEST = 55;

    /**
     * similar_text() ist im schlechtesten Fall kubisch - bei
     * Seitentiteln/Aliassen nie ein Problem, mit dieser Kappung aber auch
     * bei absurd langen Altsystem-URLs nicht.
     */
    private const MAX_COMPARE_LENGTH = 120;

    /** Unter dieser Laenge zaehlt nur noch exakte Gleichheit. */
    private const MIN_FUZZY_LENGTH = 3;

    public function __construct(
        private readonly Connection $connection,
        private readonly UrlNormalizer $normalizer,
    ) {
    }

    /**
     * Alle Seiten unterhalb eines Startpunkts, unabhaengig vom Seitentyp.
     *
     * Absichtlich iterativ Ebene fuer Ebene statt per rekursivem CTE:
     * Contao 5 laeuft noch auf MySQL 5.7, das "WITH RECURSIVE" nicht kennt.
     * tl_page hat ausserdem keine rootId-Spalte - die berechnet Contao erst
     * in PageModel::loadDetails().
     *
     * @return list<array{id: int, alias: string, title: string, pageTitle: string, type: string}>
     */
    public function loadPages(int $rootId): array
    {
        $pages = [];
        $level = [$rootId];

        // Harte Obergrenze gegen Zyklen in kaputten Baeumen.
        for ($depth = 0; $depth < 32 && [] !== $level; ++$depth) {
            // Platzhalter manuell statt per ArrayParameterType: die Werte
            // sind bereits auf int gecastet, und so bleibt der Aufruf
            // unabhaengig von den Unterschieden zwischen DBAL 3 und 4.
            $placeholders = implode(',', array_fill(0, \count($level), '?'));

            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, alias, title, pageTitle, type FROM tl_page WHERE pid IN ({$placeholders}) AND type != 'root'",
                $level,
            );

            $level = [];

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $level[] = $id;

                $pages[] = [
                    'id' => $id,
                    'alias' => (string) $row['alias'],
                    'title' => (string) $row['title'],
                    'pageTitle' => (string) $row['pageTitle'],
                    'type' => (string) $row['type'],
                ];
            }
        }

        return $pages;
    }

    /**
     * Nur die Seiten, die als Weiterleitungsziel sinnvoll sind. Fehler- und
     * Logout-Seiten sowie bereits bestehende Weiterleitungsseiten fallen
     * damit aus den Vorschlaegen heraus.
     *
     * @param list<array{id: int, alias: string, title: string, pageTitle: string, type: string}> $pages
     *
     * @return list<array{id: int, alias: string, title: string, pageTitle: string, type: string}>
     */
    public function filterTargets(array $pages): array
    {
        return array_values(array_filter(
            $pages,
            static fn (array $page) => \in_array($page['type'], ['regular', 'forward'], true),
        ));
    }

    /**
     * @param list<array{id: int, alias: string, title: string, pageTitle: string, type: string}> $candidates
     *
     * @return array{pageId: int, score: int}
     */
    public function match(string $oldPath, array $candidates): array
    {
        $oldFull = $this->normalizer->comparable(str_replace('/', ' ', $oldPath));
        $oldLast = $this->normalizer->comparable($this->normalizer->lastSegment($oldPath));

        $bestId = 0;
        $bestScore = 0;
        $bestType = '';

        foreach ($candidates as $candidate) {
            $alias = $candidate['alias'];
            $candFull = $this->normalizer->comparable(str_replace('/', ' ', $alias));
            $candLast = $this->normalizer->comparable($this->normalizer->lastSegment('/'.$alias));

            $score = max(
                $this->similarity($oldLast, $candLast),
                $this->similarity($oldFull, $candFull),
                $this->similarity($oldLast, $this->normalizer->comparable($candidate['title'])),
                $this->similarity($oldLast, $this->normalizer->comparable($candidate['pageTitle'])),
                $this->similarity($oldFull, $this->normalizer->comparable($candidate['title'])),
            );

            if (0 === $score) {
                continue;
            }

            // Bei Gleichstand gewinnt die echte Inhaltsseite vor der
            // Weiterleitungs-/Verweisseite.
            $better = $score > $bestScore
                || ($score === $bestScore && 'regular' === $candidate['type'] && 'regular' !== $bestType);

            if ($better) {
                $bestScore = $score;
                $bestId = $candidate['id'];
                $bestType = $candidate['type'];
            }
        }

        if ($bestScore < self::THRESHOLD_SUGGEST) {
            return ['pageId' => 0, 'score' => $bestScore];
        }

        return ['pageId' => $bestId, 'score' => $bestScore];
    }

    /**
     * 0-100. Kombiniert Zeichen-Ähnlichkeit (similar_text) mit
     * Wort-Überschneidung (Jaccard): Erstere erkennt Tippfehler und
     * Endungen, Letztere erkennt umsortierte oder gekuerzte Titel
     * ("kontakt-impressum" vs. "impressum-kontakt").
     */
    private function similarity(string $a, string $b): int
    {
        if ('' === $a || '' === $b) {
            return 0;
        }

        if ($a === $b) {
            return 100;
        }

        if (\strlen($a) < self::MIN_FUZZY_LENGTH || \strlen($b) < self::MIN_FUZZY_LENGTH) {
            return 0;
        }

        $a = substr($a, 0, self::MAX_COMPARE_LENGTH);
        $b = substr($b, 0, self::MAX_COMPARE_LENGTH);

        $percent = 0.0;
        similar_text($a, $b, $percent);

        return (int) round(max($percent, $this->jaccard($a, $b) * 100));
    }

    private function jaccard(string $a, string $b): float
    {
        $tokensA = $this->normalizer->tokens($a);
        $tokensB = $this->normalizer->tokens($b);

        if ([] === $tokensA || [] === $tokensB) {
            return 0.0;
        }

        $intersection = \count(array_intersect($tokensA, $tokensB));
        $union = \count(array_unique([...$tokensA, ...$tokensB]));

        return 0 === $union ? 0.0 : $intersection / $union;
    }
}
