<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldSchemaSync;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTableRegistry;

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
            $targetTable = Input::post('targetTable');

            if (!\is_string($targetTable) || '' === $targetTable) {
                $record = $dc->activeRecord;
                $targetTable = \is_object($record) ? (string) ($record->targetTable ?? '') : '';
            }

            return $this->tableRegistry->getLegendOptions('' !== $targetTable ? $targetTable : null);
        };

        $dca['fields']['active']['save_callback'][] = function (mixed $value, DataContainer $dc): mixed {
            return $this->schemaSync->guardDeactivation($value, $dc);
        };

        $dca['config']['onload_callback'][] = function (DataContainer|null $dc = null): void {
            $this->lockAfterCreation($dc);
        };

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->schemaSync->onSubmit($dc);
        };

        $dca['config']['oncopy_callback'][] = function (int $insertId): void {
            $this->resetCopiedField($insertId);
        };
    }

    /**
     * Zieltabelle und Feldtyp dürfen nach dem ersten erfolgreichen
     * Speichern (sobald "fieldName" feststeht und die Spalte existiert)
     * nicht mehr geändert werden - sonst würde das DCA auf eine andere
     * Tabelle/einen anderen Typ zeigen, während die tatsächliche
     * DB-Spalte noch in der alten Tabelle mit dem alten Typ liegt, was
     * beim nächsten Speichern eines Datensatzes dort zu einem
     * SQL-Fehler ("Unknown column") führen würde.
     *
     * "Titel des neuen Abschnitts" aus demselben Grund gesperrt: Der
     * Titel bestimmt direkt den Namen des Palette-Abschnitts (siehe
     * DynamicFieldInjector::legendKeyForTitle()) - würde er nachträglich
     * geändert, würde das Feld beim nächsten Laden der Zieltabelle in
     * einen ANDEREN (neuen) Abschnitt wandern, statt im bestehenden zu
     * bleiben, was für Redakteure, die sich an die bisherige Stelle
     * gewöhnt haben, überraschend wäre.
     */
    private function lockAfterCreation(DataContainer|null $dc): void
    {
        if (!$dc instanceof DataContainer) {
            return;
        }

        $record = $dc->activeRecord;

        if (!\is_object($record) || '' === (string) ($record->fieldName ?? '')) {
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
        // "customFieldName" bestimmt den technischen Namen nur beim
        // ALLERERSTEN Speichern (siehe DynamicFieldSchemaSync::
        // resolveFieldName(), bricht bei bereits gesetztem "fieldName"
        // sofort ab) - eine spätere Änderung hätte also ohnehin keinen
        // Effekt mehr auf den echten Spaltennamen. Gesperrt, damit das
        // nicht den Eindruck erweckt, man könnte die Spalte so umbenennen.
        $dca['fields']['customFieldName']['eval']['readonly'] = true;
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
