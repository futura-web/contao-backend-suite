<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Dbafs;
use Futuraweb\ContaoBackendSuite\Upload\FileNameSanitizer;
use Futuraweb\ContaoBackendSuite\Upload\ImageShrinker;
use Futuraweb\ContaoBackendSuite\Upload\ThemeUploadSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Shrinks oversized images and cleans up their file names right after
 * upload, if enabled for the current theme ("project").
 */
#[AsHook('postUpload')]
class UploadShrinkListener
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly ThemeUploadSettings $settings,
        private readonly ImageShrinker $shrinker,
        private readonly FileNameSanitizer $sanitizer,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param list<string> $uploaded relative paths (from the project root) of the uploaded files
     */
    public function __invoke(array $uploaded): void
    {
        if (!$this->settings->isEnabledForCurrentTheme()) {
            return;
        }

        foreach ($uploaded as $relativePath) {
            $this->processFile($relativePath);
        }
    }

    private function processFile(string $relativePath): void
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if (!\in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return;
        }

        $absolutePath = $this->projectDir.'/'.$relativePath;

        if (!is_file($absolutePath)) {
            return;
        }

        // GIFs may be animated; resizing would only keep the first frame.
        if ('gif' !== $extension) {
            try {
                $this->shrinker->shrink($absolutePath);
            } catch (\Throwable) {
                // Not a processable image (e.g. corrupt upload) - still try to rename it.
            }
        }

        $directory = \dirname($relativePath);
        $fileName = basename($relativePath);
        $sanitizedName = $this->sanitizer->sanitize($fileName);

        if ($sanitizedName === $fileName) {
            return;
        }

        $newRelativePath = $directory.'/'.$this->findAvailableName($directory, $sanitizedName);
        $newAbsolutePath = $this->projectDir.'/'.$newRelativePath;

        $this->filesystem->rename($absolutePath, $newAbsolutePath);
        Dbafs::moveResource($relativePath, $newRelativePath);
    }

    private function findAvailableName(string $directory, string $fileName): string
    {
        $absoluteDirectory = $this->projectDir.'/'.$directory;
        $pathInfo = pathinfo($fileName);
        $base = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';

        $candidate = $fileName;
        $i = 1;

        while (is_file($absoluteDirectory.'/'.$candidate)) {
            $candidate = $base.'_'.$i.$extension;
            ++$i;
        }

        return $candidate;
    }
}
