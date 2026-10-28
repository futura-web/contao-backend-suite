<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_style_selector'] = [
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
            'fields' => ['title'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['title', 'cssClass'],
            'format' => '%s <span class="tl_gray">[%s]</span>',
        ],
        'operations' => [
            'edit',
            'copy',
            'delete',
            'show',
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,cssClass;{tables_legend},tables',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_style_selector']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'cssClass' => [
            'label' => &$GLOBALS['TL_LANG']['tl_style_selector']['cssClass'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'tables' => [
            'label' => &$GLOBALS['TL_LANG']['tl_style_selector']['tables'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ],
    ],
];
