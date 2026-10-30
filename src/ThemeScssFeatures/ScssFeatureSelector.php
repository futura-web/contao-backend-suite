<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeScssFeatures;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Direkte Checkbox-Liste fuer die optionalen "@import"-Zeilen in
 * base.scss, kuratiert (nicht automatisch aus der Datei abgeleitet) -
 * nur diese Partials gelten als an/ausschaltbare "Features", nicht die
 * strukturellen Imports (Variablen, Header, Hauptnavi, ...).
 *
 * "modalGallery" erfordert "slider" (Modal/Lightbox + Galerie-Grid nutzen
 * das Slider-Markup/JS mit) - beim Speichern wird das automatisch
 * durchgesetzt: Auswahl von "modalGallery" aktiviert "slider" zwangsweise
 * mit, und "slider" kann nicht deaktiviert werden, waehrend "modalGallery"
 * noch ausgewaehlt ist (wird dann ebenfalls deaktiviert).
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
        'slider' => ['label' => 'Slider (_slider)', 'partials' => ['slider']],
        'modalGallery' => ['label' => 'Modal/Lightbox + Galerie-Grid (_modal, _gallery)', 'partials' => ['modal', 'gallery'], 'requires' => ['slider']],
        'beforeAfter' => ['label' => 'Vorher/Nachher-Vergleich (_before_after)', 'partials' => ['before_after']],
        'cookie' => ['label' => 'Cookie-Hinweis (_cookie)', 'partials' => ['cookie']],
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
            'tl_theme',
            ['backendSuiteScssFeatures' => serialize($active)],
            ['id' => (int) $dc->id],
        );
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $raw = $this->connection->fetchOne('SELECT backendSuiteScssFeatures FROM tl_theme WHERE id = ?', [(int) $dc->id]);

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
                $content = $this->setPartialActive($content, $partial, $shouldBeActive);
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
     * Setzt die Auswahl so um, dass "requires" immer erfuellt ist:
     * 1. Abhaengigkeiten nachziehen (z.B. "modalGallery" -> auch "slider").
     * 2. Danach alles wieder entfernen, dessen Abhaengigkeit fehlt (z.B.
     *    wenn "slider" separat abgewaehlt wurde, faellt "modalGallery" mit
     *    weg, statt in einem inkonsistenten Zustand zu bleiben).
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
            if (true !== $this->getPartialState($content, $partial)) {
                return false;
            }
        }

        return true;
    }

    private function getPartialState(string $content, string $partial): bool|null
    {
        $regex = '/^\h*(\/\/\h*)?@import\h+"include\/_'.preg_quote($partial, '/').'"\h*;\h*$/m';

        if (!preg_match($regex, $content, $m)) {
            return null;
        }

        return '' === ($m[1] ?? '');
    }

    private function setPartialActive(string $content, string $partial, bool $active): string
    {
        $regex = '/^(\h*)(\/\/\h*)?(@import\h+"include\/_'.preg_quote($partial, '/').'"\h*;)\h*$/m';

        $newContent = preg_replace_callback($regex, static function (array $m) use ($active): string {
            return $m[1].($active ? '' : '// ').$m[3];
        }, $content, 1);

        return $newContent ?? $content;
    }

    private function readBaseScss(int $themeId): string|null
    {
        $path = $this->baseScssPath($this->resolveProjectSlug($themeId));

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

    private function resolveProjectSlug(int $themeId): string
    {
        $templates = $this->connection->fetchOne('SELECT templates FROM tl_theme WHERE id = ?', [$themeId]);

        return false !== $templates && '' !== $templates ? basename((string) $templates) : 'project';
    }
}
