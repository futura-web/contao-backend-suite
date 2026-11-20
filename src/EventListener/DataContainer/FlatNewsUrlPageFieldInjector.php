<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * Injects the "backendSuiteFlatUrl" checkbox into a regular page's edit
 * form, right next to its alias. Any news/calendar/FAQ item read through a
 * page flagged this way has that page's alias stripped from its URL
 * sitewide (see FlatNewsUrlResponseListener/FlatNewsUrlSitemapListener) -
 * purely a reader-page property, no central prefix config needed.
 */
#[AsHook('loadDataContainer')]
class FlatNewsUrlPageFieldInjector
{
    private const FIELD = 'backendSuiteFlatUrl';

    public function __invoke(string $table): void
    {
        if ('tl_page' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_page'];

        if (isset($dca['fields'][self::FIELD])) {
            return;
        }

        Controller::loadLanguageFile('tl_page');

        $dca['fields'][self::FIELD] = [
            'label' => &$GLOBALS['TL_LANG']['tl_page']['backendSuite'][self::FIELD],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 clr'],
            'sql' => "char(1) NOT NULL default ''",
        ];

        $palette = $dca['palettes']['regular'] ?? null;

        if (\is_string($palette) && !str_contains($palette, self::FIELD) && str_contains($palette, ',alias,')) {
            $dca['palettes']['regular'] = str_replace(',alias,', ',alias,'.self::FIELD.',', $palette);
        }
    }
}
