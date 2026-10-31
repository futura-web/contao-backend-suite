<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Futuraweb\ContaoBackendSuite\Upload\FileNameSanitizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cleans up folder names the same way the image shrinker cleans up
 * uploaded filenames (lowercase, umlauts transliterated, spaces replaced)
 * — but only for folders, never for files, since renaming an existing
 * file is the editor's own choice.
 *
 * Hooks into tl_files' existing "name" field save_callback chain (used
 * for both file renames and the "new folder" flow, which creates a
 * placeholder "__new__" folder and then renames it via this same field)
 * rather than replacing it, so core's own filename validation still runs.
 */
#[AsHook('loadDataContainer')]
class FolderNameSanitizerFieldInjector
{
    public function __construct(
        private readonly FileNameSanitizer $sanitizer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ('tl_files' !== $table) {
            return;
        }

        $callbacks = &$GLOBALS['TL_DCA']['tl_files']['fields']['name']['save_callback'];
        $callbacks ??= [];

        array_unshift($callbacks, function (string $value, DataContainer $dc): string {
            return $this->sanitizeIfFolder($value, $dc);
        });
    }

    private function sanitizeIfFolder(string $value, DataContainer $dc): string
    {
        $path = (string) ($dc->activeRecord->path ?? '');

        if ('' === $path || !is_dir($this->projectDir.'/'.$path)) {
            return $value;
        }

        return $this->sanitizer->sanitizeFolderName($value);
    }
}
