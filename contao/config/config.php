<?php

declare(strict_types=1);

use Contao\ArrayUtil;

$GLOBALS['BE_MOD']['design'] ??= [];

ArrayUtil::arrayInsert($GLOBALS['BE_MOD']['design'], \count($GLOBALS['BE_MOD']['design']), [
    'style_selector' => [
        'tables' => ['tl_style_selector'],
    ],
]);
