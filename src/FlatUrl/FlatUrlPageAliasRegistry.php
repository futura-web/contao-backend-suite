<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\FlatUrl;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * Looks up every `tl_page` alias flagged via "backendSuiteFlatUrl" - i.e.
 * every reader page (News, Calendar, FAQ, ...) whose own alias should be
 * stripped from the URLs of whatever is read through it.
 */
class FlatUrlPageAliasRegistry
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<string>
     */
    public function getFlaggedAliases(): array
    {
        try {
            $aliases = $this->connection->fetchFirstColumn(
                "SELECT alias FROM tl_page WHERE backendSuiteFlatUrl = '1'",
            );
        } catch (DbalException) {
            // Spalte/Tabelle fehlt noch (Bundle frisch aktualisiert, aber
            // "contao:migrate" noch nicht gelaufen) - Feature bleibt bis
            // dahin einfach inaktiv, statt den Request abzubrechen.
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $alias): string => (string) $alias, $aliases),
            static fn (string $alias): bool => '' !== $alias,
        ));
    }
}
