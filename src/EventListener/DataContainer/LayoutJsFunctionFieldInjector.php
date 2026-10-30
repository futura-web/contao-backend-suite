<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Futuraweb\ContaoBackendSuite\ThemeJsFunctions\JsFunctionSelector;

/**
 * Fügt eine Checkbox-Liste der verfügbaren JS-Funktionsdateien
 * (theme/js/functions/js.<name>.js) zum Seitenlayout-Formular hinzu, als
 * Alternative zum manuellen Ein-/Auskommentieren in
 * assembleFunctions.js.php.
 *
 * Haengt sich mit POSITION_AFTER an die SCSS-Features-Legend an (siehe
 * LayoutScssFeatureFieldInjector, Priority 10), daher niedrigere
 * Default-Priority hier.
 */
#[AsHook('loadDataContainer')]
class LayoutJsFunctionFieldInjector
{
    private const FIELD = 'backendSuiteJsFunctions';
    private const LEGEND = 'backend_suite_js_functions_legend';
    private const ANCHOR_LEGEND = 'backend_suite_scss_features_legend';

    public function __construct(private readonly JsFunctionSelector $selector)
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
            'label' => ['JavaScript-Funktionen', 'Welche der unter "theme/js/functions/" vorhandenen Funktionen aktiv sind. Steuert, ob der jeweilige Eintrag in assembleFunctions.js.php aktiv oder auskommentiert ist - eine bislang nirgends eingetragene Funktion wird beim ersten Aktivieren neu im Bucket "ready" angelegt.'],
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

        $GLOBALS['TL_LANG']['tl_layout'][self::LEGEND] = 'JavaScript-Funktionen';

        $anchorExists = isset($dca['palettes']['default']) && \is_string($dca['palettes']['default'])
            && str_contains($dca['palettes']['default'], '{'.self::ANCHOR_LEGEND);

        PaletteManipulator::create()
            ->addLegend(self::LEGEND, $anchorExists ? self::ANCHOR_LEGEND : 'style_legend', PaletteManipulator::POSITION_AFTER)
            ->addField([self::FIELD], self::LEGEND, PaletteManipulator::POSITION_APPEND)
            ->applyToPalette('default', 'tl_layout')
        ;
    }
}
