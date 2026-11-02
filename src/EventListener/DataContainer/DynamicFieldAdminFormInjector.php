<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldSchemaSync;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTableRegistry;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTypeRegistry;

/**
 * Hängt die service-abhängigen Teile des Feld-Builder-eigenen Formulars
 * (tl_dynamic_field) ein: welche Zieltabellen/Abschnitte zur Auswahl
 * stehen (DynamicFieldTableRegistry), und das Anlegen der DB-Spalte nach
 * dem Speichern (DynamicFieldSchemaSync). Alles andere an diesem
 * Formular steht bereits statisch in contao/dca/tl_dynamic_field.php.
 */
#[AsHook('loadDataContainer')]
class DynamicFieldAdminFormInjector
{
    /**
     * Felder, die gesperrt werden, SOBALD die Zielspalte(n) echte Daten
     * enthalten - serverseitig durchgesetzt per save_callback, siehe
     * guardImmutable(). Bewusst NICHT schon ab dem allerersten
     * Speichern gesperrt (Stand bis 2026-11-02): ein Redakteur, der im
     * Baumodus z.B. versehentlich den falschen Feldtyp wählt, soll das
     * noch gefahrlos korrigieren können, solange niemand etwas in das
     * Feld eingetragen hat - erst wenn wirklich Daten drinstehen, würde
     * eine Änderung sie verwaisen lassen.
     *
     * Die "readonly"-Eval-Option allein reicht als Schutz NICHT: Contao
     * rendert sie bei inputType "select" zwar als HTML-Attribut, aber
     * "readonly" hat dort schlicht keine Wirkung (nur "disabled" würde
     * den Browser am Ändern hindern, das würde das Feld aber auch nicht
     * mehr mitsenden) - am 2026-11-02 live beobachtet: "fieldType" ließ
     * sich trotz readonly-Markierung ganz normal im Browser umstellen
     * und wurde anstandslos gespeichert.
     *
     * @var list<string>
     */
    private const LOCKED_ONCE_DATA_EXISTS = [
        'targetTable', 'fieldType', 'contentElementType', 'optionsSource', 'newLegendTitle', 'customFieldName',
    ];

    public function __construct(
        private readonly DynamicFieldTableRegistry $tableRegistry,
        private readonly DynamicFieldSchemaSync $schemaSync,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ('tl_dynamic_field' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_dynamic_field'];

        $dca['fields']['targetTable']['options_callback'] = function (): array {
            return $this->tableRegistry->getTableOptions();
        };

        $dca['fields']['legend']['options_callback'] = function (DataContainer $dc): array {
            $posted = Input::post('targetTable');
            $targetTables = \is_array($posted) ? $posted : null;

            if (null === $targetTables) {
                $record = $dc->activeRecord;
                $targetTables = \is_object($record) ? StringUtil::deserialize($record->targetTable ?? '', true) : [];
            }

            // Bei mehreren (oder keiner) ausgewählten Zieltabellen können
            // deren Abschnitte unterschiedlich heißen - dann bleibt nur
            // "neuer Abschnitt" sinnvoll anbietbar (dasselbe Muster wie
            // bei Inhaltselementen, die sowieso nie eine gemeinsame
            // Palette haben). Nur bei GENAU einer Tabelle lassen sich
            // deren echte Abschnitte sicher auflisten.
            return $this->tableRegistry->getLegendOptions(1 === \count($targetTables) ? (string) reset($targetTables) : null);
        };

        $dca['list']['label']['label_callback'] = function (array $row): string {
            return $this->formatListLabel($row);
        };

        $dca['fields']['active']['save_callback'][] = function (mixed $value, DataContainer $dc): mixed {
            return $this->schemaSync->guardDeactivation($value, $dc);
        };

        foreach (self::LOCKED_ONCE_DATA_EXISTS as $field) {
            $dca['fields'][$field]['save_callback'][] = function (mixed $value, DataContainer $dc) use ($field): mixed {
                return $this->guardImmutable($field, $value, $dc);
            };
        }

        $dca['config']['onload_callback'][] = function (DataContainer|null $dc = null): void {
            $this->lockWhenDataExists($dc);
        };

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->schemaSync->onSubmit($dc);
        };

        $dca['config']['ondelete_callback'][] = function (DataContainer $dc): void {
            $this->schemaSync->guardDeletion($dc);
        };

        $dca['config']['oncopy_callback'][] = function (int $insertId): void {
            $this->resetCopiedField($insertId);
        };
    }

