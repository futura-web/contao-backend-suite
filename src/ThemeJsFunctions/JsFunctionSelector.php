<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeJsFunctions;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\Scss\ScssPartialToggle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Spiegelt die einzelnen JS-Funktionsdateien unter
 * "theme/js/functions/js.<name>.js" als Checkbox-Liste im
 * Seitenlayout-Backend, statt sie manuell in
 * "theme/js/php/assembleFunctions.js.php" ein- und auszukommentieren.
 *
 * assembleFunctions.js.php bleibt die alleinige Quelle fuer WANN eine
 * Funktion aufgerufen wird (die Zeitpunkt-Buckets "base"/"classes"/
 * "divers"/"ready"/"scroll"/"loaded") - diese Klasse schaltet nur, OB ein
 * bereits vorhandener Eintrag aktiv (unkommentiert) oder inaktiv
 * (auskommentiert) ist. Eine Funktion, die dort noch GAR NICHT auftaucht,
 * wird beim ersten Aktivieren neu in den Bucket "ready" eingefuegt
 * (haeufigster Standardfall: DOM-Ready-Funktionen).
 *
 * Zwei Kopplungen zu anderen Dateien:
 * - HIDDEN_DEPENDENTS: eine Funktion, die technisch zwingend eine andere
 *   voraussetzt (z.B. "dSlider" braucht "dSliderDefines"), bekommt keine
 *   eigene Checkbox - ihr Zustand folgt automatisch dem der Hauptfunktion.
 * - JS_TRIGGERED_SCSS_PARTIALS: manche "@import"-Partials in base.scss
 *   (Slider, Modal, Galerie) werden nicht ueber die separate
 *   SCSS-Features-Liste manuell geschaltet, sondern automatisch aktiv,
 *   sobald die passende JS-Funktion aktiv ist - so als waeren sie dort
 *   angehakt.
 */
class JsFunctionSelector
{
    private const FUNCTIONS_RELATIVE_DIR = '/theme/js/functions';
    private const ASSEMBLE_FILE_RELATIVE_PATH = '/theme/js/php/assembleFunctions.js.php';
    private const BASE_SCSS_RELATIVE_PATH = '/theme/css/base.scss';
    private const DEFAULT_BUCKET = 'ready';

    /**
     * @var array<string, list<string>>
     */
    private const HIDDEN_DEPENDENTS = [
        'dSlider' => ['dSliderDefines'],
    ];

    /**
     * Funktionen, die nicht mehr als Checkbox angeboten werden und bei
     * jedem Speichern zwangsweise deaktiviert (auskommentiert) werden -
     * z.B. weil die Funktion durch eine reine CSS-Loesung ersetzt wurde.
     * Die Datei selbst bleibt erhalten, nur der assembleFunctions.js.php
     * -Eintrag wird stillgelegt.
     *
     * @var list<string>
     */
    private const RETIRED_FUNCTIONS = ['toTop'];

    /**
     * Funktionen, die immer aktiv sein sollen und daher keine eigene
     * Checkbox brauchen - werden bei jedem Speichern zwangsweise aktiviert
     * (z.B. "globalVars", das grundlegende globale Variablen bereitstellt,
     * von denen andere Funktionen abhaengen).
     *
     * @var list<string>
     */
    private const ALWAYS_ACTIVE_FUNCTIONS = ['globalVars'];

