<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Futuraweb\ContaoBackendSuite\ThemeFontColor\ColorImporter;
use Futuraweb\ContaoBackendSuite\ThemeFontColor\FontImporter;

/**
 * Fügt die Felder für den automatischen Google-Fonts-Import und die
 * Farb-Variablen-Pflege zum Theme-Bearbeiten-Formular hinzu und bindet
 * FontImporter/ColorImporter beim Speichern ein.
 *
 * Die Such- und Auswahl-Oberfläche selbst ist Client-seitiges JavaScript
 * (public/backend/js.fontPicker.js), das die nativen Textarea-Felder
 * ersetzt/erweitert - ein PHP input_field_callback hat sich in der Praxis
 * (Contao 5 DC_Table-Rendering) als nicht zuverlässig aufrufbar erwiesen.
 */
#[AsHook('loadDataContainer')]
class ThemeFontColorFieldInjector
{
    private const LEGEND = 'backend_suite_font_color_legend';

    private const FIELDS = [
        'backendSuiteFonts',
        'backendSuiteFontsDeleteOld',
        'backendSuiteFontSupport',
        'backendSuiteColors',
    ];

    public function __construct(
        private readonly FontImporter $fontImporter,
        private readonly ColorImporter $colorImporter,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ('tl_theme' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_theme'];

        if (isset($dca['fields']['backendSuiteFonts'])) {
            return;
        }

        $dca['fields']['backendSuiteFonts'] = [
            'label' => &$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFonts'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr long', 'style' => 'height:60px'],
            'sql' => 'text NULL',
        ];

        $dca['fields']['backendSuiteFontsDeleteOld'] = [
            'label' => &$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontsDeleteOld'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr'],
            'sql' => "char(1) NOT NULL default ''",
        ];

        $dca['fields']['backendSuiteFontSupport'] = [
            'label' => &$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontSupport'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['modern', 'legacy', 'historic'],
            'reference' => &$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontSupportRef'],
            'eval' => ['tl_class' => 'w50', 'includeBlankOption' => false],
            'default' => 'modern',
            'sql' => "varchar(32) NOT NULL default 'modern'",
        ];

        $dca['fields']['backendSuiteColors'] = [
            'label' => &$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteColors'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr w33', 'style' => 'height:100px', 'decodeEntities' => true],
            'sql' => 'text NULL',
        ];

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->fontImporter->onSubmit($dc);
        };

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->colorImporter->onSubmit($dc);
        };

        $this->injectIntoPalettes($dca);

        // Nur im Bearbeiten-Formular noetig; Contao laedt BE_CSS/BE_JS auch
        // auf anderen tl_theme-Aktionen (Liste, Baumansicht), dort schadet
        // das Laden nicht, macht aber auch nichts - deshalb kein weiterer
        // Action-Check.
        $GLOBALS['TL_CSS'][] = 'bundles/contaobackendsuite/backend/fontColorPicker.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaobackendsuite/backend/fontColorPicker.js';
    }

    private function injectIntoPalettes(array &$dca): void
    {
        if (!isset($dca['palettes']) || !\is_array($dca['palettes'])) {
            return;
        }

        $block = '{'.self::LEGEND.'},'.implode(',', self::FIELDS);

        foreach ($dca['palettes'] as $name => $palette) {
            if (!\is_string($palette) || str_contains($palette, 'backendSuiteFonts')) {
                continue;
            }

            // Direkt hinter "{config_legend}" einfuegen, damit der neue Tab
            // gleich nach "Konfiguration" erscheint statt ganz am Ende -
            // faellt auf reines Anhaengen zurueck, falls die Palette keinen
            // config_legend-Abschnitt hat.
            if (preg_match('/\{config_legend\}[^;]*/', $palette)) {
                $dca['palettes'][$name] = preg_replace('/(\{config_legend\}[^;]*)/', '$1;'.$block, $palette, 1);
            } else {
                $dca['palettes'][$name] = $palette.';'.$block;
            }
        }
    }
}
