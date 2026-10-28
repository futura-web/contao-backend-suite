<?php

declare(strict_types=1);

namespace Futura\ContaoBackendSuite\EventListener;

use Contao\ArrayUtil;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * Registers the "style_selector" back end module in the "design" group.
 *
 * Done via the initializeSystem hook (fires after every bundle's
 * contao/config/config.php has been loaded) instead of directly in our own
 * config.php: registering straight in config.php silently failed on some
 * environments because $GLOBALS['BE_MOD']['design'] is only guaranteed to
 * exist once Contao core's own config.php has run, and config.php load
 * order between bundles isn't guaranteed.
 */
#[AsHook('initializeSystem')]
class BackendModuleRegistrationListener
{
    public function __invoke(): void
    {
        if (isset($GLOBALS['BE_MOD']['design']['style_selector'])) {
            return;
        }

        $GLOBALS['BE_MOD']['design'] ??= [];

        ArrayUtil::arrayInsert($GLOBALS['BE_MOD']['design'], \count($GLOBALS['BE_MOD']['design']), [
            'style_selector' => [
                'tables' => ['tl_style_selector'],
            ],
        ]);
    }
}
