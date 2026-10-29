<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Toolbar;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Remembers which theme ("project") the editor is currently working on, so
 * the toolbar can show quicklinks for the right one without asking.
 *
 * Detection is automatic: editing a record that belongs to a theme (a
 * layout, a module, an image size, or the theme itself) updates the
 * remembered theme for the rest of the backend session. A manual switcher
 * is offered as a fallback when there's more than one theme and nothing
 * obvious to detect from (e.g. on the dashboard).
 */
class CurrentThemeTracker
{
    private const SESSION_KEY = 'backend_suite_current_theme';

    private const THEME_OWNED_TABLES = [
        'tl_theme' => null,
        'tl_layout' => 'pid',
        'tl_module' => 'pid',
        'tl_image_size' => 'pid',
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
    ) {
    }

    public function detectFromRequest(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $table = $request->query->get('table');
        $id = $request->query->get('id') ?? $request->query->get('pid');

        if (null === $table || null === $id || !\array_key_exists($table, self::THEME_OWNED_TABLES)) {
            if ($request->query->has('backendSuiteTheme')) {
                $this->remember((int) $request->query->get('backendSuiteTheme'));
            }

            return;
        }

        $themeId = 'tl_theme' === $table
            ? (int) $id
            : $this->resolveParentTheme($table, (int) $id);

        if (null !== $themeId) {
            $this->remember($themeId);
        }
    }

    public function getCurrentThemeId(): int|null
    {
        $id = $this->getSession()?->get(self::SESSION_KEY);

        if (null === $id) {
            return null;
        }

        $exists = $this->connection->fetchOne('SELECT id FROM tl_theme WHERE id = ?', [$id]);

        return false !== $exists ? (int) $id : null;
    }

    private function resolveParentTheme(string $table, int $id): int|null
    {
        $pidField = self::THEME_OWNED_TABLES[$table];
        $pid = $this->connection->fetchOne("SELECT {$pidField} FROM {$table} WHERE id = ?", [$id]);

        return false !== $pid ? (int) $pid : null;
    }

    private function remember(int $themeId): void
    {
        $this->getSession()?->set(self::SESSION_KEY, $themeId);
    }

    private function getSession(): SessionInterface|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
