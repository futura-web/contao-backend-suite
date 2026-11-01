<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTableRegistry;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTypeRegistry;

/**
 * Hängt alle aktiven, im Feld-Builder (tl_dynamic_field) für die gerade
 * geladene Tabelle angelegten Felder in deren DCA + Palette ein - der
 * eigentliche "Feld-Builder"-Mechanismus, generalisiert nach dem Muster
 * von LayoutJsFunctionFieldInjector (neues Feld live anlegen), nur
 * datengetrieben statt für eine feste Zieltabelle.
 */
#[AsHook('loadDataContainer')]
class DynamicFieldInjector
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DynamicFieldTableRegistry $tableRegistry,
        private readonly DynamicFieldTypeRegistry $typeRegistry,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ('tl_dynamic_field' === $table || !\in_array($table, $this->tableRegistry->getTables(), true)) {
            return;
        }

        try {
            // tl_dynamic_field existiert erst nach der allerersten
            // contao:migrate-Ausführung dieses Bundles - und genau dieser
            // Lauf laedt dabei selbst wieder alle anderen Tabellen-DCAs
            // (Schema-Diff), was diesen Hook erneut auslöst. Ohne dieses
            // try/catch wuerde contao:migrate (und jeder andere
            // Erstaufruf vor der Migration) an der fehlenden Tabelle
            // scheitern.
            $rows = $this->connection->fetchAllAssociative(
                "SELECT * FROM tl_dynamic_field WHERE targetTable = ? AND active = '1' AND fieldName != ''",
                [$table],
            );
        } catch (DbalException) {
            return;
        }

        if ([] === $rows) {
            return;
        }

        // Nie ein Feld einhängen, dessen Spalte tatsächlich (noch) nicht
        // existiert - z.B. weil das automatische ALTER TABLE beim
        // Anlegen fehlgeschlagen ist (siehe DynamicFieldSchemaSync). Ein
        // DCA-Feld ohne echte Spalte würde beim nächsten Speichern EINES
        // BELIEBIGEN Datensatzes dieser Tabelle mit "Unknown column"
        // abstürzen - live gegen das echte Schema prüfen ist hier
        // wichtiger als die eine zusätzliche Abfrage zu sparen.
        try {
            $existingColumns = array_map(
                static fn ($column): string => strtolower($column->getName()),
                $this->connection->createSchemaManager()->listTableColumns($table),
            );
        } catch (DbalException) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA'][$table];

        foreach ($rows as $row) {
            $fieldName = (string) $row['fieldName'];

            if (isset($dca['fields'][$fieldName]) || !\in_array(strtolower($fieldName), $existingColumns, true)) {
                continue;
            }

            $dca['fields'][$fieldName] = $this->typeRegistry->buildDcaField($row) + [
                'sql' => $this->typeRegistry->sqlFor((string) $row['fieldType']),
            ];

            $this->injectIntoPalette($table, $dca, $row, $fieldName);
        }
    }

    /**
     * @param array<string, mixed> $dca
     * @param array<string, mixed> $row
     */
    private function injectIntoPalette(string $table, array &$dca, array $row, string $fieldName): void
    {
        $legend = (string) $row['legend'];
        $palette = $dca['palettes']['default'] ?? '';
        $palette = \is_string($palette) ? $palette : '';

        if ('' !== $legend && DynamicFieldTableRegistry::NEW_LEGEND_OPTION !== $legend && str_contains($palette, '{'.$legend)) {
            PaletteManipulator::create()
                ->addField([$fieldName], $legend, PaletteManipulator::POSITION_APPEND)
                ->applyToPalette('default', $table)
            ;

            return;
        }

        // Kein gültiger Legend-Anker (bewusst "neuer Abschnitt" gewählt,
        // oder die gespeicherte Legend existiert in dieser Tabelle nicht
        // mehr) - neuen Abschnitt direkt ans Palette-Ende anhängen,
        // statt PaletteManipulator::addLegend() einen unsicheren
        // Fallback-Anker raten zu lassen. Der Abschnitts-Key wird aus dem
        // Titel abgeleitet, damit Felder mit UNTERSCHIEDLICHEM Titel auch
        // in unterschiedlichen Abschnitten landen (sonst würden alle
        // "neuer Abschnitt"-Felder einer Tabelle denselben einen Titel
        // teilen - der zuletzt verarbeitete hätte gewonnen).
        $legendKey = $this->legendKeyForTitle((string) $row['newLegendTitle']);
        $GLOBALS['TL_LANG'][$table][$legendKey] = '' !== (string) $row['newLegendTitle'] ? (string) $row['newLegendTitle'] : 'Weitere Felder';

        if (!str_contains($dca['palettes']['default'], '{'.$legendKey)) {
            $dca['palettes']['default'] = rtrim($dca['palettes']['default'], ';').';{'.$legendKey.'},'.$fieldName;

            return;
        }

        PaletteManipulator::create()
            ->addField([$fieldName], $legendKey, PaletteManipulator::POSITION_APPEND)
            ->applyToPalette('default', $table)
        ;
    }

    private function legendKeyForTitle(string $title): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $title), '_'));

        return '' !== $slug ? 'dyn_'.$slug.'_legend' : 'dyn_field_legend';
    }
}
