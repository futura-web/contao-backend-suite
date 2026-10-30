<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeFontColor;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Schreibt im Theme gepflegte Farb-Variablen in "_vars_colors.scss".
 *
 * Zeilen-Syntax im Feld "backendSuiteColors":
 *   $variable | Wert   oder   $variable Wert
 *   optional ein Farbton nach "//": "$variable | Wert //Farbton"
 *
 * Existiert die Variable schon in der Datei, wird nur ihr Wert ersetzt;
 * sonst wird eine neue Zeile ans Ende angehängt. Erwartet dieselbe
 * SCSS-Projektstruktur-Konvention wie der FontImporter.
 */
class ColorImporter
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT backendSuiteColors, templates FROM tl_theme WHERE id = ?',
            [(int) $dc->id],
        );

        if (!$row) {
            return;
        }

        $colors = self::parseColorList((string) $row['backendSuiteColors']);

        if ([] === $colors) {
            return;
        }

        // "templates" speichert den Pfad relativ zum Projekt-Root (z.B.
        // "templates/project"), nicht nur den Ordnernamen - daher basename().
        $path = $this->varsColorsPath(basename((string) ($row['templates'] ?: 'project')));

        if (!is_file($path)) {
            Message::addError(sprintf('Farb-Import: "%s" wurde nicht gefunden.', $path));

            return;
        }

        $updated = [];
        $added = [];

        foreach ($colors as $name => $entry) {
            $isNew = !self::setVariable($path, $name, $entry['value'], $entry['label']);

            if ($isNew) {
                self::appendVariable($path, $name, $entry['value'], $entry['label']);
                $added[] = $name;
            } else {
                $updated[] = $name;
            }
        }

        Message::addConfirmation(sprintf(
            'Farb-Import: %d Variable(n) aktualisiert%s.',
            \count($updated) + \count($added),
            $added ? ' (neu angelegt: '.implode(', ', $added).')' : '',
        ));

        // Eingabefeld nach erfolgreicher Verarbeitung leeren - die "aktuell
        // eingebundenen Farben" werden client-seitig direkt aus
        // _vars_colors.scss angezeigt, nicht aus diesem Feld.
        $this->connection->update('tl_theme', ['backendSuiteColors' => ''], ['id' => (int) $dc->id]);
    }

    /**
     * Optionen für Rollen-Dropdowns (Rahmenfarbe, Links, ...): alle
     * Variablen, die aktuell (mit Farbton-Kommentar) in _vars_colors.scss
     * des aktiven Themes stehen.
     *
     * @return array<string, string>
     */
    public function getColorOptionsForDc(DataContainer $dc): array
    {
        $themeId = $this->resolveThemeId($dc);

        if (null === $themeId) {
            return [];
        }

        return $this->getColorOptions($themeId);
    }

    /**
     * @return array<string, string>
     */
    public function getColorOptions(int $themeId): array
    {
        $options = [];

        foreach ($this->parseColorsFromFile($themeId) as $name => $entry) {
            $key = ltrim($name, '$');
            $options[$key] = $entry['label'] ?? $key;
        }

        return $options;
    }

    public function resolveLabel(int $themeId, string $varName): string
    {
        $varName = '$'.ltrim($varName, '$');
        $colors = $this->parseColorsFromFile($themeId);

        return $colors[$varName]['label'] ?? ltrim($varName, '$');
    }

    public static function setVariable(string $path, string $name, string $value, ?string $label = null): bool
    {
        $content = file_get_contents($path);

        if (false === $content) {
            return false;
        }

        if (null !== $label) {
            $pattern = '/^('.preg_quote($name, '/').':\s*).*$/m';
            $replacement = $value.'; //'.$label;
        } else {
            $pattern = '/^('.preg_quote($name, '/').':\s*).*?(;.*)$/m';
            $replacement = null;
        }

        $newContent = preg_replace_callback($pattern, static function (array $m) use ($value, $replacement): string {
            return $m[1].($replacement ?? ($value.$m[2]));
        }, $content, 1, $count);

        if (null === $newContent || $count < 1) {
            return false;
        }

        file_put_contents($path, $newContent);

        return true;
    }

    public static function appendVariable(string $path, string $name, string $value, ?string $label = null): void
    {
        $content = file_get_contents($path);

        if (false === $content) {
            return;
        }

        $line = $name.': '.$value.';'.(null !== $label ? ' //'.$label : '');

        file_put_contents($path, rtrim($content, "\n")."\n".$line."\n");
    }

    /**
     * @return array<string, array{value: string, label: string|null}>
     */
    private static function parseColorList(string $raw): array
    {
        $colors = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            if (str_contains($line, '|')) {
                $parts = array_map('trim', explode('|', $line, 2));

                if (2 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
                    continue;
                }

                [$name, $rest] = $parts;
            } else {
                if (!preg_match('/^(\S+)\s+(.+)$/', $line, $m)) {
                    continue;
                }

                $name = $m[1];
                $rest = trim($m[2]);
            }

            if (preg_match('/^(.*?)\s*\/\/\s*(.*)$/', $rest, $lm)) {
                $value = trim($lm[1]);
                $label = trim($lm[2]);
                $label = '' !== $label ? $label : null;
            } else {
                $value = $rest;
                $label = null;
            }

            if ('' === $name || '' === $value || str_contains($value, ' ')) {
                continue;
            }

            if (!str_starts_with($name, '$')) {
                $name = '$'.$name;
            }

            if (!preg_match('/^\$[a-zA-Z_][a-zA-Z0-9_-]*$/', $name)) {
                continue;
            }

            $colors[$name] = ['value' => $value, 'label' => $label];
        }

        return $colors;
    }

    /**
     * @return array<string, array{value: string, label: string|null}>
     */
    private function parseColorsFromFile(int $themeId): array
    {
        $templates = $this->connection->fetchOne('SELECT templates FROM tl_theme WHERE id = ?', [$themeId]);
        // "templates" speichert den Pfad relativ zum Projekt-Root (z.B.
        // "templates/project"), nicht nur den Ordnernamen - daher basename().
        $path = $this->varsColorsPath(false !== $templates && '' !== $templates ? basename((string) $templates) : 'project');

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return [];
        }

        preg_match_all('/^(\$[a-zA-Z_][a-zA-Z0-9_-]*):\s*([^;]+);\s*(?:\/\/\s*(.*))?$/m', $content, $matches, PREG_SET_ORDER);

        $colors = [];

        foreach ($matches as $m) {
            $value = trim($m[2]);
            $label = isset($m[3]) ? trim($m[3]) : '';

            // Reine "!default"-Platzhalterzeilen referenzieren nur eine
            // andere Variable und haben i.d.R. keinen eigenen
            // Farbton-Kommentar - fuer die Rollen-Dropdowns nicht relevant.
            if ('' === $label) {
                continue;
            }

            $colors[$m[1]] = ['value' => $value, 'label' => $label];
        }

        return $colors;
    }

    private function varsColorsPath(string $projectSlug): string
    {
        return $this->projectDir.'/files/public/'.$projectSlug.'/theme/css/include/variables/_vars_colors.scss';
    }

    private function resolveThemeId(DataContainer $dc): int|null
    {
        if ($dc->id) {
            return (int) $dc->id;
        }

        $firstThemeId = $this->connection->fetchOne('SELECT id FROM tl_theme ORDER BY name LIMIT 1');

        return false !== $firstThemeId ? (int) $firstThemeId : null;
    }
}
