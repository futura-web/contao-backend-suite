<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Input;
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

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->schemaSync->onSubmit($dc);
        };
    }
}
