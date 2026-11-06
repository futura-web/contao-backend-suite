<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\UserFeatures;

use Contao\BackendUser;
use Contao\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Welche der optionalen Backend-Suite-Erweiterungen für den aktuell
 * angemeldeten Redakteur sichtbar sind, steuerbar pro Benutzer über das
 * Feld "backendSuiteFeatures" (siehe UserFeatureFieldInjector).
 *
 * Nur die auffälligen, UI-erweiternden Extras stehen hier zur Wahl - stille
 * Hintergrundmechanik (Ordner-Namensbereinigung, Feld-Builder-Felder,
 * der bereits pro Theme umschaltbare Bild-Shrinker) wird bewusst nicht
 * aufgenommen, da sie kein "Erweiterung ein-/ausblenden"-Bedürfnis bedient.
 *
 * Modularis-Element fehlt hier ebenfalls: das lebt im separaten Bundle
 * futuraweb/contao-modularis-builder, das umgekehrt von dieser Suite
 * abhängt - ein Zugriff von dort auf dieses Bundle würde eine zirkuläre
 * Abhängigkeit erzeugen.
 */
final class UserFeatureRegistry
{
    public function __construct(private readonly Security $security)
    {
    }

    public const STYLE_SELECTOR = 'style_selector';
    public const TOOLBAR_QUICKLINKS = 'toolbar_quicklinks';
    public const PAGE_ROOT_LANGUAGE = 'page_root_language';
    public const PAGE_ID_ALIAS = 'page_id_alias';
    public const SEO_LENGTH = 'seo_length';
    public const PROJECT_CONFIG = 'project_config';
    public const JS_FUNCTIONS = 'js_functions';
    public const SCSS_FEATURES = 'scss_features';
    public const FONT_COLOR_PICKER = 'font_color_picker';

    private const LABELS = [
        self::STYLE_SELECTOR => 'Style-Selector (CSS-Klassen-Auswahl)',
        self::TOOLBAR_QUICKLINKS => 'Toolbar-Quicklinks',
        self::PAGE_ROOT_LANGUAGE => 'Sprache bei Startpunkten',
        self::PAGE_ID_ALIAS => 'ID-/Alias-Anzeige im Seitenbaum',
        self::SEO_LENGTH => 'Textfeld-Längenanzeige (SEO)',
        self::PROJECT_CONFIG => 'Projekt-Theme-Konfiguration',
        self::JS_FUNCTIONS => 'JavaScript-Funktionen (Layout)',
        self::SCSS_FEATURES => 'SCSS-Features (Layout)',
        self::FONT_COLOR_PICKER => 'Schriftarten-/Farben-Picker',
    ];

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return self::LABELS;
    }

    /**
     * Jeder Nutzer entscheidet selbst, auch Admins - keine automatische
     * Ausnahme mehr. Default ist "versteckt": nichts angekreuzt (auch bei
     * allen Bestandsnutzern, inklusive Admins, vor Einführung dieses Felds)
     * bedeutet, keine der Erweiterungen ist sichtbar, bis der Nutzer
     * gezielt welche in "Mein Profil" ankreuzt.
     *
     * Außerhalb eines echten Backend-Requests (kein angemeldeter Nutzer,
     * z. B. contao:migrate/cache:warmup) liefert dies dagegen weiterhin
     * true: sonst würden die DCA-Felder dieser Erweiterungen (z. B. der
     * Style-Selector) dort nie registriert und ihre DB-Spalten nie
     * angelegt, weil niemand angemeldet ist, der sie "sehen" könnte.
     *
     * Nutzt Security::getUser() statt BackendUser::getInstance(): Letzteres
     * instanziiert auch außerhalb eines Backend-Requests ein Nutzerobjekt
     * ohne geladene Datenbankzeile und wirft dabei "Undefined array
     * key"-Warnings; Security::getUser() liefert dort schlicht null.
     */
    public function isEnabledForCurrentUser(string $feature): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            return true;
        }

        $raw = $user->backendSuiteFeatures ?? null;

        if (null === $raw || '' === $raw) {
            return false;
        }

        $selected = StringUtil::deserialize($raw, true);

        return \in_array($feature, $selected, true);
    }
}
