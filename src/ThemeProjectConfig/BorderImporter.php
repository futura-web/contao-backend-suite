<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeProjectConfig;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\ThemeFontColor\ColorImporter;

/**
 * Rahmen-Einstellungen (_vars_borders.scss): Rollen-Felder statt freiem
 * Text, analog zu den Farben.
 *
 * - Rahmenfarbe / bei Fokus / bei Fehler: Dropdown, Optionen aus den im
 *   Feld "Farben" angelegten Variablen (gleiche Quelle wie dort).
 * - Rahmenstärke: reine Zahl (ohne "px"), leer -> bestehender Wert bleibt
 *   unangetastet.
 * - Rahmenart: Dropdown mit den CSS-Border-Styles, leer -> bestehender
 *   Wert bleibt unangetastet.
 * - Eckenradius: Dropdown mit den in _vars_sizes.scss vorhandenen
 *   $clampXXpx-Variablen, leer -> bestehender Wert bleibt unangetastet.
 *
 * "Leer" bedeutet in allen Fällen: keine Schreib-Aktion, die Zeile in
 * _vars_borders.scss bleibt exakt so stehen, wie sie aktuell ist.
 */
class BorderImporter
{
    public const BORDER_TYPES = ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'];

    public function __construct(
        private readonly Connection $connection,
        private readonly ProjectScssPaths $paths,
        private readonly ColorImporter $colorImporter,
    ) {
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT projectBorderColor, projectBorderColorFocus, projectBorderColorError, '
            .'projectBorderSize, projectBorderType, projectBorderRadius FROM tl_theme WHERE id = ?',
            [(int) $dc->id],
        );

        if (!$row) {
            return;
        }

        $writes = [];

        if ('' !== (string) $row['projectBorderColor']) {
            $writes['$borderColor'] = '$'.$row['projectBorderColor'];
        }

        if ('' !== (string) $row['projectBorderColorFocus']) {
            $writes['$borderColorFocus'] = '$'.$row['projectBorderColorFocus'];
        }

        if ('' !== (string) $row['projectBorderColorError']) {
            $writes['$borderColorError'] = '$'.$row['projectBorderColorError'];
        }

        if ('' !== (string) $row['projectBorderSize']) {
            $size = preg_replace('/[^0-9.]/', '', (string) $row['projectBorderSize']) ?? '';

            if ('' !== $size) {
                $writes['$borderSize'] = $size.'px';
            }
        }

        if (\in_array((string) $row['projectBorderType'], self::BORDER_TYPES, true)) {
            $writes['$borderType'] = (string) $row['projectBorderType'];
        }

        if ('' !== (string) $row['projectBorderRadius']) {
            $writes['$borderRadius'] = '$'.$row['projectBorderRadius'];
        }

        if ([] === $writes) {
            return;
        }

        $path = $this->paths->variablesDir((int) $dc->id).'/_vars_borders.scss';

        if (!is_file($path)) {
            Message::addError(sprintf('Rahmen-Einstellungen: "%s" wurde nicht gefunden.', $path));

            return;
        }

        $changed = [];

        foreach ($writes as $name => $value) {
            if (ColorImporter::setVariable($path, $name, $value)) {
                $changed[] = $name;
            }
        }

        if ([] === $changed) {
            return;
        }

        Message::addConfirmation(sprintf('Rahmen-Einstellungen aktualisiert: %s.', implode(', ', $changed)));
    }

    /**
     * Optionen für das Eckenradius-Dropdown: alle $clampXXpx-Variablen,
     * die aktuell in _vars_sizes.scss definiert sind.
     *
     * @return array<string, string>
     */
    public function getClampSizeOptions(DataContainer $dc): array
    {
        $themeId = $dc->id ? (int) $dc->id : $this->firstThemeId();

        if (null === $themeId) {
            return [];
        }

        $path = $this->paths->variablesDir($themeId).'/_vars_sizes.scss';

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return [];
        }

        preg_match_all('/^\$(clamp\d+px):/m', $content, $matches);

        $names = array_unique($matches[1]);

        return array_combine($names, $names);
    }

    /**
     * Schreibt beim Laden des Bearbeiten-Formulars die Hilfetexte der 3
     * Farb-Rollen-Felder dynamisch um: statt eines generischen Hinweises
     * zeigen sie den tatsächlich aufgelösten Farbton der aktuell in
     * _vars_borders.scss hinterlegten Variable, z.B. 'Default: "Schwarz"'.
     */
    public function onLoad(DataContainer|null $dc = null): void
    {
        if (!$dc instanceof DataContainer || !$dc->id) {
            return;
        }

        $path = $this->paths->variablesDir((int) $dc->id).'/_vars_borders.scss';

        if (!is_file($path)) {
            return;
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return;
        }

        $fieldToVar = [
            'projectBorderColor' => 'borderColor',
            'projectBorderColorFocus' => 'borderColorFocus',
            'projectBorderColorError' => 'borderColorError',
        ];

        foreach ($fieldToVar as $field => $varName) {
            if (!preg_match('/^\$'.preg_quote($varName, '/').':\s*\$?([a-zA-Z0-9_-]+)/m', $content, $m)) {
                continue;
            }

            $label = $this->colorImporter->resolveLabel((int) $dc->id, $m[1]);

            $GLOBALS['TL_DCA']['tl_theme']['fields'][$field]['label'][1] = sprintf('Default: "%s".', $label);
        }
    }

    private function firstThemeId(): int|null
    {
        $id = $this->connection->fetchOne('SELECT id FROM tl_theme ORDER BY name LIMIT 1');

        return false !== $id ? (int) $id : null;
    }
}
