<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldSnippetBuilder;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTableRegistry;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTypeRegistry;

$GLOBALS['TL_DCA']['tl_dynamic_field'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['targetTable', 'fieldLabel'],
            'flag' => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['fieldLabel', 'targetTable', 'fieldType'],
            'format' => '%s <span class="tl_gray">[%s / %s]</span>',
        ],
        // Bewusst kein "delete" - angelegte Felder werden nur über die
        // "Aktiv"-Checkbox deaktiviert, nie samt DB-Spalte gelöscht (
        // Nutzerentscheidung, siehe Memory feedback-no-hardcoded-env-values
        // /project-contao-backend-suite).
        'operations' => [
            'edit',
            'copy',
            'show',
        ],
    ],
    'palettes' => [
        '__selector__' => ['targetTable', 'fieldType'],
        'default' => '{active_legend},active;{target_legend},targetTable,legend,newLegendTitle;{field_legend},fieldType,fieldLabel,fieldDescription,customFieldName;{snippet_legend},snippetPreview',
    ],
    // Subpaletten, gesteuert über die Selector-Felder oben (__selector__
    // + submitOnChange): "contentElementType" nur bei Zieltabelle
    // "Inhaltselemente"; "optionsSource" + "selectOptions" (Hilfetext
    // dort erklärt, dass Letzteres nur bei Quelle "Frei eingetippt"
    // greift) zusammen nur bei Feldtyp "Select".
    //
    // WICHTIG zum Schlüsselformat: Contaos PaletteBuilder::getPalette()
    // sucht bei einem NICHT-Checkbox-Selektorfeld (hier: zwei
    // select-Felder) unter dem Schlüssel "<Feldname>_<Wert>"
    // (z.B. "fieldType_select"), NICHT einfach unter dem Wert allein
    // (z.B. "select") - nur ein einzelnes, nicht-multiples
    // Checkbox-Feld würde den Wert als eigenen Schlüssel verwenden
    // (siehe core-bundle/src/DataContainer/PaletteBuilder.php). Mit dem
    // falschen Schlüssel bleibt das Feld bei JEDEM Wert unsichtbar, ohne
    // Fehlermeldung - genau das war die Ursache der vorherigen beiden
    // fehlgeschlagenen Versuche.
    'subpalettes' => [
        'targetTable_'.DynamicFieldTableRegistry::CONTENT_ELEMENT_TABLE => 'contentElementType',
        'fieldType_'.DynamicFieldTypeRegistry::SELECT => 'optionsSource,selectOptions',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'active' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['active'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr'],
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'targetTable' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['targetTable'],
            'exclude' => true,
            'inputType' => 'select',
            // options_callback wird von DynamicFieldAdminFormInjector gesetzt.
            'eval' => ['mandatory' => true, 'submitOnChange' => true, 'includeBlankOption' => true, 'tl_class' => 'w50', 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'legend' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['legend'],
            'exclude' => true,
            'inputType' => 'select',
            // options_callback wird von DynamicFieldAdminFormInjector gesetzt.
            'eval' => ['mandatory' => true, 'tl_class' => 'w50', 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'newLegendTitle' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['newLegendTitle'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'clr w50', 'placeholder' => 'nur nötig bei "Neuer eigener Abschnitt"'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'contentElementType' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['contentElementType'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => array_keys(DynamicFieldTableRegistry::CONTENT_ELEMENT_TYPES),
            'reference' => DynamicFieldTableRegistry::CONTENT_ELEMENT_TYPES,
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr w50', 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'fieldType' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldType'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => array_keys(DynamicFieldTypeRegistry::LABELS),
            'reference' => DynamicFieldTypeRegistry::LABELS,
            'eval' => ['mandatory' => true, 'submitOnChange' => true, 'includeBlankOption' => true, 'tl_class' => 'w50', 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'fieldLabel' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldLabel'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'clr w50'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'fieldDescription' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldDescription'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'customFieldName' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['customFieldName'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 48, 'tl_class' => 'clr w50', 'placeholder' => 'z.B. hinweistext'],
            'sql' => "varchar(48) NOT NULL default ''",
        ],
        'optionsSource' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['optionsSource'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => array_keys(DynamicFieldTypeRegistry::OPTIONS_SOURCE_LABELS),
            'reference' => DynamicFieldTypeRegistry::OPTIONS_SOURCE_LABELS,
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50', 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'selectOptions' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['selectOptions'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr', 'rows' => 4, 'placeholder' => "wert1:Label 1\nwert2:Label 2", 'decodeEntities' => true],
            'sql' => 'text NULL',
        ],
        'fieldName' => [
            // Technischer Spaltenname, automatisch aus "fieldLabel"
            // abgeleitet (siehe DynamicFieldSchemaSync::resolveFieldName) -
            // kein eigenes Eingabefeld, nicht in der Palette.
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'snippetPreview' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['snippetPreview'],
            'exclude' => true,
            'input_field_callback' => static function (DataContainer $dc): string {
                return (new DynamicFieldSnippetBuilder())->render($dc);
            },
            'eval' => ['tl_class' => 'clr long'],
        ],
    ],
];
