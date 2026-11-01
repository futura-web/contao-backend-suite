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
