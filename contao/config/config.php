<?php

declare(strict_types=1);

use Contao\ArrayUtil;

$GLOBALS['BE_MOD']['design'] ??= [];

ArrayUtil::arrayInsert($GLOBALS['BE_MOD']['design'], \count($GLOBALS['BE_MOD']['design']), [
    'style_selector' => [
        'tables' => ['tl_style_selector'],
    ],
    'dynamic_field' => [
        'tables' => ['tl_dynamic_field'],
    ],
    // Die Aktionen "runImport" und "createPages" stehen bewusst NICHT als
    // BE_MOD-Callbacks hier: so faellt Contao bei diesen Keys auf die
    // normale Tabellenansicht zurueck, deren onload_callback mit echter
    // Dependency Injection laeuft (siehe RedirectImportActionListener).
    'redirect_wizard' => [
        'tables' => ['tl_bs_redirect_import', 'tl_bs_redirect'],
    ],
]);
