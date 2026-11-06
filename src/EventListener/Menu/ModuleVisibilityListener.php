<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\Menu;

use Contao\CoreBundle\Event\MenuEvent;
use Futuraweb\ContaoBackendSuite\UserFeatures\UserFeatureRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Blendet Backend-Modul-Menüeinträge aus, deren zugehörige Erweiterung der
 * aktuelle Nutzer in "Mein Profil" / im Benutzer-Formular nicht aktiviert
 * hat (siehe UserFeatureRegistry).
 *
 * Modularis gehört technisch zum separaten Bundle
 * futuraweb/contao-modularis-builder, nicht zu diesem Bundle - geht aber
 * trotzdem von hier aus, weil diese Suite bereits von Modularis abhängt
 * (nicht umgekehrt) und daher gefahrlos dessen Modul-Namen kennen darf.
 *
 * Niedrige Priorität, damit Contao-Cores eigener BackendMainListener
 * (Standard-Priorität 10), der diese Menüeinträge überhaupt erst aus
 * BE_MOD aufbaut, in jedem Fall vorher gelaufen ist.
 */
#[AsEventListener(priority: -100)]
class ModuleVisibilityListener
{
    /**
     * design-Kategorie-Modul => Feature-Key.
     */
    private const MODULES = [
        'style_selector' => UserFeatureRegistry::STYLE_SELECTOR,
        'dynamic_field' => UserFeatureRegistry::DYNAMIC_FIELD_BUILDER,
        'modularis_templates' => UserFeatureRegistry::MODULARIS,
    ];

    public function __construct(private readonly UserFeatureRegistry $features)
    {
    }

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        $category = $tree->getChild('design');

        if (!$category) {
            return;
        }

        foreach (self::MODULES as $module => $feature) {
            $item = $category->getChild($module);

            if ($item && !$this->features->isEnabledForCurrentUser($feature)) {
                $category->removeChild($module);
            }
        }
    }
}
