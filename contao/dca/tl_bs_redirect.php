<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Ein Datensatz = eine alte URL mit ihrem Weiterleitungsvorschlag.
 *
 * Bis die Schaltflaeche "Weiterleitungsseiten anlegen" gedrueckt wird,
 * passiert hier nichts am Seitenbaum: diese Tabelle ist reine Vorschau,
 * damit falsche Zuordnungen vorher auffallen.
 */
$GLOBALS['TL_DCA']['tl_bs_redirect'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_bs_redirect_import',
        'enableVersioning' => false,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_PARENT,
            'fields' => ['sorting'],
            'headerFields' => ['title', 'rootPage', 'targetFolder', 'sourceType'],
            'panelLayout' => 'filter;search,limit',
            'defaultSearchField' => 'source',
            // child_record_callback: RedirectCandidateListListener
        ],
        'global_operations' => [
            'createPages' => [
                'href' => 'key=createPages',
                'class' => 'header_new',
                'icon' => 'new.svg',
            ],
            'all',
        ],
        'operations' => [
            'edit',
            'delete',
            'show',
        ],
    ],
    'palettes' => [
        'default' => '{candidate_legend},source,alias;{target_legend},targetPage',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_bs_redirect_import.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'sorting' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'source' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect']['source'],
            'search' => true,
            'inputType' => 'text',
            // Nach dem Import nicht mehr sinnvoll aenderbar: der Alias ist
            // daraus schon berechnet. Wer eine andere alte URL braucht,
            // ergaenzt sie in der Quelle und importiert erneut.
            'eval' => ['readonly' => true, 'maxlength' => 1024, 'tl_class' => 'long clr'],
            'sql' => "varchar(1024) NOT NULL default ''",
        ],
        'alias' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect']['alias'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            // Gleicher rgxp wie tl_page.alias: der Wert wandert unveraendert
            // dorthin und muss daher dieselbe Pruefung bestehen.
            'eval' => ['rgxp' => 'folderalias', 'maxlength' => 255, 'tl_class' => 'long clr'],
            'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
        'targetPage' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect']['targetPage'],
            'exclude' => true,
            'inputType' => 'pageTree',
            'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'score' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect']['score'],
            'filter' => true,
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'state' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect']['state'],
            'filter' => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_bs_redirect']['states'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'createdPage' => [
            'label' => &$GLOBALS['TL_LANG']['tl_bs_redirect']['createdPage'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
    ],
];
