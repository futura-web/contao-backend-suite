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
    /**
     * Human-readable labels for the tables editors are most likely to see
     * here. Anything not listed falls back to a humanized version of the
     * table name instead of the raw "tl_..." string.
     */
    private const LABELS = [
        'tl_content' => 'Inhaltselemente',
        'tl_article' => 'Artikel',
        'tl_page' => 'Seiten',
        'tl_layout' => 'Seitenlayouts',
        'tl_module' => 'Module',
        'tl_image_size' => 'Bildgrößen',
        'tl_news' => 'Nachrichten',
        'tl_calendar_events' => 'Termine',
        'tl_faq' => 'FAQ',
        'tl_form' => 'Formulare',
        'tl_form_field' => 'Formularfelder',
        'tl_comments' => 'Kommentare',
        'tl_member' => 'Mitglieder',
    ];

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
            $options[$table] = self::LABELS[$table] ?? $this->humanize($table);
        }

        return $options;
    }

    private function humanize(string $table): string
    {
        $name = str_replace('tl_', '', $table);
        $name = str_replace('_', ' ', $name);

        return ucfirst($name);
    }
}
