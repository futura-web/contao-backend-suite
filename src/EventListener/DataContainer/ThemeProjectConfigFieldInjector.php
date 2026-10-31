<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Input;
use Futuraweb\ContaoBackendSuite\ThemeFontColor\ColorImporter;
use Futuraweb\ContaoBackendSuite\ThemeProjectConfig\BoilerplateRenamer;
use Futuraweb\ContaoBackendSuite\ThemeProjectConfig\BorderImporter;
use Futuraweb\ContaoBackendSuite\ThemeProjectConfig\HeaderNavImporter;
use Futuraweb\ContaoBackendSuite\ThemeProjectConfig\TypographyImporter;

/**
 * Projekt-Theme-Konfiguration: Rahmen, Header, Hauptnavi und
 * Schriftgrößen/Abstände - schreiben ihre Werte direkt in die
 * SCSS-Variablendateien des Projekts (siehe ThemeProjectConfig\*).
 *
 * Feldnamen bleiben bei der "project*"-Konvention (statt "backendSuite*"),
 * da dieses Feature aus einem projekt-lokalen contao/dca/tl_theme.php
 * hierher migriert wurde und bestehende Daten/Spalten weiterverwendet.
 */
#[AsHook('loadDataContainer')]
class ThemeProjectConfigFieldInjector
{
    public function __construct(
        private readonly BoilerplateRenamer $boilerplateRenamer,
        private readonly BorderImporter $borderImporter,
        private readonly HeaderNavImporter $headerNavImporter,
        private readonly TypographyImporter $typographyImporter,
        private readonly ColorImporter $colorImporter,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ('tl_theme' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_theme'];

        if (isset($dca['fields']['projectBorderColor'])) {
            return;
        }

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->boilerplateRenamer->onSubmit($dc);
        };

        $themeId = $this->currentThemeId();

        // Fremde Projekte ohne unsere SCSS-Boilerplate-Struktur bekommen
        // diese Felder gar nicht erst angezeigt, statt sie wirkungslos
        // einzublenden. Lässt sich die Theme-ID nicht bestimmen (z. B.
        // Listenansicht, Neuanlage), wird konservativ trotzdem eingehängt.
        if (null !== $themeId && !$this->colorImporter->hasScssStructureForTheme($themeId)) {
            return;
        }

        $this->injectBorderFields($dca);
        $this->injectHeaderNavFields($dca);
        $this->injectTypographyFields($dca);
    }

