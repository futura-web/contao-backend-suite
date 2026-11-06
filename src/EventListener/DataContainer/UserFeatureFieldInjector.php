<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Futuraweb\ContaoBackendSuite\UserFeatures\UserFeatureRegistry;

/**
 * Fügt das Feld "Backend-Suite-Erweiterungen" zum Benutzer-Formular hinzu,
 * über das ein Admin pro Nutzer auswählt, welche der optionalen Extras der
 * Suite für diesen Nutzer sichtbar sind - gedacht für Redakteure, die nur
 * einen Teil der Erweiterungen tatsächlich brauchen.
 *
 * Nur im "Benutzer"-Backend-Modul sichtbar (volle tl_user-Palette), nicht
 * in "Mein Profil": dessen Formular verwendet ein eigenes, fest
 * verdrahtetes Feld-Set statt der hier erweiterten Standard-Palette.
 */
#[AsHook('loadDataContainer')]
class UserFeatureFieldInjector
{
    private const FIELD = 'backendSuiteFeatures';
    private const LEGEND = 'backend_suite_features_legend';

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
            'label' => ['Backend-Suite-Erweiterungen', 'Nur die angekreuzten Erweiterungen sind für diesen Nutzer sichtbar. Nichts angekreuzt: alle Erweiterungen sind sichtbar (Standard). Admins sehen immer alles.'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'options' => $this->registry->getOptions(),
            'eval' => ['multiple' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ];

        $GLOBALS['TL_LANG']['tl_user'][self::LEGEND] = 'Backend-Suite-Erweiterungen';

        PaletteManipulator::create()
            ->addLegend(self::LEGEND, 'groups_legend', PaletteManipulator::POSITION_AFTER)
            ->addField([self::FIELD], self::LEGEND, PaletteManipulator::POSITION_APPEND)
            ->applyToPalette('default', 'tl_user')
        ;
    }
}
