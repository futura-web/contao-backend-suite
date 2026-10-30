<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\Hook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\LayoutModel;
use Contao\PageModel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Löscht den Combiner-Asset-Cache (assets/css, assets/js) vor jedem Seitenaufbau,
 * wenn im aktuellen Layout der Entwicklungsmodus aktiv ist.
 *
 * Contaos Combiner cached kompiliertes CSS/JS anhand des filemtime() der
 * eingebundenen Top-Level-Datei (z. B. base.scss) – Änderungen an per @import
 * eingebundenen Partials ändern diesen Zeitstempel nicht und bleiben dadurch
 * unbemerkt. Das Leeren von assets/css vor dem Request zwingt Contao, bei jedem
 * Aufruf neu zu kompilieren.
 */
#[AsHook('replaceDynamicScriptTags')]
class DevelopmentModeListener
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function __invoke(string $buffer): string
    {
        global $objPage;

        $layout = $objPage instanceof PageModel ? LayoutModel::findById($objPage->layoutId) : null;

        if (!$layout || !$layout->developmentMode) {
            return $buffer;
        }

        $projectDir = $this->kernel->getProjectDir();

        foreach (['assets/css', 'assets/js'] as $relativeDir) {
            $dir = $projectDir.'/'.$relativeDir;

            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir.'/*') ?: [] as $path) {
                if (is_file($path) || is_link($path)) {
                    $this->filesystem->remove($path);
                }
            }
        }

        return $buffer;
    }
}
