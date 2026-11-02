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
            // Nicht mehr nach "targetTable" sortierbar, seit das seit
            // 2026-11-02 ein serialisiertes Array (Mehrfachauswahl) ist.
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['fieldLabel'],
            'flag' => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            // "targetTable" ist seit der Mehrfachauswahl ein
            // serialisiertes Array - label_callback statt format+fields,
            // damit es lesbar (Tabellennamen statt Rohwert) dargestellt
            // wird. Siehe DynamicFieldAdminFormInjector.
            'fields' => ['fieldLabel', 'targetTable', 'fieldType'],
        ],
        // "toggle" erlaubt Deaktivieren direkt per Klick in der Liste
        // (Syntax 1:1 aus Contao-Core, tl_news.php "published").
        // "delete" ist seit 2026-11-02 auf Nutzerwunsch erlaubt, aber
        // über ondelete_callback (DynamicFieldSchemaSync::
        // guardDeletion()) abgesichert: nur möglich, solange die Spalte
        // in der Zieltabelle keine echten Daten enthält. Die Spalte
        // selbst wird dabei nie automatisch gedroppt.
        'operations' => [
            'edit',
            'copy',
            'toggle' => [
                'href' => 'act=toggle&field=active',
                'icon' => 'visible.svg',
                'showInHeader' => true,
            ],
            'delete',
            'show',
        ],
    ],
    'palettes' => [
        // "targetTable" steht bewusst NICHT (mehr) im __selector__: seit
        // der Umstellung auf Mehrfachauswahl (2026-11-02) ist es ein
        // Array-Wert, und Contaos PaletteBuilder::getPalette() baut den
        // Subpaletten-Schlüssel per "(string) $trigger" - bei einem
        // Array würde das zur Laufzeit zu "feld_Array" verkommen (PHPs
        // "Array to string conversion"), der Subpalette-Eintrag für
        // "tl_content" also NIE mehr treffen, ohne jede Fehlermeldung
        // (derselbe Fallstrick wie beim Subpaletten-Schlüsselformat
        // weiter unten, nur diesmal durch den Array-Wert ausgelöst).
        // "contentElementType" ist deshalb jetzt einfach immer Teil der
        // normalen Palette statt hinter einer Subpalette versteckt -
        // der Hilfetext erklärt, dass es nur bei "Inhaltselemente"
        // relevant ist.
        '__selector__' => ['fieldType'],
        'default' => '{active_legend},active;{target_legend},targetTable,contentElementType,legend,newLegendTitle;{field_legend},fieldType,fieldLabel,fieldDescription,customFieldName;{snippet_legend},snippetPreview',
    ],
    // Subpaletten, gesteuert über das Selector-Feld oben (__selector__
    // + submitOnChange): "optionsSource" + "selectOptions" (Hilfetext
    // dort erklärt, dass Letzteres nur bei Quelle "Frei eingetippt"
    // greift) zusammen nur bei Feldtyp "Select"; "gallerySize" nur bei
    // Feldtyp "Galerie".
    //
    // WICHTIG zum Schlüsselformat: Contaos PaletteBuilder::getPalette()
    // sucht bei einem NICHT-Checkbox-Selektorfeld (hier: ein
    // select-Feld) unter dem Schlüssel "<Feldname>_<Wert>"
    // (z.B. "fieldType_select"), NICHT einfach unter dem Wert allein
    // (z.B. "select") - nur ein einzelnes, nicht-multiples
    // Checkbox-Feld würde den Wert als eigenen Schlüssel verwenden
    // (siehe core-bundle/src/DataContainer/PaletteBuilder.php). Mit dem
    // falschen Schlüssel bleibt das Feld bei JEDEM Wert unsichtbar, ohne
    // Fehlermeldung - genau das war die Ursache der vorherigen beiden
    // fehlgeschlagenen Versuche.
    'subpalettes' => [
        'fieldType_'.DynamicFieldTypeRegistry::SELECT => 'optionsSource,selectOptions',
        'fieldType_'.DynamicFieldTypeRegistry::GALLERY => 'gallerySize',
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
            'toggle' => true,
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr'],
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'targetTable' => [
            // Mehrfachauswahl seit 2026-11-02 (Nutzerwunsch): EIN Feld
            // kann dadurch gleichzeitig in mehreren Tabellen (je eine
            // eigene Spalte) angelegt werden - siehe
            // DynamicFieldSchemaSync::onSubmit().
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['targetTable'],
            'exclude' => true,
            'inputType' => 'checkbox',
            // options_callback wird von DynamicFieldAdminFormInjector gesetzt.
            'eval' => ['mandatory' => true, 'multiple' => true, 'submitOnChange' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ],
        'legend' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['legend'],
            'exclude' => true,
            'inputType' => 'select',
            // options_callback wird von DynamicFieldAdminFormInjector gesetzt.
            'eval' => ['mandatory' => true, 'tl_class' => 'w33', 'chosen' => true],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'newLegendTitle' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['newLegendTitle'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 128, 'tl_class' => 'w33', 'placeholder' => 'nur nötig bei "Neuer eigener Abschnitt"'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'contentElementType' => [
            // Mehrfachauswahl seit 2026-11-02 (Nutzerwunsch): leer
            // gelassen = alle kuratierten Typen (DynamicFieldInjector::
            // paletteKeysFor()), sonst nur die angehakten. Immer Teil
            // der normalen Palette (nicht mehr hinter einer Subpalette
            // versteckt, siehe Kommentar bei "__selector__" oben) - nur
            // relevant, wenn "Inhaltselemente" bei "Zieltabelle"
            // mit ausgewählt ist.
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['contentElementType'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'options' => array_keys(DynamicFieldTableRegistry::CONTENT_ELEMENT_TYPES),
            'reference' => DynamicFieldTableRegistry::CONTENT_ELEMENT_TYPES,
            'eval' => ['multiple' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ],
        'fieldType' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldType'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => array_keys(DynamicFieldTypeRegistry::LABELS),
            'reference' => DynamicFieldTypeRegistry::LABELS,
            'eval' => ['mandatory' => true, 'submitOnChange' => true, 'includeBlankOption' => true, 'tl_class' => 'clr w33', 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'fieldLabel' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldLabel'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w33'],
            'sql' => "varchar(128) NOT NULL default ''",
        ],
        'fieldDescription' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['fieldDescription'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w33'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'customFieldName' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['customFieldName'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 48, 'tl_class' => 'w33', 'placeholder' => 'z.B. hinweistext'],
            'sql' => "varchar(48) NOT NULL default ''",
        ],
        'optionsSource' => [
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['optionsSource'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => array_keys(DynamicFieldTypeRegistry::OPTIONS_SOURCE_LABELS),
            'reference' => DynamicFieldTypeRegistry::OPTIONS_SOURCE_LABELS,
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w33', 'chosen' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'selectOptions' => [
            // Listeneinträge-Wizard statt Freitext-Textarea (seit
            // 2026-11-02, auf Nutzerwunsch): gleiches Muster wie Contaos
            // natives "Aufzählung"-Inhaltselement (tl_content.listitems).
            // Der eingegebene Text ist zugleich Wert UND Label - siehe
            // DynamicFieldTypeRegistry::parseSelectOptions().
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['selectOptions'],
            'exclude' => true,
            'inputType' => 'listWizard',
            'eval' => ['multiple' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ],
        'gallerySize' => [
            // Options kommen von DynamicFieldGallerySizeOptionsListener
            // (liest die tl_image_size-Presets des aktuellen Benutzers,
            // 1:1 nach dem Muster von Contao-Cores
            // ImageSizeOptionsListener für tl_content.size usw.).
            'label' => &$GLOBALS['TL_LANG']['tl_dynamic_field']['gallerySize'],
            'exclude' => true,
            'inputType' => 'imageSize',
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval' => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50 clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'fieldName' => [
            // Technischer Spaltenname, automatisch aus "fieldLabel"
            // abgeleitet (siehe DynamicFieldSchemaSync::deriveFieldName) -
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
