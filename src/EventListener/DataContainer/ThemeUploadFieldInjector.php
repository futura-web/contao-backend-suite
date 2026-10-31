<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * Adds the "shrink + rename images on upload" checkbox to the theme edit
 * form. The field itself is toggled from the file manager (see
 * UploadToggleRequestListener), but it lives on tl_theme since the setting
 * is per theme ("project").
 */
#[AsHook('loadDataContainer')]
class ThemeUploadFieldInjector
{
    private const FIELD = 'backendSuiteShrinkOnUpload';

    public function __invoke(string $table): void
    {
        if ('tl_theme' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_theme'];

        if (isset($dca['fields'][self::FIELD])) {
            return;
        }

        $dca['fields'][self::FIELD] = [
            'label' => &$GLOBALS['TL_LANG']['tl_theme']['backendSuite'][self::FIELD],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => ['type' => 'boolean', 'default' => false],
        ];

        if (isset($dca['palettes']['default']) && !str_contains($dca['palettes']['default'], self::FIELD)) {
            $block = '{backend_suite_legend},'.self::FIELD;

            // Insert right before the fonts/colors legend when it's already
            // there, so this toggle doesn't end up after it - otherwise
            // just append like before.
            if (str_contains($dca['palettes']['default'], '{backend_suite_font_color_legend}')) {
                $dca['palettes']['default'] = str_replace(
                    '{backend_suite_font_color_legend}',
                    $block.';{backend_suite_font_color_legend}',
                    $dca['palettes']['default'],
                );
            } else {
                $dca['palettes']['default'] .= ';'.$block;
            }
        }
    }
}
