<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * Shows each page's ID next to its title in the page tree, so you can spot
 * the right record (e.g. to match it against a URL or a database query)
 * without opening it or checking the status bar link.
 */
#[AsHook('loadDataContainer')]
class PageIdLabelInjector
{
    public function __invoke(string $table): void
    {
        if ('tl_page' !== $table) {
            return;
        }

        $labelConfig = &$GLOBALS['TL_DCA']['tl_page']['list']['label'];

        if (!\in_array('id', $labelConfig['fields'] ?? [], true)) {
            $labelConfig['fields'][] = 'id';
        }

        $labelConfig['format'] = '%s <span style="color:#999999;padding-left:3px;font-weight:normal;">[ID: %s]</span>';
    }
}