    /**
     * @var array<string, list<string>>
     */
    private const JS_TRIGGERED_SCSS_PARTIALS = [
        'dSlider' => ['slider'],
        'contaoModal' => ['modal', 'slider'],
        'dGallery' => ['gallery', 'slider'],
        'beforeAfter' => ['before_after'],
        'privacyNotice' => ['cookie'],
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Beim Laden des Formulars den Checkbox-Zustand immer frisch aus der
     * Datei ableiten, statt einem evtl. veralteten DB-Wert zu vertrauen -
     * falls die Datei manuell bearbeitet wurde, stimmt die Anzeige trotzdem.
     */
    public function onLoad(DataContainer|null $dc = null): void
    {
        if (!$dc instanceof DataContainer || !$dc->id) {
            return;
        }

        $projectSlug = $this->resolveProjectSlug((int) $dc->id);
        $active = array_keys(array_filter($this->getFunctionStates($projectSlug)));

        // Versteckte Abhaengigkeiten und stillgelegte Funktionen (siehe
        // HIDDEN_DEPENDENTS / RETIRED_FUNCTIONS) haben keine eigene
        // Checkbox - wuerden sie hier mitgespeichert, rendert Contao sie
        // als "Unbekannte Option", weil getOptionsForDc() sie nicht mehr
        // auflistet.
        $active = array_values(array_diff($active, $this->excludedFunctionNames()));

        $this->connection->update(
            'tl_layout',
            ['backendSuiteJsFunctions' => serialize($active)],
            ['id' => (int) $dc->id],
        );
    }

    /**
     * @return array<string, string>
     */
    public function getOptionsForDc(DataContainer $dc): array
    {
        if (!$dc->id) {
            return [];
        }

        $excluded = $this->excludedFunctionNames();
        $options = [];

        foreach ($this->getAvailableFunctions($this->resolveProjectSlug((int) $dc->id)) as $name) {
            if (\in_array($name, $excluded, true)) {
                continue;
            }

            $options[$name] = $name;
        }

        return $options;
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $raw = $this->connection->fetchOne('SELECT backendSuiteJsFunctions FROM tl_layout WHERE id = ?', [(int) $dc->id]);

        if (false === $raw) {
            return;
        }

        $selected = \is_array($unserialized = @unserialize((string) $raw)) ? $unserialized : [];
        $selected = array_values(array_diff($selected, self::RETIRED_FUNCTIONS));
        $selected = array_values(array_unique(array_merge($selected, self::ALWAYS_ACTIVE_FUNCTIONS)));
        $selected = $this->applyHiddenDependents($selected);

        $projectSlug = $this->resolveProjectSlug((int) $dc->id);
        $path = $this->assembleFilePath($projectSlug);

        if (!is_file($path)) {
            Message::addError(sprintf('JS-Funktionen: "%s" wurde nicht gefunden.', self::ASSEMBLE_FILE_RELATIVE_PATH));

            return;
        }

        $states = $this->getFunctionStates($projectSlug);
        $enabled = [];
        $disabled = [];
        $added = [];

        foreach ($this->getAvailableFunctions($projectSlug) as $name) {
            $shouldBeActive = \in_array($name, $selected, true);
            $isActive = $states[$name] ?? null;

            if ($shouldBeActive === $isActive) {
                continue;
            }

            if (null === $isActive) {
                if ($shouldBeActive) {
                    $this->insertFunction($path, $name);
                    $added[] = $name;
                }

                continue;
            }

            if ($this->setFunctionActive($path, $name, $shouldBeActive)) {
                if ($shouldBeActive) {
                    $enabled[] = $name;
                } else {
                    $disabled[] = $name;
                }
            }
        }

        $scssChanged = $this->syncScssPartials($projectSlug, $selected);

        if ([] === $enabled && [] === $disabled && [] === $added && !$scssChanged) {
            return;
        }

        $parts = [];

        if ($added) {
            $parts[] = 'neu eingebunden (Bucket "'.self::DEFAULT_BUCKET.'"): '.implode(', ', $added);
        }

        if ($enabled) {
            $parts[] = 'aktiviert: '.implode(', ', $enabled);
        }

        if ($disabled) {
            $parts[] = 'deaktiviert: '.implode(', ', $disabled);
        }

        if ($scssChanged) {
            $parts[] = 'zugehörige SCSS-Partials (Slider/Modal/Galerie) angepasst';
        }

        if ([] !== $parts) {
            Message::addConfirmation('JS-Funktionen '.implode('; ', $parts).'.');
        }
    }

    /**
     * Alle Funktionsnamen, die keine eigene Checkbox haben: versteckte
     * Abhaengigkeiten (HIDDEN_DEPENDENTS-Werte), stillgelegte Funktionen
     * (RETIRED_FUNCTIONS) und immer aktive Funktionen
     * (ALWAYS_ACTIVE_FUNCTIONS).
     *
     * @return list<string>
     */
    private function excludedFunctionNames(): array
    {
        $hidden = [] !== self::HIDDEN_DEPENDENTS ? array_merge(...array_values(self::HIDDEN_DEPENDENTS)) : [];

        return array_values(array_unique(array_merge($hidden, self::RETIRED_FUNCTIONS, self::ALWAYS_ACTIVE_FUNCTIONS)));
    }

    /**
     * Erzwingt, dass eine versteckte abhaengige Funktion (siehe
     * HIDDEN_DEPENDENTS) immer denselben Aktiv-Zustand wie ihre
     * Hauptfunktion hat - unabhaengig davon, was tatsaechlich im
     * (nicht vorhandenen) Checkbox-Feld uebermittelt wurde.
     *
     * @param list<string> $selected
     *
     * @return list<string>
     */
    private function applyHiddenDependents(array $selected): array
    {
        foreach (self::HIDDEN_DEPENDENTS as $parent => $dependents) {
            foreach ($dependents as $dependent) {
                if (\in_array($parent, $selected, true)) {
                    if (!\in_array($dependent, $selected, true)) {
                        $selected[] = $dependent;
                    }
                } else {
                    $selected = array_values(array_diff($selected, [$dependent]));
                }
            }
        }

        return $selected;
    }

    /**
     * Aktiviert/deaktiviert die SCSS-Partials, die an bestimmte
     * JS-Funktionen gekoppelt sind (siehe JS_TRIGGERED_SCSS_PARTIALS),
     * in base.scss. Ein Partial gilt als aktiv, sobald IRGENDEINE der
     * Funktionen, die es referenzieren, aktiv ist.
     *
     * @param list<string> $selected
     */
    private function syncScssPartials(string $projectSlug, array $selected): bool
    {
        $path = $this->baseScssPath($projectSlug);

        if (!is_file($path)) {
            return false;
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return false;
        }

        $allPartials = array_values(array_unique(array_merge(...array_values(self::JS_TRIGGERED_SCSS_PARTIALS))));
        $changed = false;

        foreach ($allPartials as $partial) {
            $shouldBeActive = false;

            foreach (self::JS_TRIGGERED_SCSS_PARTIALS as $jsName => $partials) {
                if (\in_array($partial, $partials, true) && \in_array($jsName, $selected, true)) {
                    $shouldBeActive = true;
                    break;
                }
            }

            $isActive = ScssPartialToggle::getState($content, $partial);

            if (null !== $isActive && $shouldBeActive !== $isActive) {
                $content = ScssPartialToggle::setActive($content, $partial, $shouldBeActive);
                $changed = true;
            }
        }

        if ($changed) {
            file_put_contents($path, $content);
        }

        return $changed;
    }

    /**
     * @return list<string>
     */
    private function getAvailableFunctions(string $projectSlug): array
    {
        $dir = $this->projectDir.'/files/public/'.$projectSlug.self::FUNCTIONS_RELATIVE_DIR;

        if (!is_dir($dir)) {
            return [];
        }

        $names = [];

        foreach (glob($dir.'/js.*.js') ?: [] as $file) {
            $basename = basename($file, '.js');

            if (str_starts_with($basename, 'js.')) {
                $names[] = substr($basename, 3);
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Alle Funktions-Eintraege, die aktuell IRGENDWO in
     * assembleFunctions.js.php stehen (unabhaengig vom Bucket), mit ihrem
     * Aktiv-Zustand (true = nicht auskommentiert).
     *
     * @return array<string, bool>
     */
    private function getFunctionStates(string $projectSlug): array
    {
        $content = $this->readArrayBlock($this->assembleFilePath($projectSlug));

        if (null === $content) {
            return [];
        }

        preg_match_all(
            '/^(\h*)(\/\/\h*)?\[\h*\'([a-zA-Z0-9_]+)\'(?:\h*,\h*\'param\'\h*=>\h*\'[^\']*\')?\h*\],?\h*$/m',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        // Manche Funktionen stehen bewusst in mehreren Buckets als
        // Timing-Alternative (z.B. "checkScroll" in "ready" UND "scroll") -
        // gilt als aktiv, sobald IRGENDEIN Vorkommen unkommentiert ist.
        $states = [];

        foreach ($matches as $m) {
            $states[$m[3]] = ($states[$m[3]] ?? false) || '' === $m[2];
        }

        return $states;
    }

    private function setFunctionActive(string $path, string $name, bool $active): bool
    {
        $content = file_get_contents($path);

        if (false === $content) {
            return false;
        }

        $regex = '/^(\h*)(\/\/\h*)?(\[\h*\''.preg_quote($name, '/').'\'(?:\h*,\h*\'param\'\h*=>\h*\'[^\']*\')?\h*\],?)\h*$/m';

        // Alle Vorkommen konsistent schalten (eine Funktion kann in
        // mehreren Buckets als Timing-Alternative auftauchen, siehe
        // getFunctionStates()) - sonst widersprechen sich Anzeige- und
        // Dateizustand.
        $newContent = preg_replace_callback($regex, static function (array $m) use ($active): string {
            return $m[1].($active ? '' : '// ').$m[3];
        }, $content, -1, $count);

        if (null === $newContent || $count < 1) {
            return false;
        }

        file_put_contents($path, $newContent);

        return true;
    }

    private function insertFunction(string $path, string $name): void
    {
        $content = file_get_contents($path);

        if (false === $content) {
            return;
        }

        $bucketPattern = '/(\''.preg_quote(self::DEFAULT_BUCKET, '/').'\'\h*=>\h*\[)(.*?)(\n(\h*)\],)/s';

        $newContent = preg_replace_callback($bucketPattern, static function (array $m) use ($name): string {
            $indent = $m[4].'    ';

            return $m[1].$m[2].$indent.'[\''.$name.'\'],'."\n".$m[3];
        }, $content, 1, $count);

        if (null === $newContent || $count < 1) {
            return;
        }

        file_put_contents($path, $newContent);
    }

    private function readArrayBlock(string $path): string|null
    {
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return null;
        }

        if (!preg_match('/\$arrJsPaths\h*=\h*\[(.*?)\n\h*\];/s', $content, $m)) {
            return null;
        }

        return $m[1];
    }

    private function assembleFilePath(string $projectSlug): string
    {
        return $this->projectDir.'/files/public/'.$projectSlug.self::ASSEMBLE_FILE_RELATIVE_PATH;
    }

    private function baseScssPath(string $projectSlug): string
    {
        return $this->projectDir.'/files/public/'.$projectSlug.self::BASE_SCSS_RELATIVE_PATH;
    }

    private function resolveProjectSlug(int $layoutId): string
    {
        $themeId = $this->connection->fetchOne('SELECT pid FROM tl_layout WHERE id = ?', [$layoutId]);

        if (false === $themeId) {
            return 'project';
        }

        $templates = $this->connection->fetchOne('SELECT templates FROM tl_theme WHERE id = ?', [(int) $themeId]);

        return false !== $templates && '' !== $templates ? basename((string) $templates) : 'project';
    }
}