    private function currentThemeId(): int|null
    {
        $id = Input::get('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function injectBorderFields(array &$dca): void
    {
        $colorOptions = function (DataContainer $dc): array {
            return $this->colorOptionsFor($dc);
        };

        $dca['fields']['projectBorderColor'] = [
            'label' => ['Rahmenfarbe', 'Default: siehe aktueller Wert in _vars_borders.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w33', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectBorderColorFocus'] = [
            'label' => ['Rahmenfarbe bei Fokus', 'Default: siehe aktueller Wert in _vars_borders.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w33', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectBorderColorError'] = [
            'label' => ['Rahmenfarbe bei Fehler', 'Default: siehe aktueller Wert in _vars_borders.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w33', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectBorderSize'] = [
            'label' => ['Rahmenstärke', 'Nur die Zahl, ohne "px" (z.B. "2"). Default: "1px".'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w33', 'rgxp' => 'natural', 'placeholder' => 'z.B. 2'],
            'sql' => "varchar(16) NOT NULL default ''",
        ];

        $dca['fields']['projectBorderType'] = [
            'label' => ['Rahmenart', 'Default: "solid".'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => BorderImporter::BORDER_TYPES,
            'eval' => ['tl_class' => 'w33', 'includeBlankOption' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ];

        $dca['fields']['projectBorderRadius'] = [
            'label' => ['Eckenradius', 'Aus den vorhandenen Clamp-Größen (_vars_sizes.scss).'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => function (DataContainer $dc): array {
                return $this->borderImporter->getClampSizeOptions($dc);
            },
            'eval' => ['tl_class' => 'w33', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ];

        $GLOBALS['TL_LANG']['tl_theme']['borders_legend'] = 'Rahmen';

        PaletteManipulator::create()
            ->addLegend('borders_legend', 'colors_legend', PaletteManipulator::POSITION_AFTER)
            ->addField(
                ['projectBorderColor', 'projectBorderColorFocus', 'projectBorderColorError', 'projectBorderSize', 'projectBorderType', 'projectBorderRadius'],
                'borders_legend',
                PaletteManipulator::POSITION_APPEND,
            )
            ->applyToPalette('default', 'tl_theme')
        ;

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->borderImporter->onSubmit($dc);
        };

        $dca['config']['onload_callback'][] = function (DataContainer|null $dc = null): void {
            $this->borderImporter->onLoad($dc);
        };
    }

    private function injectHeaderNavFields(array &$dca): void
    {
        $colorOptions = function (DataContainer $dc): array {
            return $this->colorOptionsFor($dc);
        };

        $dca['fields']['projectHeightHeader'] = [
            'label' => ['Höhe Header', 'Aus den vorhandenen Clamp-Größen (_vars_sizes.scss).'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => function (DataContainer $dc): array {
                return $this->borderImporter->getClampSizeOptions($dc);
            },
            'eval' => ['tl_class' => 'w33', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ];

        $dca['fields']['projectLogoHeight'] = [
            'label' => ['Logohöhe in %', 'Nur die Zahl, ohne "%" (z.B. "70").'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w33', 'rgxp' => 'natural', 'placeholder' => 'z.B. 70'],
            'sql' => "varchar(16) NOT NULL default ''",
        ];

        $dca['fields']['projectColorHamburger'] = [
            'label' => ['Farbe Hamburger-Icon', 'Default: siehe aktueller Wert in _vars_header.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w33', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainDirection'] = [
            'label' => ['Einflug-Richtung', 'Aus welcher Richtung die Hauptnavigation einfliegt.'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['left', 'right'],
            'reference' => ['left' => 'Von Links', 'right' => 'Von Rechts'],
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true],
            'sql' => "varchar(16) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainColor'] = [
            'label' => ['Farbe', 'Default: siehe aktueller Wert in _vars_colors.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainColorHover'] = [
            'label' => ['Farbe bei Hover', 'Default: siehe aktueller Wert in _vars_colors.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainColorActive'] = [
            'label' => ['Farbe aktiv', 'Default: siehe aktueller Wert in _vars_colors.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainColorTrail'] = [
            'label' => ['Farbe Trail', 'Default: siehe aktueller Wert in _vars_colors.scss.'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainFontSize'] = [
            'label' => ['Schriftgröße', 'Start- und Endgröße, mit Leerzeichen getrennt, ohne "px" (z.B. "16 22"). Baut daraus einen responsiven clamp()-Wert.'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w25', 'placeholder' => 'z.B. 16 22'],
            'sql' => "varchar(16) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainFontWeight'] = [
            'label' => ['Schriftstärke', ''],
            'exclude' => true,
            'inputType' => 'select',
            'options' => HeaderNavImporter::FONT_WEIGHTS,
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true],
            'sql' => "varchar(8) NOT NULL default ''",
        ];

        $dca['fields']['projectNavMainBg'] = [
            'label' => ['Hintergrundfarbe', '– = kein Hintergrund (Default). Sonst Farbe aus dem Feld "Farben".'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $colorOptions,
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ];

        $GLOBALS['TL_LANG']['tl_theme']['header_legend'] = 'Header';
        $GLOBALS['TL_LANG']['tl_theme']['navmain_legend'] = 'Hauptnavi';

        PaletteManipulator::create()
            ->addLegend('header_legend', 'borders_legend', PaletteManipulator::POSITION_AFTER)
            ->addField(['projectHeightHeader', 'projectLogoHeight', 'projectColorHamburger'], 'header_legend', PaletteManipulator::POSITION_APPEND)
            ->addLegend('navmain_legend', 'header_legend', PaletteManipulator::POSITION_AFTER)
            ->addField(
                [
                    'projectNavMainBg', 'projectNavMainDirection', 'projectNavMainFontSize', 'projectNavMainFontWeight',
                    'projectNavMainColor', 'projectNavMainColorHover', 'projectNavMainColorActive', 'projectNavMainColorTrail',
                ],
                'navmain_legend',
                PaletteManipulator::POSITION_APPEND,
            )
            ->applyToPalette('default', 'tl_theme')
        ;

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->headerNavImporter->onSubmit($dc);
        };

        $dca['config']['onload_callback'][] = function (DataContainer|null $dc = null): void {
            $this->headerNavImporter->onLoad($dc);
        };
    }

    private function injectTypographyFields(array &$dca): void
    {
        $clampOptions = function (DataContainer $dc): array {
            return $this->borderImporter->getClampSizeOptions($dc);
        };

        $dca['fields']['projectTextFontSize'] = [
            'label' => ['Schriftgröße Fließtext', 'Start- und Endgröße, mit Leerzeichen getrennt, ohne "px" (z.B. "15 18"). Wirkt als Default in body{} für Absätze und Listenelemente.'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w25', 'placeholder' => 'z.B. 15 18'],
            'sql' => "varchar(16) NOT NULL default ''",
        ];

        $dca['fields']['projectMarginText'] = [
            'label' => ['Abstand Fließtext', 'Aus den vorhandenen Clamp-Größen (_vars_sizes.scss).'],
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => $clampOptions,
            'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true, 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ];

        foreach (range(1, 6) as $level) {
            $dca['fields']['projectH'.$level.'FontSize'] = [
                'label' => ['Schriftgröße H'.$level, 'Start- und Endgröße, mit Leerzeichen getrennt, ohne "px" (z.B. "22 28"). Standardmäßig identisch mit H1 bzw. Fließtext.'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['tl_class' => 'w25', 'placeholder' => 1 === $level ? 'z.B. 22 28' : 'z.B. 15 18'],
                'sql' => "varchar(16) NOT NULL default ''",
            ];

            $dca['fields']['projectMarginH'.$level] = [
                'label' => ['Abstand H'.$level, 'Aus den vorhandenen Clamp-Größen (_vars_sizes.scss). Standardmäßig identisch mit Fließtext.'],
                'exclude' => true,
                'inputType' => 'select',
                'options_callback' => $clampOptions,
                'eval' => ['tl_class' => 'w25', 'includeBlankOption' => true, 'chosen' => true],
                'sql' => "varchar(32) NOT NULL default ''",
            ];
        }

        $GLOBALS['TL_LANG']['tl_theme']['typography_legend'] = 'Schriftgrößen';

        PaletteManipulator::create()
            ->addLegend('typography_legend', 'colors_legend', PaletteManipulator::POSITION_AFTER)
            ->addField(
                [
                    'projectTextFontSize', 'projectMarginText',
                    'projectH1FontSize', 'projectMarginH1',
                    'projectH2FontSize', 'projectMarginH2',
                    'projectH3FontSize', 'projectMarginH3',
                    'projectH4FontSize', 'projectMarginH4',
                    'projectH5FontSize', 'projectMarginH5',
                    'projectH6FontSize', 'projectMarginH6',
                ],
                'typography_legend',
                PaletteManipulator::POSITION_APPEND,
            )
            ->applyToPalette('default', 'tl_theme')
        ;

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->typographyImporter->onSubmit($dc);
        };
    }

    private function colorOptionsFor(DataContainer $dc): array
    {
        return $this->colorImporter->getColorOptionsForDc($dc);
    }
}
