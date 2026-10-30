<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\StringUtil;
use Futuraweb\ContaoBackendSuite\Upload\ThemeUploadSettings;

/**
 * Adds the "shrink + rename images on upload" toggle button to the file
 * manager's global operations.
 */
#[AsHook('loadDataContainer')]
class UploadToggleButtonInjector
{
    public function __construct(private readonly ThemeUploadSettings $settings)
    {
    }

    public function __invoke(string $table): void
    {
        if ('tl_files' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_files'];

        if (isset($dca['list']['global_operations']['backendSuiteToggleShrink'])) {
            return;
        }

        $dca['list']['global_operations']['backendSuiteToggleShrink'] = [
            'href' => 'backendSuiteToggleShrink=1',
            'class' => 'header_backend_suite_shrink',
            'button_callback' => [$this, 'renderButton'],
        ];
    }

    public function renderButton(string $href, string $label, string $title, string $class, string $attributes): string
    {
        $enabled = $this->settings->isEnabledForCurrentTheme();

        $label = $enabled
            ? ($GLOBALS['TL_LANG']['tl_files']['backendSuiteShrinkOn'] ?? 'Bilder-Verkleinern: An')
            : ($GLOBALS['TL_LANG']['tl_files']['backendSuiteShrinkOff'] ?? 'Bilder-Verkleinern: Aus');

        $class .= $enabled ? ' backend-suite-shrink-on' : ' backend-suite-shrink-off';

        return '<a href="'.Backend::addToUrl($href).'" title="'.StringUtil::specialchars($label).'" class="'.$class.'"'.$attributes.'>'.StringUtil::specialchars($label).'</a> ';
    }
}
