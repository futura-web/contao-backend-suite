<?php

declare(strict_types=1);

namespace Futura\ContaoBackendSuite\CssStyle;

use Doctrine\DBAL\Connection;

/**
 * Merges the classes of the selected style-selector rows into an existing
 * `cssID`/`cssClass` value, without touching classes the editor added by hand.
 */
class StyleMerger
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<int> $selectedIds
     */
    public function mergeClasses(string $existingClasses, array $selectedIds): string
    {
        $current = $this->splitClasses($existingClasses);
        $current = array_diff($current, $this->getAllManagedClasses());

        $selected = $this->getClassesForIds($selectedIds);

        return implode(' ', array_unique([...$current, ...$selected]));
    }

    /**
     * @return list<string>
     */
    private function splitClasses(string $classes): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($classes)) ?: []));
    }

    /**
     * @return list<string>
     */
    private function getAllManagedClasses(): array
    {
        $rows = $this->connection->fetchFirstColumn('SELECT cssClass FROM tl_style_selector');

        $classes = [];

        foreach ($rows as $row) {
            $classes = [...$classes, ...$this->splitClasses((string) $row)];
        }

        return array_unique($classes);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<string>
     */
    private function getClassesForIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($ids), '?'));

        $rows = $this->connection->fetchFirstColumn(
            "SELECT cssClass FROM tl_style_selector WHERE id IN ({$placeholders})",
            array_values($ids),
        );

        $classes = [];

        foreach ($rows as $row) {
            $classes = [...$classes, ...$this->splitClasses((string) $row)];
        }

        return array_unique($classes);
    }
}