    /**
     * Zieltabelle und Feldtyp dürfen NICHT mehr geändert werden, sobald
     * die Zielspalte(n) echte Daten enthalten - sonst würde das DCA auf
     * eine andere Tabelle/einen anderen Typ zeigen, während die
     * tatsächliche DB-Spalte (mit den schon erfassten Daten!) noch in
     * der alten Tabelle mit dem alten Typ liegt, was beim nächsten
     * Speichern eines Datensatzes dort zu einem SQL-Fehler ("Unknown
     * column") führen würde. Solange noch nichts eingetragen wurde,
     * bleibt alles frei korrigierbar (siehe LOCKED_ONCE_DATA_EXISTS).
     *
     * "Titel des neuen Abschnitts" aus demselben Grund gesperrt: Der
     * Titel bestimmt direkt den Namen des Palette-Abschnitts (siehe
     * DynamicFieldInjector::legendKeyForTitle()) - würde er nachträglich
     * geändert, würde das Feld beim nächsten Laden der Zieltabelle in
     * einen ANDEREN (neuen) Abschnitt wandern, statt im bestehenden zu
     * bleiben, was für Redakteure, die sich an die bisherige Stelle
     * gewöhnt haben, überraschend wäre.
     */
    private function lockWhenDataExists(DataContainer|null $dc): void
    {
        if (!$dc instanceof DataContainer) {
            return;
        }

        $record = $dc->activeRecord;

        if (!\is_object($record) || '' === (string) ($record->fieldName ?? '')) {
            return;
        }

        if (!$this->schemaSync->fieldHasDataAnywhere(
            StringUtil::deserialize($record->targetTable ?? '', true),
            (string) ($record->fieldName ?? ''),
            (string) ($record->fieldType ?? ''),
        )) {
            // Noch keine echten Daten in der Zielspalte - alles bleibt
            // änderbar. guardImmutable() ist der tatsächlich
            // durchsetzende Schutz, dieser readonly-Eval ist nur der
            // optische Hinweis dazu (und wirkt ohnehin nur bei
            // Text-Eingabefeldern wirklich, siehe Klassenkommentar oben).
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_dynamic_field'];
        $dca['fields']['targetTable']['eval']['readonly'] = true;
        $dca['fields']['fieldType']['eval']['readonly'] = true;
        $dca['fields']['newLegendTitle']['eval']['readonly'] = true;
        // Gleicher Grund wie bei targetTable: bestimmt, in welche Palette
        // (welcher Inhaltselement-Typ) injiziert wird - nachträglich
        // geändert würde das Feld aus der Form des bisherigen Typs
        // verschwinden, ohne dass jemand das erwartet.
        $dca['fields']['contentElementType']['eval']['readonly'] = true;
        // "optionsSource" bestimmt, wie gespeicherte Werte zu
        // interpretieren sind (freier Text vs. eine ID aus tl_module/
        // tl_form/tl_content) - ein nachträglicher Wechsel würde bereits
        // gespeicherte Werte falsch auslegen.
        $dca['fields']['optionsSource']['eval']['readonly'] = true;
        // "customFieldName" beeinflusst den technischen Namen nur, wenn
        // DynamicFieldSchemaSync::deriveFieldName() neu ableiten darf
        // (siehe dortiger Klassenkommentar) - sobald Daten existieren,
        // hätte eine Änderung ohnehin keinen Effekt mehr. Gesperrt, damit
        // das nicht den Eindruck erweckt, man könnte die Spalte dann noch
        // umbenennen.
        $dca['fields']['customFieldName']['eval']['readonly'] = true;
    }

    /**
     * Echte, serverseitige Durchsetzung von LOCKED_ONCE_DATA_EXISTS -
     * siehe Klassenkommentar dort, warum die reine "readonly"-Eval-
     * Option dafür nicht ausreicht. Wirft bei einem Änderungsversuch
     * eine Exception, die Contao als normalen Formular-Validierungs-
     * fehler anzeigt (wie guardDeactivation() in DynamicFieldSchemaSync)
     * - aber nur, wenn die Zielspalte(n) schon echte Daten enthalten;
     * davor bleibt alles frei korrigierbar.
     */
    private function guardImmutable(string $field, mixed $value, DataContainer $dc): mixed
    {
        if (!$dc->id) {
            return $value;
        }

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT fieldName, targetTable, fieldType, %s AS currentValue FROM tl_dynamic_field WHERE id = ?', $field),
            [(int) $dc->id],
        );

        if (false === $row || '' === (string) $row['fieldName']) {
            // Noch nicht angelegt (kein fieldName) - frei änderbar.
            return $value;
        }

        // Beide Seiten einheitlich als sortiertes Array normalisieren,
        // bevor verglichen wird - deckt sowohl einfache Skalarfelder
        // (targetTable, fieldType, ...; StringUtil::deserialize() mit
        // forceArray wickelt einen Skalar einfach in ein Einzelelement-
        // Array) als auch das seit 2026-11-02 mehrwertige
        // "contentElementType" (Checkbox-Mehrfachauswahl) gleich ab,
        // unabhängig davon, ob Contao den save_callback mit einem
        // bereits serialisierten String oder einem rohen Array
        // aufruft - beides kommt bei Mehrfachfeldern je nach
        // Contao-Codepfad vor.
        $incoming = \is_array($value) ? $value : StringUtil::deserialize((string) $value, true);
        $stored = StringUtil::deserialize((string) $row['currentValue'], true);
        sort($incoming);
        sort($stored);

        if ($incoming === $stored) {
            return $value;
        }

        $oldTargetTables = StringUtil::deserialize((string) $row['targetTable'], true);

        if (!$this->schemaSync->fieldHasDataAnywhere($oldTargetTables, (string) $row['fieldName'], (string) $row['fieldType'])) {
            // Noch keine echten Daten in der Zielspalte - eine Korrektur
            // (z.B. versehentlich falscher Feldtyp im Baumodus gewählt)
            // bleibt gefahrlos möglich, solange nichts verloren gehen kann.
            return $value;
        }

        $label = $GLOBALS['TL_LANG']['tl_dynamic_field'][$field][0] ?? $field;

        throw new \RuntimeException(sprintf(
            '"%s" kann nicht mehr geändert werden, da "%s" in "%s" bereits Daten enthält.',
            $label,
            $row['fieldName'],
            implode(', ', $oldTargetTables),
        ));
    }

