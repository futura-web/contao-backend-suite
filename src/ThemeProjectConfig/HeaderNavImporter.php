<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeProjectConfig;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\ThemeFontColor\ColorImporter;

/**
 * Header-/Hauptnavi-Einstellungen: Felder für Variablen aus
 * _vars_header.scss und _nav_main.scss, analog zu den Rahmen-Feldern.
 *
 * - Höhe Header: Dropdown mit den in _vars_sizes.scss vorhandenen
 *   $clampXXpx-Variablen, leer -> bestehender Wert bleibt unangetastet.
 * - Einflug-Richtung: welche der in _vars_header.scss vordefinierten
 *   $navMainFromXXX-Variablen die "transform:"-Zeile in _nav_main.scss
 *   referenziert. Bewusst nur Links/Rechts wählbar, leer -> bestehender
 *   Wert bleibt unangetastet.
 */
class HeaderNavImporter
{
    private const DIRECTION_SUFFIXES = ['left' => 'Left', 'right' => 'Right'];

    private const COLOR_FIELD_TO_VAR = [
        'projectNavMainColor' => '$navMainColor',
        'projectNavMainColorHover' => '$navMainColorHover',
        'projectNavMainColorActive' => '$navMainColorActive',
        'projectNavMainColorTrail' => '$navMainColorTrail',
    ];

