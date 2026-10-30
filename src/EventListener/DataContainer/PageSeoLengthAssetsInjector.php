<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * Adds a live character counter below the pageTitle and description fields,
 * color-coded against the length Google typically shows before truncating
 * a search result title or snippet.
 */
#[AsHook('loadDataContainer')]
class PageSeoLengthAssetsInjector
{
    public function __invoke(string $table): void
    {
        if ('tl_page' !== $table) {
            return;
        }

        $GLOBALS['TL_CSS'][] = 'bundles/contaobackendsuite/seo-length.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaobackendsuite/seo-length.js';
    }
}
