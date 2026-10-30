<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Futuraweb\ContaoBackendSuite\ThemeScssFeatures\ScssFeatureSelector;

/**
 * Fügt eine Checkbox-Liste der optionalen SCSS-Features (die
 * auskommentierbaren "@import"-Zeilen in base.scss) zum
 * Theme-Bearbeiten-Formular hinzu.
 */
#[AsHook('loadDataContainer')]
class ThemeScssFeatureFieldInjector
{
    private const FIELD = 'backendSuiteScssFeatures';
    private const LEGEND = 'backend_suite_scss_features_legend';

    public function __construct(private readonly ScssFeatureSelector $selector)
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

        $this->injectIntoPalettes($dca);
    }

    private function injectIntoPalettes(array &$dca): void
    {
        if (!isset($dca['palettes']) || !\is_array($dca['palettes'])) {
            return;
        }

        $block = '{'.self::LEGEND.'},'.self::FIELD;
        $GLOBALS['TL_LANG']['tl_theme'][self::LEGEND] = 'SCSS-Features';

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