    public const FONT_WEIGHTS = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];

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
            'SELECT projectHeightHeader, projectLogoHeight, projectColorHamburger, projectNavMainDirection, '
            .'projectNavMainColor, projectNavMainColorHover, projectNavMainColorActive, projectNavMainColorTrail, '
            .'projectNavMainFontSize, projectNavMainFontWeight, projectNavMainBg FROM tl_theme WHERE id = ?',
            [(int) $dc->id],
        );

        if (!$row) {
            return;
        }

        $themeId = (int) $dc->id;
        $headerPath = $this->paths->variablesDir($themeId).'/_vars_header.scss';
        $colorsPath = $this->paths->variablesDir($themeId).'/_vars_colors.scss';
        $typographyPath = $this->paths->variablesDir($themeId).'/_vars_typography.scss';
        $navMainPath = $this->paths->includeDir($themeId).'/_nav_main.scss';
        $changed = [];

        if ('' !== (string) $row['projectHeightHeader']) {
            if (!is_file($headerPath)) {
                Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $headerPath));
            } elseif (ColorImporter::setVariable($headerPath, '$heightHeader', '$'.$row['projectHeightHeader'])) {
                $changed[] = '$heightHeader';
            }
        }

        $logoHeightRaw = trim((string) $row['projectLogoHeight']);

        if ('' !== $logoHeightRaw) {
            if (!is_file($headerPath)) {
                Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $headerPath));
            } elseif (ColorImporter::setVariable($headerPath, '$logoHeight', $logoHeightRaw.'%')) {
                $changed[] = '$logoHeight';
            }
        }

        $hamburgerRole = (string) $row['projectColorHamburger'];

        if ('' !== $hamburgerRole) {
            if (!is_file($headerPath)) {
                Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $headerPath));
            } elseif (ColorImporter::setVariable($headerPath, '$colorHamburger', '$'.$hamburgerRole)) {
                $changed[] = '$colorHamburger';
            }
        }

        $direction = (string) $row['projectNavMainDirection'];

        if (isset(self::DIRECTION_SUFFIXES[$direction])) {
            if (!is_file($navMainPath)) {
                Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $navMainPath));
            } elseif (self::setTransformDirection($navMainPath, self::DIRECTION_SUFFIXES[$direction])) {
                $changed[] = 'transform (Einflug-Richtung)';
            }
        }

        foreach (self::COLOR_FIELD_TO_VAR as $field => $varName) {
            $roleValue = (string) $row[$field];

            if ('' === $roleValue) {
                continue;
            }

            if (!is_file($colorsPath)) {
                Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $colorsPath));

                break;
            }

            if (ColorImporter::setVariable($colorsPath, $varName, '$'.$roleValue)) {
                $changed[] = $varName;
            }
        }

        $fontSizeRaw = trim((string) $row['projectNavMainFontSize']);

        if ('' !== $fontSizeRaw && preg_match('/^(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)$/', $fontSizeRaw, $m)) {
            $min = (float) $m[1];
            $max = (float) $m[2];

            if ($max > 0) {
                $value = sprintf('clamp(%spx, #{((%s / $startClamp) * 100)}vw, %spx)', self::trimNum($min), self::trimNum($max), self::trimNum($max));

                if (!is_file($typographyPath)) {
                    Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $typographyPath));
                } elseif (ColorImporter::setVariable($typographyPath, '$navMainFontSize', $value)) {
                    $changed[] = '$navMainFontSize';
                }
            }
        }

        $weight = (string) $row['projectNavMainFontWeight'];

        if (\in_array($weight, self::FONT_WEIGHTS, true)) {
            if (!is_file($typographyPath)) {
                Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $typographyPath));
            } elseif (ColorImporter::setVariable($typographyPath, '$navMainFontWeight', $weight)) {
                $changed[] = '$navMainFontWeight';
            }
        }

        // Hintergrundfarbe: anders als die übrigen Felder bewusst KEIN
        // "leer -> unverändert" - leer bedeutet hier aktiv "kein
        // Hintergrund" (Default), da das die sinnvolle Ausgangslage für
        // eine neue Hauptnavi ist, nicht nur ein Platzhalter-Zustand.
        $bgRole = (string) $row['projectNavMainBg'];
        $bgValue = '' !== $bgRole ? '$'.$bgRole : '$transpa';

        if (!is_file($headerPath)) {
            Message::addError(sprintf('Hauptnavi-Einstellungen: "%s" wurde nicht gefunden.', $headerPath));
        } elseif (ColorImporter::setVariable($headerPath, '$navMainBg', $bgValue)) {
            $changed[] = '$navMainBg';
        }

        if ([] !== $changed) {
            Message::addConfirmation(sprintf('Hauptnavi-Einstellungen aktualisiert: %s.', implode(', ', $changed)));
        }
    }

    /**
     * Schreibt beim Laden des Bearbeiten-Formulars die Hilfetexte der
     * Hauptnavi-/Header-Farb-Rollen-Felder dynamisch um, analog zu
     * BorderImporter::onLoad().
     */
    public function onLoad(DataContainer|null $dc = null): void
    {
        if (!$dc instanceof DataContainer || !$dc->id) {
            return;
        }

        $themeId = (int) $dc->id;

        $this->applyDynamicDefaultLabels($themeId, $this->paths->variablesDir($themeId).'/_vars_colors.scss', [
            'projectNavMainColor' => 'navMainColor',
            'projectNavMainColorHover' => 'navMainColorHover',
            'projectNavMainColorActive' => 'navMainColorActive',
            'projectNavMainColorTrail' => 'navMainColorTrail',
        ]);

        $this->applyDynamicDefaultLabels($themeId, $this->paths->variablesDir($themeId).'/_vars_header.scss', [
            'projectColorHamburger' => 'colorHamburger',
        ]);
    }

    /**
     * @param array<string, string> $fieldToVar
     */
    private function applyDynamicDefaultLabels(int $themeId, string $path, array $fieldToVar): void
    {
        if (!is_file($path)) {
            return;
        }

        $content = file_get_contents($path);

        if (false === $content) {
            return;
        }

        foreach ($fieldToVar as $field => $varName) {
            if (!preg_match('/^\$'.preg_quote($varName, '/').':\s*\$?([a-zA-Z0-9_-]+)/m', $content, $m)) {
                continue;
            }

            $label = $this->colorImporter->resolveLabel($themeId, $m[1]);

            $GLOBALS['TL_DCA']['tl_theme']['fields'][$field]['label'][1] = sprintf('Default: "%s".', $label);
        }
    }

    /**
     * Formatiert eine Zahl ohne überflüssige Nachkommastellen (16.0 -> "16",
     * 16.5 -> "16.5") für die Verwendung in generierten SCSS-Werten.
     */
    private static function trimNum(float $value): string
    {
        return rtrim(rtrim(sprintf('%F', $value), '0'), '.');
    }

    /**
     * Ersetzt in der "transform:"-Zeile von _nav_main.scss die
     * referenzierte $navMainFromXXX-Variable, Rest der Zeile (z.B. der
     * Kommentar) bleibt erhalten.
     */
    private static function setTransformDirection(string $path, string $suffix): bool
    {
        $content = file_get_contents($path);

        if (false === $content) {
            return false;
        }

        $pattern = '/^(\s*transform:\s*)\$navMainFrom[A-Za-z]+(\s*;.*)$/m';
        $replacement = '${1}$navMainFrom'.$suffix.'${2}';

        $newContent = preg_replace($pattern, $replacement, $content, 1, $count);

        if (null === $newContent || $count < 1) {
            return false;
        }

        file_put_contents($path, $newContent);

        return true;
    }
}
