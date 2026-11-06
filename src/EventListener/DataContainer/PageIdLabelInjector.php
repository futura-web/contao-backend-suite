<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Futuraweb\ContaoBackendSuite\UserFeatures\UserFeatureRegistry;

/**
 * Shows each page's ID next to its title in the page tree while the Shift
 * key is held, so you can spot the right record (e.g. to match it against
 * a URL or a database query) without opening it or checking the status bar
 * link, and without cluttering the tree the rest of the time.
 */
#[AsHook('loadDataContainer')]
class PageIdLabelInjector
{
    public function __construct(private readonly UserFeatureRegistry $features)
    {
    }

    public function __invoke(string $table): void
    {
        if ('tl_page' !== $table) {
            return;
        }

        if (!$this->features->isEnabledForCurrentUser(UserFeatureRegistry::PAGE_ID_ALIAS)) {
            return;
        }

        $labelConfig = &$GLOBALS['TL_DCA']['tl_page']['list']['label'];

        if (!\in_array('id', $labelConfig['fields'] ?? [], true)) {
            $labelConfig['fields'][] = 'id';
        }

        $labelConfig['format'] = '%s <span class="backend-suite-page-id">[ID: %s]</span>';

        $GLOBALS['TL_CSS'][] = 'bundles/contaobackendsuite/backend-id.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaobackendsuite/backend-id.js';
    }
}
