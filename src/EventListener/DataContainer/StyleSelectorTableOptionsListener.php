<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Futuraweb\ContaoBackendSuite\CssStyle\StyleSelectorTableRegistry;

/**
 * Provides the "available in" table checkboxes on tl_style_selector.
 */
#[AsCallback(table: 'tl_style_selector', target: 'fields.tables.options')]
class StyleSelectorTableOptionsListener
{
    public function __construct(private readonly StyleSelectorTableRegistry $tableRegistry)
    {
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(): array
    {
        $options = [];

        foreach (array_keys($this->tableRegistry->getTables()) as $table) {
            $options[$table] = $table;
        }

        return $options;
    }
}
