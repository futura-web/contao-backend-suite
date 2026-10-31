<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeProjectConfig;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\ThemeFontColor\ColorImporter;

/**
 * Schriftgrößen- und Abstands-Einstellungen: Felder für Variablen aus
 * _vars_typography.scss, analog zu Rahmen/Hauptnavi.
 *
 * - Fließtext ($sizeText, wirkt als Default in body{} für <p>/<li>) und
 *   Überschriften ($sizeH1-$sizeH6): jeweils Start- und Endgröße, mit
 *   Leerzeichen getrennt, ohne "px" - baut daraus einen responsiven
 *   clamp()-Wert.
 * - Abstände ($marginText, $marginH1-$marginH6): Dropdown mit den in
 *   _vars_sizes.scss vorhandenen $clampXXpx-Variablen.
 * - Leer -> bestehender Wert bleibt unangetastet.
 */
class TypographyImporter
{
    private const SIZE_FIELD_TO_VAR = [
        'projectTextFontSize' => '$sizeText',
        'projectH1FontSize' => '$sizeH1',
        'projectH2FontSize' => '$sizeH2',
        'projectH3FontSize' => '$sizeH3',
        'projectH4FontSize' => '$sizeH4',
        'projectH5FontSize' => '$sizeH5',
        'projectH6FontSize' => '$sizeH6',
    ];

    private const MARGIN_FIELD_TO_VAR = [
        'projectMarginText' => '$marginText',
        'projectMarginH1' => '$marginH1',
        'projectMarginH2' => '$marginH2',
        'projectMarginH3' => '$marginH3',
        'projectMarginH4' => '$marginH4',
        'projectMarginH5' => '$marginH5',
        'projectMarginH6' => '$marginH6',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ProjectScssPaths $paths,
    ) {
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $fields = array_merge(array_keys(self::SIZE_FIELD_TO_VAR), array_keys(self::MARGIN_FIELD_TO_VAR));

        $row = $this->connection->fetchAssociative(
            'SELECT '.implode(', ', $fields).' FROM tl_theme WHERE id = ?',
            [(int) $dc->id],
        );

        if (!$row) {
            return;
        }

        $path = $this->paths->variablesDir((int) $dc->id).'/_vars_typography.scss';
        $changed = [];

        foreach (self::SIZE_FIELD_TO_VAR as $field => $varName) {
            $raw = trim((string) $row[$field]);

            if ('' === $raw || !preg_match('/^(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)$/', $raw, $m)) {
                continue;
            }

            $min = (float) $m[1];
            $max = (float) $m[2];

            if ($max <= 0) {
                continue;
            }

            if (!is_file($path)) {
                Message::addError(sprintf('Schriftgrößen-Einstellungen: "%s" wurde nicht gefunden.', $path));

                break;
            }

            $value = sprintf('clamp(%spx, #{((%s / $startClamp) * 100)}vw, %spx)', self::trimNum($min), self::trimNum($max), self::trimNum($max));

            if (ColorImporter::setVariable($path, $varName, $value)) {
                $changed[] = $varName;
            }
        }

        foreach (self::MARGIN_FIELD_TO_VAR as $field => $varName) {
            $clampName = (string) $row[$field];

            if ('' === $clampName) {
                continue;
            }

            if (!is_file($path)) {
                Message::addError(sprintf('Abstands-Einstellungen: "%s" wurde nicht gefunden.', $path));

                break;
            }

            if (ColorImporter::setVariable($path, $varName, '$'.$clampName)) {
                $changed[] = $varName;
            }
        }

        if ([] !== $changed) {
            Message::addConfirmation(sprintf('Schriftgrößen-/Abstands-Einstellungen aktualisiert: %s.', implode(', ', $changed)));
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
}
