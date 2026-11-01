<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DynamicField;

/**
 * Zentrale Definition der im Feld-Builder wählbaren Feldtypen: welches
 * DCA-Feld daraus entsteht, welchen SQL-Typ die neue Spalte bekommt, und
 * (für "select") wie die Optionsliste aus dem Textarea-Eingabefeld
 * geparst wird.
 */
class DynamicFieldTypeRegistry
{
    public const TEXT = 'text';
    public const TEXTAREA_TINYMCE = 'textarea_tinymce';
    public const SELECT = 'select';
    public const CHECKBOX = 'checkbox';
    public const GALLERY = 'gallery';
    public const LINK = 'link';

    /** @var array<string, string> */
    public const LABELS = [
        self::TEXT => 'Text (einzeilig)',
        self::TEXTAREA_TINYMCE => 'Textarea mit TinyMCE (Rich-Text)',
        self::SELECT => 'Auswahlliste (Select)',
        self::CHECKBOX => 'Checkbox (Ja/Nein)',
        self::GALLERY => 'Galerie (mehrere Bilder)',
        self::LINK => 'Link/Button (URL)',
    ];

    // Nur für Feldtyp "Select" relevant: woher die Optionen kommen.
    // "module"/"form"/"content" werden nicht hier, sondern von
    // DynamicFieldInjector zur Laufzeit per DB-Abfrage aufgelöst (siehe
    // dort resolveReferenceOptions()) - diese Klasse bleibt DB-frei.
    public const SOURCE_FREE = 'free';
    public const SOURCE_MODULE = 'module';
    public const SOURCE_FORM = 'form';
    public const SOURCE_CONTENT = 'content';

    /** @var array<string, string> */
    public const OPTIONS_SOURCE_LABELS = [
        self::SOURCE_FREE => 'Frei eingetippte Optionen',
        self::SOURCE_MODULE => 'Vorhandenes Modul einbinden',
        self::SOURCE_FORM => 'Vorhandenes Formular einbinden',
        self::SOURCE_CONTENT => 'Vorhandenes Inhaltselement einbinden',
    ];

    public function sqlFor(string $type): string
    {
        return match ($type) {
            self::TEXTAREA_TINYMCE => 'text NULL',
            self::CHECKBOX => "char(1) NOT NULL default ''",
            self::GALLERY => 'blob NULL',
            default => "varchar(255) NOT NULL default ''",
        };
    }

    /**
     * @param array<string, mixed> $row Zeile aus tl_dynamic_field
     *
     * @return array<string, mixed>
     */
    public function buildDcaField(array $row): array
    {
        $label = [
            (string) $row['fieldLabel'],
            '' !== (string) $row['fieldDescription'] ? (string) $row['fieldDescription'] : 'Über den Feld-Builder angelegtes Feld.',
        ];

        return match ((string) $row['fieldType']) {
            self::TEXTAREA_TINYMCE => [
                'label' => $label,
                'exclude' => true,
                'inputType' => 'textarea',
                'eval' => ['rte' => 'tinyMCE', 'tl_class' => 'clr'],
            ],
            self::SELECT => [
                'label' => $label,
                'exclude' => true,
                'inputType' => 'select',
                // Bei "module"/"form"/"content" werden "options"/
                // "reference" von DynamicFieldInjector nach diesem
                // Aufruf mit den echten Datensätzen überschrieben.
                'options' => array_keys($this->parseSelectOptions((string) $row['selectOptions'])),
                'reference' => $this->parseSelectOptions((string) $row['selectOptions']),
                'eval' => ['tl_class' => 'w50', 'includeBlankOption' => true, 'chosen' => true],
            ],
            self::CHECKBOX => [
                'label' => $label,
                'exclude' => true,
                'inputType' => 'checkbox',
                'eval' => ['tl_class' => 'w50'],
            ],
            self::GALLERY => [
                'label' => $label,
                'exclude' => true,
                'inputType' => 'fileTree',
                'eval' => ['fieldType' => 'checkbox', 'files' => true, 'multiple' => true, 'extensions' => 'jpg,jpeg,png,gif,webp', 'tl_class' => 'clr'],
            ],
            self::LINK => [
                'label' => $label,
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'url', 'tl_class' => 'w50', 'placeholder' => 'https://... oder /interne-seite'],
            ],
            default => [
                'label' => $label,
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['tl_class' => 'w50', 'maxlength' => 255],
            ],
        };
    }

    /**
     * Parst eine Zeile-für-Zeile-Liste "wert:Label" aus dem
     * "selectOptions"-Textarea. Zeilen ohne ":" verwenden den gleichen
     * Text für Wert und Label.
     *
     * @return array<string, string>
     */
    public function parseSelectOptions(string $raw): array
    {
        $options = [];

        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            [$value, $optionLabel] = array_pad(explode(':', $line, 2), 2, $line);
            $options[trim($value)] = trim($optionLabel);
        }

        return $options;
    }
}
