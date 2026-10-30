<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeScssFeatures;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\Scss\ScssPartialToggle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checkbox-Liste fuer die optionalen "@import"-Zeilen in base.scss,
 * kuratiert (nicht automatisch aus der Datei abgeleitet) - nur diese
 * Partials gelten als manuell an/ausschaltbare "Features", nicht die
 * strukturellen Imports (Variablen, Header, Hauptnavi, ...).
 *
 * "Slider", "Modal/Lightbox + Galerie-Grid" (_slider, _modal, _gallery),
 * "Vorher/Nachher-Vergleich" (_before_after) und "Cookie-Hinweis"
 * (_cookie) stehen hier bewusst NICHT zur Auswahl - sie werden
 * automatisch anhand der passenden JS-Funktionen geschaltet, siehe
 * JsFunctionSelector::JS_TRIGGERED_SCSS_PARTIALS. "Autoscroll" bleibt
 * dagegen manuell schaltbar, da es auch ohne JS (rein per CSS) umsetzbar
 * ist.
 */
class ScssFeatureSelector
{
    private const BASE_SCSS_RELATIVE_PATH = '/theme/css/base.scss';

    /**
     * @var array<string, array{label: string, partials: list<string>, requires?: list<string>}>
     */
    private const FEATURES = [
        'forms' => ['label' => 'Formulare (_forms)', 'partials' => ['forms']],
        'stickyFooter' => ['label' => 'Sticky Footer (_sticky_footer)', 'partials' => ['sticky_footer']],
        'banner' => ['label' => 'Banner (_banner)', 'partials' => ['banner']],
        'autoscroll' => ['label' => 'Autoscroll (_autoscroll)', 'partials' => ['autoscroll']],
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getOptionsForDc(DataContainer $dc): array
    {
        $options = [];

        foreach (self::FEATURES as $key => $feature) {
            $options[$key] = $feature['label'];
        }

        return $options;
    }

    public function onLoad(DataContainer|null $dc = null): void
    {
        if (!$dc instanceof DataContainer || !$dc->id) {
            return;
        }

        $content = $this->readBaseScss((int) $dc->id);

        if (null === $content) {
            return;
        }

        $active = [];

        foreach (self::FEATURES as $key => $feature) {
            if ($this->isFeatureActive($content, $feature['partials'])) {
                $active[] = $key;
            }
        }

        $this->connection->update(
            'tl_layout',
            ['backendSuiteScssFeatures' => serialize($active)],
            ['id' => (int) $dc->id],
        );
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $raw = $this->connection->fetchOne('SELECT backendSuiteScssFeatures FROM tl_layout WHERE id = ?', [(int) $dc->id]);

        if (false === $raw) {
            return;
        }

        $submitted = \is_array($u = @unserialize((string) $raw)) ? array_values(array_intersect($u, array_keys(self::FEATURES))) : [];
        $resolved = $this->resolveDependencies($submitted);

        $path = $this->baseScssPath($this->resolveProjectSlug((int) $dc->id));

        if (!is_file($path)) {
            Message::addError(sprintf('SCSS-Features: "%s" wurde nicht gefunden.', self::BASE_SCSS_RELATIVE_PATH));

            return;
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return;
        }

        $enabled = [];
        $disabled = [];

        foreach (self::FEATURES as $key => $feature) {
            $shouldBeActive = \in_array($key, $resolved, true);
            $isActive = $this->isFeatureActive($content, $feature['partials']);

            if ($shouldBeActive === $isActive) {
                continue;
            }

            foreach ($feature['partials'] as $partial) {
                $content = ScssPartialToggle::setActive($content, $partial, $shouldBeActive);
            }

            if ($shouldBeActive) {
                $enabled[] = $feature['label'];
            } else {
                $disabled[] = $feature['label'];
            }
        }

        if ([] === $enabled && [] === $disabled) {
            return;
        }

        file_put_contents($path, $content);

        $parts = [];

        if ($enabled) {
            $parts[] = 'aktiviert: '.implode(', ', $enabled);
        }

        if ($disabled) {
            $parts[] = 'deaktiviert: '.implode(', ', $disabled);
        }

        Message::addConfirmation('SCSS-Features '.implode('; ', $parts).'.');
    }

    /**
     * Setzt die Auswahl so um, dass "requires" immer erfuellt ist (z.B.
     * eine spaetere Checkbox, die eine andere voraussetzt): Abhaengigkeiten
     * nachziehen, dann alles wieder entfernen, dessen Abhaengigkeit fehlt.
     *
     * @param list<string> $selected
     *
     * @return list<string>
     */
    private function resolveDependencies(array $selected): array
    {
        $selected = array_values(array_unique($selected));

        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($selected as $key) {
                foreach (self::FEATURES[$key]['requires'] ?? [] as $required) {
                    if (!\in_array($required, $selected, true)) {
                        $selected[] = $required;
                        $changed = true;
                    }
                }
            }
        }

        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($selected as $i => $key) {
                foreach (self::FEATURES[$key]['requires'] ?? [] as $required) {
                    if (!\in_array($required, $selected, true)) {
                        unset($selected[$i]);
                        $changed = true;
                    }
                }
            }

            $selected = array_values($selected);
        }

        return $selected;
    }

    /**
     * @param list<string> $partials
     */
    private function isFeatureActive(string $content, array $partials): bool
    {
        foreach ($partials as $partial) {
            if (true !== ScssPartialToggle::getState($content, $partial)) {
                return false;
            }
        }

        return true;
    }

    private function readBaseScss(int $layoutId): string|null
    {
        $path = $this->baseScssPath($this->resolveProjectSlug($layoutId));

        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return false !== $content ? $content : null;
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
