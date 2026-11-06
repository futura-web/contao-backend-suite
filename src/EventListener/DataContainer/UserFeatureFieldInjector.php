<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Futuraweb\ContaoBackendSuite\UserFeatures\UserFeatureRegistry;

/**
 * Fügt das Feld "Backend-Suite-Erweiterungen" zum Benutzer-Formular hinzu -
 * sowohl im "Benutzer"-Backend-Modul (ein Admin stellt es für einen anderen
 * Nutzer ein) als auch in "Mein Profil" (jeder Nutzer stellt es für sich
 * selbst ein).
 *
 * tl_user nutzt Contaos Subpaletten-Mechanismus (__selector__ = inherit,
 * admin): je nach Nutzer ist in Wahrheit eine von "default", "group",
 * "extend", "custom" oder "admin" aktiv, nie zuverlässig "default" allein -
 * das Feld muss deshalb in alle eingehängt werden, sonst taucht es bei den
 * meisten echten Nutzern gar nicht auf. "Mein Profil" ist nochmal ein
 * Sonderfall: tl_user::handleUserProfile() (Contao-Core) ersetzt dort die
 * komplette Paletten-Liste durch nur "login" - nur wenn unser Feld auch in
 * dieser Palette steckt, erscheint es im eigenen Profil.
 */
#[AsHook('loadDataContainer')]
class UserFeatureFieldInjector
{
    private const FIELD = 'backendSuiteFeatures';
    private const LEGEND = 'backend_suite_features_legend';

    /**
     * Palette => Legende, nach der unsere eigene eingehängt wird. Je nach
     * Palette heißt die letzte sinnvolle Anker-Legende anders (siehe
     * Contao-Core contao/dca/tl_user.php).
     */
    private const ANCHORS = [
        'login' => 'theme_legend',
        'admin' => 'admin_legend',
        'default' => 'groups_legend',
        'group' => 'groups_legend',
        'extend' => 'groups_legend',
        'custom' => 'groups_legend',
    ];

    public function __construct(private readonly UserFeatureRegistry $registry)
    {
    }

    public function __invoke(string $table): void
    {
        if ('tl_user' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_user'];

        if (isset($dca['fields'][self::FIELD])) {
            return;
        }

        $dca['fields'][self::FIELD] = [
            'label' => ['Backend-Suite-Erweiterungen', 'Nur die angekreuzten Erweiterungen sind sichtbar. Nichts angekreuzt: alle Erweiterungen sind sichtbar (Standard). Admins sehen immer alles.'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'options' => $this->registry->getOptions(),
            'eval' => ['multiple' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ];

        $GLOBALS['TL_LANG']['tl_user'][self::LEGEND] = 'Backend-Suite-Erweiterungen';

        foreach (self::ANCHORS as $palette => $anchor) {
            if (!isset($dca['palettes'][$palette]) || !\is_string($dca['palettes'][$palette])) {
                continue;
            }

            if (!str_contains($dca['palettes'][$palette], '{'.$anchor)) {
                continue;
            }

            PaletteManipulator::create()
                ->addLegend(self::LEGEND, $anchor, PaletteManipulator::POSITION_AFTER)
                ->addField([self::FIELD], self::LEGEND, PaletteManipulator::POSITION_APPEND)
                ->applyToPalette($palette, 'tl_user')
            ;
        }
    }
}