    /**
     * Da "targetTable" seit der Mehrfachauswahl (2026-11-02) ein
     * serialisiertes Array ist, kann die Listenansicht nicht mehr per
     * simplem "format"+"fields" formatieren (würde den Rohwert zeigen) -
     * stattdessen hier die Tabellennamen lesbar auflösen und
     * kommagetrennt zusammenfassen.
     *
     * @param array<string, mixed> $row
     */
    private function formatListLabel(array $row): string
    {
        $tableLabels = $this->tableRegistry->getTableOptions();
        $selected = StringUtil::deserialize($row['targetTable'] ?? '', true);

        $tables = implode(', ', array_map(
            static fn (string $table): string => $tableLabels[$table] ?? $table,
            $selected,
        ));

        $fieldType = (string) ($row['fieldType'] ?? '');

        return sprintf(
            '%s <span class="tl_gray">[%s / %s]</span>',
            StringUtil::specialchars((string) ($row['fieldLabel'] ?? '')),
            StringUtil::specialchars('' !== $tables ? $tables : '–'),
            StringUtil::specialchars(DynamicFieldTypeRegistry::LABELS[$fieldType] ?? $fieldType),
        );
    }

    /**
     * Ein dupliziertes Feld zeigt sonst (readonly, siehe oben) weiterhin
     * auf denselben technischen Namen wie das Original und wird beim
     * Laden der Zieltabelle stillschweigend übersprungen (schon
     * belegter Feldname) - die Kopie wäre ein wirkungsloses Phantom.
     * Stattdessen den Namen zurücksetzen, damit die Kopie beim nächsten
     * Speichern ganz normal ihren eigenen Namen/ihre eigene Spalte
     * bekommt.
     */
    private function resetCopiedField(int $insertId): void
    {
        $this->connection->update('tl_dynamic_field', ['fieldName' => ''], ['id' => $insertId]);
    }
}
