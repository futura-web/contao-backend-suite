<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\Menu;

use Contao\Controller;
use Contao\CoreBundle\Event\MenuEvent;
use Doctrine\DBAL\Connection;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\RouterInterface;

/**
 * Adds theme quicklinks (Module, Seitenlayouts, Bildgrößen) to the backend
 * header menu.
 *
 * With a single theme, each quicklink is one direct link. With more than
 * one theme, each quicklink instead becomes a hover menu listing every
 * theme, and under each theme its individual records (layouts, image
 * sizes) — or, for modules, just the one link to the module list, since a
 * theme can have many modules but normally only a handful of layouts or
 * image sizes worth linking to directly.
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
        private readonly Connection $connection,
        private readonly RouterInterface $router,
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

        foreach (self::QUICKLINKS as $table => $labelKey) {
            $tree->addChild(
                \count($themes) < 2
                    ? $this->buildFlatLink($factory, $table, $labelKey, array_key_first($themes))
                    : $this->buildNestedMenu($factory, $table, $labelKey, $themes),
            );
        }

        // Eigenes CSS, da Contao fuer diese (von uns hinzugefuegten) Klassen
        // keine Icon-Darstellung mitbringt - ohne das Stylesheet erscheinen
        // die Links als reiner Text statt als Icon-Buttons vor "Handbuch".
        $GLOBALS['TL_CSS'][] = 'bundles/contaobackendsuite/backend/headerToolbar.css';
    }

    private function buildFlatLink(FactoryInterface $factory, string $table, string $labelKey, int|string $themeId): ItemInterface
    {
        return $factory->createItem($labelKey)
            ->setUri($this->router->generate('contao_backend', $this->resolveQuicklinkParams($table, $themeId)))
            ->setLabel($this->label($labelKey))
            ->setLinkAttribute('class', 'icon-'.$table)
            ->setLinkAttribute('title', $this->label($labelKey))
            ->setAttribute('class', 'backend-suite-header-icon')
            ->setExtra('translation_domain', false);
    }

    /**
     * @param array<int|string, string> $themes
     */
    private function buildNestedMenu(FactoryInterface $factory, string $table, string $labelKey, array $themes): ItemInterface
    {
        $item = $factory->createItem($labelKey)
            ->setUri('#')
            ->setLabel($this->label($labelKey))
            ->setLinkAttribute('class', 'icon-'.$table)
            ->setLinkAttribute('title', $this->label($labelKey))
            ->setAttribute('class', 'backend-suite-header-icon')
            ->setExtra('translation_domain', false);

        foreach ($themes as $themeId => $themeName) {
            $projectItem = $factory->createItem('backendSuiteProject_'.$table.'_'.$themeId)
                ->setUri('#')
                ->setLabel($themeName)
                ->setExtra('translation_domain', false);

            foreach ($this->getChildLinks($factory, $table, $labelKey, $themeId) as $childItem) {
                $projectItem->addChild($childItem);
            }

            $item->addChild($projectItem);
        }

        return $item;
    }

    /**
     * @return list<ItemInterface>
     */
    private function getChildLinks(FactoryInterface $factory, string $table, string $labelKey, int|string $themeId): array
    {
        // Modules: a theme can have many, so just link to the list instead
        // of flooding the menu with every single one.
        if ('tl_module' === $table) {
            return [
                $factory->createItem('backendSuiteProjectLink_'.$table.'_'.$themeId)
                    ->setUri($this->router->generate('contao_backend', ['do' => 'themes', 'table' => $table, 'id' => $themeId]))
                    ->setLabel($this->label($labelKey))
                    ->setExtra('translation_domain', false),
            ];
        }

        $records = $this->connection->fetchAllKeyValue(
            "SELECT id, name FROM {$table} WHERE pid = ? ORDER BY name",
            [$themeId],
        );

        if ([] === $records) {
            return [
                $factory->createItem('backendSuiteProjectLink_'.$table.'_'.$themeId)
                    ->setUri($this->router->generate('contao_backend', ['do' => 'themes', 'table' => $table, 'id' => $themeId]))
                    ->setLabel($this->label($labelKey))
                    ->setExtra('translation_domain', false),
            ];
        }

        $items = [];

        foreach ($records as $recordId => $recordName) {
            $items[] = $factory->createItem('backendSuiteRecord_'.$table.'_'.$recordId)
                ->setUri($this->router->generate('contao_backend', ['do' => 'themes', 'table' => $table, 'id' => $recordId, 'act' => 'edit']))
                ->setLabel($recordName)
                ->setExtra('translation_domain', false);
        }

        return $items;
    }

    private function label(string $labelKey): string
    {
        return $GLOBALS['TL_LANG']['tl_theme']['backendSuite'][$labelKey] ?? $labelKey;
    }

    /**
     * Für "Seitenlayouts": gibt es fuer das aktuelle Theme genau ein
     * Layout, direkt dorthin verlinken statt zur (dann unnoetigen)
     * Listenansicht - bei keinem oder mehreren Layouts bleibt es bei der
     * Uebersicht.
     *
     * @return array<string, int|string>
     */
    private function resolveQuicklinkParams(string $table, int|string $themeId): array
    {
        $listParams = ['do' => 'themes', 'table' => $table, 'id' => $themeId];

        if ('tl_layout' !== $table) {
            return $listParams;
        }

        $layoutIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_layout WHERE pid = ?',
            [$themeId],
        );

        if (1 !== \count($layoutIds)) {
            return $listParams;
        }

        return ['do' => 'themes', 'table' => $table, 'id' => $layoutIds[0], 'act' => 'edit'];
    }
}
