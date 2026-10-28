<?php

declare(strict_types=1);

use Contao\ArrayUtil;

ArrayUtil::arrayInsert($GLOBALS['BE_MOD']['design'], \count($GLOBALS['BE_MOD']['design']), [
    'style_selector' => [
        'tables' => ['tl_style_selector'],
    ],
]);
