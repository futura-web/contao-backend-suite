<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeJsFunctions;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Spiegelt die einzelnen JS-Funktionsdateien unter
 * "theme/js/functions/js.<name>.js" als Checkbox-Liste im Theme-Backend,
 * statt sie manuell in "theme/js/php/assembleFunctions.js.php" ein- und
 * auszukommentieren.
 *
 * assembleFunctions.js.php bleibt die alleinige Quelle fuer WANN eine
 * Funktion aufgerufen wird (die Zeitpunkt-Buckets "base"/"classes"/
 * "divers"/"ready"/"scroll"/"loaded") - diese Klasse schaltet nur, OB ein
 * bereits vorhandener Eintrag aktiv (unkommentiert) oder inaktiv
 * (auskommentiert) ist. Eine Funktion, die dort noch GAR NICHT auftaucht,
 * wird beim ersten Aktivieren neu in den Bucket "ready" eingefuegt
 * (haeufigster Standardfall: DOM-Ready-Funktionen).
 */
class JsFunctionSelector
{
    private const FUNCTIONS_RELATIVE_DIR = '/theme/js/functions';
    private const ASSEMBLE_FILE_RELATIVE_PATH = '/theme/js/php/assembleFunctions.js.php';
    private const DEFAULT_BUCKET = 'ready';

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

        $this->connection->update(
            'tl_theme',
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

        $options = [];

        foreach ($this->getAvailableFunctions($this->resolveProjectSlug((int) $dc->id)) as $name) {
            $options[$name] = $name;
        }

        return $options;
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $raw = $this->connection->fetchOne('SELECT backendSuiteJsFunctions FROM tl_theme WHERE id = ?', [(int) $dc->id]);

        if (false === $raw) {
            return;
        }

        $selected = \is_array($unserialized = @unserialize((string) $raw)) ? $unserialized : [];
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

        if ([] === $enabled && [] === $disabled && [] === $added) {
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

        Message::addConfirmation('JS-Funktionen '.implode('; ', $parts).'.');
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

    private function resolveProjectSlug(int $themeId): string
    {
        $templates = $this->connection->fetchOne('SELECT templates FROM tl_theme WHERE id = ?', [$themeId]);

        return false !== $templates && '' !== $templates ? basename((string) $templates) : 'project';
    }
}
