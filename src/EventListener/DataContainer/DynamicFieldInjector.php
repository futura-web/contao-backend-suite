<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\FilesModel;
use Doctrine\DBAL\Connection;
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

        $rows = $this->connection->fetchAllAssociative(
            "SELECT * FROM tl_dynamic_field WHERE targetTable = ? AND active = '1' AND fieldName != ''",
            [$table],
        );

        if ([] === $rows) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA'][$table];

        foreach ($rows as $row) {
            $fieldName = (string) $row['fieldName'];

            if (isset($dca['fields'][$fieldName])) {
                continue;
            }

            $dca['fields'][$fieldName] = $this->typeRegistry->buildDcaField($row) + [
                'sql' => $this->typeRegistry->sqlFor((string) $row['fieldType']),
            ];

            if (DynamicFieldTypeRegistry::GALLERY === (string) $row['fieldType']) {
                $this->applyGalleryUploadFolder($dca['fields'][$fieldName], $row['galleryUploadFolder'] ?? null);
            }

            $this->injectIntoPalette($table, $dca, $row, $fieldName);
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    private function applyGalleryUploadFolder(array &$field, mixed $folderUuid): void
    {
        if (!\is_string($folderUuid) || '' === $folderUuid) {
            return;
        }

        $folder = FilesModel::findByUuid($folderUuid);

        if (null !== $folder) {
            $field['eval']['uploadFolder'] = $folder->path;
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
        // Fallback-Anker raten zu lassen.
        $legendKey = 'dyn_field_legend';
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
}
