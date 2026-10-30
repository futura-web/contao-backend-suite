<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\Menu;

use Contao\Controller;
use Contao\CoreBundle\Event\MenuEvent;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\Toolbar\CurrentThemeTracker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Adds theme quicklinks (Module, Seitenlayouts, Bildgrößen) for the
 * currently detected theme to the backend header menu, plus a switcher
 * to change the current theme when there's more than one.
 *
 * This replaces OM-Backend's approach of stacking those buttons for every
 * theme at once: here only the current theme's buttons are shown.
 */
#[AsEventListener]
class HeaderToolbarMenuListener
{
    private const QUICKLINKS = [
        'tl_module' => 'backendSuiteQuicklinkModules',
        'tl_layout' => 'backendSuiteQuicklinkLayouts',
        'tl_image_size' => 'backendSuiteQuicklinkImageSizes',
    ];

    public function __construct(
        private readonly CurrentThemeTracker $tracker,
        private readonly Connection $connection,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(MenuEvent $event): void
    {
        if ('headerMenu' !== $event->getTree()->getName()) {
            return;
        }

        $themes = $this->connection->fetchAllKeyValue('SELECT id, name FROM tl_theme ORDER BY name');

        if ([] === $themes) {
            return;
        }

        // The "tl_theme" language file isn't loaded by default on pages
        // that have nothing to do with themes (e.g. the dashboard), but
        // the header menu is rendered on every backend page.
        Controller::loadLanguageFile('tl_theme');

        $factory = $event->getFactory();
        $tree = $event->getTree();

        $currentThemeId = $this->tracker->getCurrentThemeId() ?? array_key_first($themes);

        foreach (self::QUICKLINKS as $table => $labelKey) {
            $tree->addChild(
                $factory->createItem($labelKey)
                    ->setUri($this->router->generate('contao_backend', [
                        'do' => 'themes',
                        'table' => $table,
                        'id' => $currentThemeId,
                    ]))
                    ->setLabel($GLOBALS['TL_LANG']['tl_theme']['backendSuite'][$labelKey] ?? $labelKey)
                    ->setLinkAttribute('class', 'icon-'.$table)
                    ->setLinkAttribute('title', $GLOBALS['TL_LANG']['tl_theme']['backendSuite'][$labelKey] ?? $labelKey)
                    ->setAttribute('class', 'backend-suite-header-icon')
                    ->setExtra('translation_domain', false),
            );
        }

        // Eigenes CSS, da Contao fuer diese (von uns hinzugefuegten) Klassen
        // keine Icon-Darstellung mitbringt - ohne das Stylesheet erscheinen
        // die Links als reiner Text statt als Icon-Buttons vor "Handbuch".
        $GLOBALS['TL_CSS'][] = 'bundles/contaobackendsuite/backend/headerToolbar.css';

        if (\count($themes) < 2) {
            return;
        }

        $switcher = $factory->createItem('backendSuiteThemeSwitcher')
            ->setUri('#')
            ->setLabel($themes[$currentThemeId] ?? (string) $currentThemeId)
            ->setLinkAttribute('class', 'icon-tl_theme')
            ->setLinkAttribute('title', $GLOBALS['TL_LANG']['tl_theme']['backendSuite']['switchProject'] ?? 'Projekt wechseln')
            ->setExtra('translation_domain', false);

        $currentParams = $this->requestStack->getCurrentRequest()?->query->all() ?? [];

        foreach ($themes as $id => $name) {
            $switcher->addChild(
                $factory->createItem('theme_'.$id)
                    ->setUri($this->router->generate('contao_backend', [...$currentParams, 'backendSuiteTheme' => $id]))
                    ->setLabel($name)
                    ->setCurrent($id === $currentThemeId)
                    ->setExtra('translation_domain', false),
            );
        }

        $tree->addChild($switcher);
    }
}
