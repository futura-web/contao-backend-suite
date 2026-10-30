<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Futuraweb\ContaoBackendSuite\ThemeJsFunctions\JsFunctionSelector;

/**
 * Fügt eine Checkbox-Liste der verfügbaren JS-Funktionsdateien
 * (theme/js/functions/js.<name>.js) zum Theme-Bearbeiten-Formular hinzu,
 * als Alternative zum manuellen Ein-/Auskommentieren in
 * assembleFunctions.js.php.
 */
#[AsHook('loadDataContainer')]
class ThemeJsFunctionFieldInjector
{
    private const FIELD = 'backendSuiteJsFunctions';
    private const LEGEND = 'backend_suite_js_functions_legend';

    public function __construct(private readonly JsFunctionSelector $selector)
    {
    }

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

        $this->injectIntoPalettes($dca);
    }

    private function injectIntoPalettes(array &$dca): void
    {
        if (!isset($dca['palettes']) || !\is_array($dca['palettes'])) {
            return;
        }

        $block = '{'.self::LEGEND.'},'.self::FIELD;
        $GLOBALS['TL_LANG']['tl_theme'][self::LEGEND] = 'JavaScript-Funktionen';

        foreach ($dca['palettes'] as $name => $palette) {
            if (!\is_string($palette) || str_contains($palette, self::FIELD)) {
                continue;
            }

            if (preg_match('/\{config_legend\}[^;]*/', $palette)) {
                $dca['palettes'][$name] = preg_replace('/(\{config_legend\}[^;]*)/', '$1;'.$block, $palette, 1);
            } else {
                $dca['palettes'][$name] = $palette.';'.$block;
            }
        }
    }
}
