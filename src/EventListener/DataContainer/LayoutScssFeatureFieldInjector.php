<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Futuraweb\ContaoBackendSuite\ThemeScssFeatures\ScssFeatureSelector;

/**
 * Fügt eine Checkbox-Liste der optionalen SCSS-Features (die
 * auskommentierbaren "@import"-Zeilen in base.scss) zum
 * Seitenlayout-Formular hinzu, direkt unterhalb von "Stylesheets".
 *
 * Priority 10, damit diese Legend sicher VOR der JS-Funktionen-Legend
 * (siehe LayoutJsFunctionFieldInjector) angelegt wird - die haengt sich
 * mit POSITION_AFTER an diese hier an.
 */
#[AsHook('loadDataContainer', priority: 10)]
class LayoutScssFeatureFieldInjector
{
    private const FIELD = 'backendSuiteScssFeatures';
    private const LEGEND = 'backend_suite_scss_features_legend';

    public function __construct(private readonly ScssFeatureSelector $selector)
    {
    }

    public function __invoke(string $table): void
    {
        if ('tl_layout' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_layout'];

        if (isset($dca['fields'][self::FIELD])) {
            return;
        }

        $dca['fields'][self::FIELD] = [
            'label' => ['SCSS-Features', 'Legt fest, welche optionalen @import-Zeilen in base.scss aktiv sind. Immer aktive Grund-Imports (Header, Hauptnavi, ...) stehen hier nicht zur Auswahl.'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'options_callback' => fn (DataContainer $dc): array => $this->selector->getOptionsForDc($dc),
            'eval' => ['multiple' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ];

        $dca['config']['onload_callback'][] = function (DataContainer|null $dc = null): void {
            $this->selector->onLoad($dc);
        };

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->selector->onSubmit($dc);
        };

        $GLOBALS['TL_LANG']['tl_layout'][self::LEGEND] = 'SCSS-Features';

        PaletteManipulator::create()
            ->addLegend(self::LEGEND, 'style_legend', PaletteManipulator::POSITION_AFTER)
            ->addField([self::FIELD], self::LEGEND, PaletteManipulator::POSITION_APPEND)
            ->applyToPalette('default', 'tl_layout')
        ;
    }
}
