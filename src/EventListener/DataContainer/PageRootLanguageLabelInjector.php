<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\StringUtil;
use Contao\System;

/**
 * Shows the language code next to root pages ("Startseiten") in the page
 * tree, so you don't have to open each one to see which language it's for.
 *
 * Runs with a low priority so it wraps whatever is currently set as the
 * label_callback (core's default, or another extension's own wrapper)
 * instead of assuming it's still the untouched core array callback.
 */
#[AsHook('loadDataContainer', priority: -100)]
class PageRootLanguageLabelInjector
{
    public function __invoke(string $table): void
    {
        if ('tl_page' !== $table) {
            return;
        }

        $labelConfig = &$GLOBALS['TL_DCA']['tl_page']['list']['label'];
        $original = $labelConfig['label_callback'] ?? null;

        if (null === $original || !(\is_array($original) || \is_callable($original))) {
            return;
        }

        $labelConfig['label_callback'] = static function (array $row, string $label, DataContainer|null $dc = null, string $imageAttribute = '', bool $blnReturnImage = false, bool $blnProtected = false, bool $isVisibleRootTrailPage = false) use ($original): string {
            $buffer = \is_array($original)
                ? System::importStatic($original[0])->{$original[1]}($row, $label, $dc, $imageAttribute, $blnReturnImage, $blnProtected, $isVisibleRootTrailPage)
                : $original($row, $label, $dc, $imageAttribute, $blnReturnImage, $blnProtected, $isVisibleRootTrailPage);

            if ($blnReturnImage) {
                return $buffer;
            }

            // TEMP DEBUG: unconditional marker to confirm the hook fires at all.
            return $buffer.' <span class="tl_gray">[DEBUGMARK]</span>';
        };
    }
}
