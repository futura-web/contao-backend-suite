<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeProjectConfig;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Löst für ein Theme den zugehörigen Projekt-Ordner unter "files/public/"
 * auf - über die Spalte "templates" (z.B. "templates/oase"), genau wie
 * ColorImporter das für _vars_colors.scss bereits macht.
 */
class ProjectScssPaths
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function variablesDir(int $themeId): string
    {
        return $this->includeDir($themeId).'/variables';
    }

    public function includeDir(int $themeId): string
    {
        return $this->projectDir.'/files/public/'.$this->projectSlug($themeId).'/theme/css/include';
    }

    private function projectSlug(int $themeId): string
    {
        $templates = $this->connection->fetchOne('SELECT templates FROM tl_theme WHERE id = ?', [$themeId]);

        return basename((string) (false !== $templates && '' !== $templates ? $templates : 'project'));
    }
}
