<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Routing;

use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\CoreBundle\Routing\Page\DynamicRouteInterface;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * Seitentyp fuer Leseseiten von Nachrichtenarchiven, deren Beitraege direkt
 * unter /<artikel-alias> liegen sollen statt unter
 * /<seitenalias>/<artikel-alias>.
 *
 * Der Kern steckt in configurePageRoute(): die Route /{alias} bekommt als
 * Anforderung nicht etwa ein allgemeines Slug-Muster, sondern exakt die
 * Aliasse der Artikel, deren Archiv auf diese Seite zeigt. Dadurch greift
 * die Route ausschliesslich bei echten Beitraegen und verschluckt keine
 * anderen einsegmentigen Pfade - es braucht weder ein Skript vor dem
 * Front-Controller noch eine Existenzpruefung zur Laufzeit.
 *
 * Mehrere Seiten dieses Typs teilen sich die Route /{alias}; jede nimmt nur
 * die Aliasse der Archive an, deren jumpTo sie ist. Die routePriority sollte
 * unter der normaler Seiten liegen, damit eine gleichnamige echte Seite
 * gewinnt.
 */
#[AsPage(type: self::TYPE, path: '/{alias}', requirements: ['alias' => '[a-z0-9][a-z0-9-]*'])]
class FlatNewsPage implements DynamicRouteInterface
{
    public const TYPE = 'bs_news_flat';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function configurePageRoute(PageRoute $route): void
    {
        $aliases = $this->fetchAliases((int) $route->getPageModel()->id);

        // Ohne Beitraege darf die Route nie greifen: "(?!)" ist ein
        // Negativ-Lookahead auf die leere Zeichenkette und trifft daher nichts.
        $pattern = $aliases
            ? implode('|', array_map(static fn (string $a): string => preg_quote($a, '#'), $aliases))
            : '(?!)';

        $route->setRequirement('alias', '(?:'.$pattern.')');
    }

    public function getUrlSuffixes(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    private function fetchAliases(int $pageId): array
    {
        try {
            $aliases = $this->connection->fetchFirstColumn(
                'SELECT n.alias FROM tl_news n INNER JOIN tl_news_archive a ON a.id = n.pid WHERE a.jumpTo = ? AND n.alias != ?',
                [$pageId, ''],
            );
        } catch (DbalException) {
            // Tabellen fehlen (news-bundle nicht installiert, Migration noch
            // nicht gelaufen): Route bleibt wirkungslos statt den Request
            // abzubrechen.
            return [];
        }

        return array_values(array_map(static fn (mixed $a): string => (string) $a, $aliases));
    }
}
