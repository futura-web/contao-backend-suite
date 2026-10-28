<?php

declare(strict_types=1);

namespace Futura\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Futura\ContaoBackendSuite\CssStyle\StyleMerger;
use Futura\ContaoBackendSuite\CssStyle\StyleSelectorTableRegistry;

/**
 * Injects the `ffStyleSelector` field into every DCA table that has a
 * `cssID` or `cssClass` field — no manual wiring needed per table.
 */
#[AsHook('loadDataContainer')]
class StyleSelectorFieldInjector
{
    private const FIELD = 'ffStyleSelector';

    public function __construct(
        private readonly StyleSelectorTableRegistry $tableRegistry,
        private readonly StyleMerger $styleMerger,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ('tl_style_selector' === $table) {
            return;
        }

        $classField = $this->tableRegistry->getClassFieldForTable($table);

        if (null === $classField) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA'][$table];

        if (isset($dca['fields'][self::FIELD])) {
            return;
        }

        $dca['fields'][self::FIELD] = [
            'label' => &$GLOBALS['TL_LANG']['tl_style_selector']['fieldLabel'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => function () use ($table) {
                return $this->getOptionsForTable($table);
            },
            'eval' => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
            'save_callback' => [
                function ($value, DataContainer $dc) use ($table, $classField) {
                    return $this->onSave($value, $dc, $table, $classField);
                },
            ],
            'sql' => 'blob NULL',
        ];

        $this->injectIntoPalettes($dca, $classField);
    }

    /**
     * @return array<int, string>
     */
    private function getOptionsForTable(string $table): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, tables FROM tl_style_selector ORDER BY title',
        );

        $options = [];

        foreach ($rows as $row) {
            $allowedTables = StringUtil::deserialize($row['tables'], true);

            if (\in_array($table, $allowedTables, true)) {
                $options[(int) $row['id']] = $row['title'];
            }
        }

        return $options;
    }

    private function onSave(mixed $value, DataContainer $dc, string $table, string $classField): mixed
    {
        if (!$dc->activeRecord) {
            return $value;
        }

        $selectedIds = array_map('intval', (array) StringUtil::deserialize($value, true));

        if ('cssID' === $classField) {
            $cssId = (array) StringUtil::deserialize($dc->activeRecord->cssID, true);
            $existingClasses = (string) ($cssId[1] ?? '');
            $newClasses = $this->styleMerger->mergeClasses($existingClasses, $selectedIds);

            $this->connection->update(
                $table,
                ['cssID' => serialize([$cssId[0] ?? '', $newClasses])],
                ['id' => $dc->id],
            );
        } else {
            $existingClasses = (string) $dc->activeRecord->cssClass;
            $newClasses = $this->styleMerger->mergeClasses($existingClasses, $selectedIds);

            $this->connection->update($table, ['cssClass' => $newClasses], ['id' => $dc->id]);
        }

        return $value;
    }

    private function injectIntoPalettes(array &$dca, string $classField): void
    {
        if (!isset($dca['palettes']) || !\is_array($dca['palettes'])) {
            return;
        }

        foreach ($dca['palettes'] as $name => $palette) {
            if (!\is_string($palette) || str_contains($palette, self::FIELD)) {
                continue;
            }

            if (str_contains($palette, ','.$classField)) {
                $dca['palettes'][$name] = str_replace(
                    ','.$classField,
                    ','.self::FIELD.','.$classField,
                    $palette,
                );
            }
        }
    }
}
