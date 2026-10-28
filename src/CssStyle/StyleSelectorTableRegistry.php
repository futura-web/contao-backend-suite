<?php

declare(strict_types=1);

namespace Futura\ContaoBackendSuite\CssStyle;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinder;

/**
 * Finds every DCA table that has a `cssID` or `cssClass` field, so the
 * style selector can be offered there automatically — no per-table wiring.
 *
 * Note: the scan force-loads every candidate table's DCA via
 * Controller::loadDataContainer(), which Contao caches per request. In the
 * rare case a request genuinely needs more than one of these tables (this
 * scan aside), the second one won't re-fire the `loadDataContainer` hook,
 * so its style-selector field would be missing for that request only.
 */
class StyleSelectorTableRegistry
{
    private const CLASS_FIELDS = ['cssID', 'cssClass'];

    /** @var array<string, string>|null */
    private array|null $tables = null;

    /**
     * Guards against re-entrancy: loading a candidate table's DCA fires the
     * `loadDataContainer` hook again, which asks this registry for an
     * answer before the initial scan has finished.
     */
    private bool $scanning = false;

    public function __construct(private readonly ResourceFinder $resourceFinder)
    {
    }

    /**
     * @return array<string, string> table name => the field holding the classes ('cssID' or 'cssClass')
     */
    public function getTables(): array
    {
        if (null !== $this->tables) {
            return $this->tables;
        }

        if ($this->scanning) {
            return [];
        }

        $this->scanning = true;
        $tables = [];

        foreach ($this->findCandidateTableNames() as $table) {
            Controller::loadDataContainer($table);

            $field = $this->getClassField($table);

            if (null !== $field) {
                $tables[$table] = $field;
            }
        }

        $this->scanning = false;
        $this->tables = $tables;

        return $this->tables;
    }

    public function getClassFieldForTable(string $table): string|null
    {
        return $this->getTables()[$table] ?? null;
    }

    /**
     * @return list<string>
     */
    private function findCandidateTableNames(): array
    {
        $tables = [];

        foreach ($this->resourceFinder->findIn('dca')->files()->name('*.php') as $file) {
            $tables[] = $file->getBasename('.php');
        }

        return array_unique($tables);
    }

    private function getClassField(string $table): string|null
    {
        $fields = $GLOBALS['TL_DCA'][$table]['fields'] ?? [];

        foreach (self::CLASS_FIELDS as $field) {
            if (\array_key_exists($field, $fields)) {
                return $field;
            }
        }

        return null;
    }
}
