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
 */
#[AsHook('loadDataContainer')]
class PageRootLanguageLabelInjector
{
    public function __invoke(string $table): void
    {
        if ('tl_page' !== $table) {
            return;
        }

        $labelConfig = &$GLOBALS['TL_DCA']['tl_page']['list']['label'];
        $original = $labelConfig['label_callback'] ?? null;

        if (!\is_array($original)) {
            return;
        }

        $labelConfig['label_callback'] = static function (array $row, string $label, DataContainer|null $dc = null, string $imageAttribute = '', bool $blnReturnImage = false, bool $blnProtected = false, bool $isVisibleRootTrailPage = false) use ($original): string {
            $buffer = System::importStatic($original[0])->{$original[1]}($row, $label, $dc, $imageAttribute, $blnReturnImage, $blnProtected, $isVisibleRootTrailPage);

            if ($blnReturnImage || 'root' !== $row['type'] || '' === (string) ($row['language'] ?? '')) {
                return $buffer;
            }

            return $buffer.' <span class="tl_gray">['.StringUtil::specialchars($row['language']).']</span>';
        };
    }
}
