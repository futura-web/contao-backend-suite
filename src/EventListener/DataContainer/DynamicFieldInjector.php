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

            $paletteKey = $this->paletteKeyFor($table, $row);

            if (null === $paletteKey || !\is_string($dca['palettes'][$paletteKey] ?? null)) {
                // Inhaltselemente ohne (mehr) gültigen Typ, oder eine
                // Typ-Palette, die es in dieser Contao-Version gar nicht
                // (mehr) gibt - konservativ überspringen statt in eine
                // nicht existierende Palette zu schreiben.
                continue;
            }

            $dca['fields'][$fieldName] = $this->typeRegistry->buildDcaField($row) + [
                'sql' => $this->typeRegistry->sqlFor((string) $row['fieldType']),
            ];

            if (DynamicFieldTypeRegistry::SELECT === (string) $row['fieldType']) {
                $this->applyReferenceOptions($dca['fields'][$fieldName], (string) $row['optionsSource']);
            }

            $this->injectIntoPalette($table, $paletteKey, $dca, $row, $fieldName);
        }
    }

    /**
     * Für Select-Felder mit optionsSource "module"/"form"/"content":
     * ersetzt die (bei "frei" aus selectOptions geparsten) Optionen
     * durch die tatsächlich vorhandenen Datensätze der jeweiligen
     * Tabelle. Bleibt bei "frei" (oder einem unbekannten Wert)
     * unverändert.
     *
     * @param array<string, mixed> $field
     */
    private function applyReferenceOptions(array &$field, string $source): void
    {
        $options = match ($source) {
            DynamicFieldTypeRegistry::SOURCE_MODULE => $this->fetchOptions('tl_module', 'name', 100),
            DynamicFieldTypeRegistry::SOURCE_FORM => $this->fetchOptions('tl_form', 'title', 100),
            DynamicFieldTypeRegistry::SOURCE_CONTENT => $this->fetchContentElementOptions(),
            default => null,
        };

        if (null === $options) {
            return;
        }

        $field['options'] = array_keys($options);
        $field['reference'] = $options;
    }

    /**
     * @return array<string, string> id => Label
     */
    private function fetchOptions(string $table, string $labelColumn, int $limit): array
    {
        try {
            // $table/$labelColumn/$limit kommen ausschließlich aus
            // hartcodierten Aufrufen innerhalb dieser Klasse (nie aus
            // Nutzereingabe) - direkte Interpolation ist hier sicher und
            // umgeht PDOs manchmal eigenwilliges LIMIT-Parameter-Binding.
            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, {$labelColumn} AS label FROM {$table} ORDER BY {$labelColumn} ASC LIMIT {$limit}",
            );
        } catch (DbalException) {
            return [];
        }

        $options = [];

        foreach ($rows as $row) {
            $label = trim((string) $row['label']);
            $options[(string) $row['id']] = '' !== $label ? $label : '#'.$row['id'];
        }

        return $options;
    }

    /**
     * Inhaltselemente haben keinen einzelnen Titel - das Label wird aus
     * Typ + Überschrift/Text zusammengesetzt. Begrenzt auf die zuletzt
     * bearbeiteten, da es pro Website sehr viele geben kann (die
     * "chosen"-Auswahl im Formular bleibt trotzdem durchsuchbar).
     *
     * @return array<string, string> id => Label
     */
    private function fetchContentElementOptions(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, type, headline, text FROM tl_content ORDER BY tstamp DESC LIMIT 500',
            );
        } catch (DbalException) {
            return [];
        }

        $options = [];

        foreach ($rows as $row) {
            // "headline" liegt bei Inhaltselementen meist serialisiert
            // vor (['value' => ..., 'unit' => 'h2']), bei älteren
            // Datensätzen manchmal noch als reiner String - deserialize
            // mit $blnArray=true deckt beides sicher ab.
            $headlineData = \Contao\StringUtil::deserialize($row['headline'] ?? '', true);
            $headline = trim((string) ($headlineData['value'] ?? ''));
            $excerpt = '' !== $headline ? $headline : trim(strip_tags((string) ($row['text'] ?? '')));
            $excerpt = mb_substr($excerpt, 0, 60);

            $options[(string) $row['id']] = sprintf('[%s] %s (ID %d)', $row['type'], '' !== $excerpt ? $excerpt : '–', $row['id']);
        }

        return $options;
    }

    /**
     * Normalfall: die "default"-Palette der Zieltabelle. Inhaltselemente
     * haben stattdessen pro Typ eine eigene Palette (siehe
     * DynamicFieldTableRegistry-Klassenkommentar) - dort wird die
     * Palette des konkret gewählten Typs (tl_content.type-Wert)
     * verwendet.
     *
     * @param array<string, mixed> $row
     */
    private function paletteKeyFor(string $table, array $row): string|null
    {
        if (DynamicFieldTableRegistry::CONTENT_ELEMENT_TABLE !== $table) {
            return 'default';
        }

        $type = (string) ($row['contentElementType'] ?? '');

        return isset(DynamicFieldTableRegistry::CONTENT_ELEMENT_TYPES[$type]) ? $type : null;
    }

    /**
     * @param array<string, mixed> $dca
     * @param array<string, mixed> $row
     */
    private function injectIntoPalette(string $table, string $paletteKey, array &$dca, array $row, string $fieldName): void
    {
        $legend = (string) $row['legend'];
        $palette = $dca['palettes'][$paletteKey] ?? '';
        $palette = \is_string($palette) ? $palette : '';

        if ('' !== $legend && DynamicFieldTableRegistry::NEW_LEGEND_OPTION !== $legend && str_contains($palette, '{'.$legend)) {
            PaletteManipulator::create()
                ->addField([$fieldName], $legend, PaletteManipulator::POSITION_APPEND)
                ->applyToPalette($paletteKey, $table)
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

        if (!str_contains($palette, '{'.$legendKey)) {
            $dca['palettes'][$paletteKey] = rtrim($palette, ';').';{'.$legendKey.'},'.$fieldName;

            return;
        }

        PaletteManipulator::create()
            ->addField([$fieldName], $legendKey, PaletteManipulator::POSITION_APPEND)
            ->applyToPalette($paletteKey, $table)
        ;
    }

    private function legendKeyForTitle(string $title): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $title), '_'));

        return '' !== $slug ? 'dyn_'.$slug.'_legend' : 'dyn_field_legend';
    }
}
