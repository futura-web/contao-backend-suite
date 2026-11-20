<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use Futuraweb\ContaoBackendSuite\RedirectWizard\WaybackClient;

/**
 * Ein Datensatz = ein Importlauf fuer ein Altprojekt: welcher Startpunkt
 * gemeint ist, wo die Weiterleitungsseiten abgelegt werden, und woher die
 * Liste der alten URLs kommt.
 *
 * Die Kindtabelle tl_bs_redirect haelt die daraus erzeugten Vorschlaege.
 */
$GLOBALS['TL_DCA']['tl_bs_redirect_import'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ctable' => ['tl_bs_redirect'],
        'switchToEdit' => true,
        'enableVersioning' => false,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['title'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'search,limit',
            'defaultSearchField' => 'title',
        ],
        'label' => [
            'fields' => ['title'],
            'format' => '%s',
        ],
        'operations' => [
            'children' => [
                'href' => 'table=tl_bs_redirect',
                'icon' => 'edit.svg',
            ],
            'runImport' => [
                'href' => 'key=runImport',
                'icon' => 'theme_import.svg',
            ],
            'editheader' => [
                'href' => 'act=edit',
                'icon' => 'header.svg',
            ],
            'delete',
            'show',
        ],
    ],
    'palettes' => [
        '__selector__' => ['sourceType'],
        'default' => '{title_legend},title;{target_legend},rootPage,targetFolder;{source_legend},sourceType',
    ],
    'subpalettes' => [
        'sourceType_list' => 'urlList',
        'sourceType_wayback' => 'waybackDomain,waybackLimit',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'rootPage' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import']['rootPage'],
            'exclude' => true,
            'inputType' => 'select',
            // options_callback: RedirectImportOptionsListener
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'targetFolder' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import']['targetFolder'],
            'exclude' => true,
            'inputType' => 'pageTree',
            'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'sourceType' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import']['sourceType'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['list', 'wayback'],
            'reference' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import'],
            'eval' => ['mandatory' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default 'list'",
        ],
        'urlList' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import']['urlList'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['rows' => 16, 'style' => 'height:300px', 'decodeEntities' => true, 'tl_class' => 'clr'],
            'sql' => 'mediumtext NULL',
        ],
        'waybackDomain' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import']['waybackDomain'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'waybackLimit' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect_import']['waybackLimit'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => [
                'rgxp' => 'natural',
                'maxval' => WaybackClient::MAX_LIMIT,
                'maxlength' => 6,
                'tl_class' => 'w50',
            ],
            'sql' => 'int(10) unsigned NOT NULL default 1000',
        ],
    ],
];
