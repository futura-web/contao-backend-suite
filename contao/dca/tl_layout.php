<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addField('developmentMode', 'combineScripts', PaletteManipulator::POSITION_AFTER)
    ->applyToPalette('default', 'tl_layout')
;

$GLOBALS['TL_DCA']['tl_layout']['fields']['developmentMode'] = [
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'boolean', 'default' => false],
];
