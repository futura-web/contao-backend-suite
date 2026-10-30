<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Upload;

use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\Toolbar\CurrentThemeTracker;

/**
 * Whether "shrink + rename images on upload" is enabled, stored per theme
 * ("project") so different projects can have different defaults.
 */
class ThemeUploadSettings
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentThemeTracker $tracker,
    ) {
    }

    public function isEnabledForCurrentTheme(): bool
    {
        $themeId = $this->resolveCurrentThemeId();

        if (null === $themeId) {
            return false;
        }

        return (bool) $this->connection->fetchOne(
            'SELECT backendSuiteShrinkOnUpload FROM tl_theme WHERE id = ?',
            [$themeId],
        );
    }

    public function toggleForCurrentTheme(): void
    {
        $themeId = $this->resolveCurrentThemeId();

        if (null === $themeId) {
            return;
        }

        $current = (bool) $this->connection->fetchOne(
            'SELECT backendSuiteShrinkOnUpload FROM tl_theme WHERE id = ?',
            [$themeId],
        );

        $this->connection->update(
            'tl_theme',
            ['backendSuiteShrinkOnUpload' => $current ? 0 : 1],
            ['id' => $themeId],
        );
    }

    public function isEnabledForTheme(int $themeId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT backendSuiteShrinkOnUpload FROM tl_theme WHERE id = ?',
            [$themeId],
        );
    }

    /**
     * Falls back to the first theme when none has been detected yet (e.g.
     * the editor went straight to the file manager without touching a
     * theme-owned record first), matching the toolbar's fallback behaviour.
     */
    private function resolveCurrentThemeId(): int|null
    {
        $themeId = $this->tracker->getCurrentThemeId();

        if (null !== $themeId) {
            return $themeId;
        }

        $firstThemeId = $this->connection->fetchOne('SELECT id FROM tl_theme ORDER BY name LIMIT 1');

        return false !== $firstThemeId ? (int) $firstThemeId : null;
    }
}
