<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DynamicField;

use Contao\StringUtil;

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

    // Der Feldtyp "Link" erzeugt KEINE einzelne Spalte, sondern vier
    // zusammengehörige Spalten (fieldName + jeweiliger Suffix) - siehe
    // columnSqlTypes()/buildDcaFields(). Alle anderen Feldtypen liefern
    // dort weiterhin genau einen Eintrag mit Suffix "".
    public const LINK_SUFFIX_URL = '';
    public const LINK_SUFFIX_TITLE = 'Title';
    public const LINK_SUFFIX_TEXT = 'Text';
    public const LINK_SUFFIX_TARGET = 'Target';

    /** @var array<string, string> */
    public const LABELS = [
        self::TEXT => 'Text (einzeilig)',
        self::TEXTAREA_TINYMCE => 'Textarea mit TinyMCE (Rich-Text)',
        self::SELECT => 'Auswahlliste (Select)',
        self::CHECKBOX => 'Checkbox (Ja/Nein)',
        self::GALLERY => 'Galerie (mehrere Bilder)',
        self::LINK => 'Link/Button (URL + Titel + Text + neuer Tab)',
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
     * Welche Spalte(n) ein Feldtyp tatsächlich in der Zieltabelle
     * braucht. Bei allen einfachen Typen genau eine, mit Suffix "" (die
     * Spalte heißt dann exakt wie "fieldName"). "Link" ist der einzige
     * zusammengesetzte Typ und braucht vier (URL/Titel/Text/Blank-
     * Checkbox) - DynamicFieldSchemaSync::ensureColumn() legt für jeden
     * zurückgelieferten Suffix "fieldName.$suffix" als eigene Spalte an.
     *
     * @return array<string, string> Suffix => SQL-Spaltendefinition
     */
    public function columnSqlTypes(string $type): array
    {
        if (self::LINK === $type) {
            return [
                self::LINK_SUFFIX_URL => "varchar(255) NOT NULL default ''",
                self::LINK_SUFFIX_TITLE => "varchar(255) NOT NULL default ''",
                self::LINK_SUFFIX_TEXT => "varchar(255) NOT NULL default ''",
                self::LINK_SUFFIX_TARGET => "char(1) NOT NULL default ''",
            ];
        }

        return ['' => $this->sqlFor($type)];
    }

    /**
     * Ob die Spalte für diesen Suffix eine Checkbox (char(1), "0"/"1")
     * ist - relevant für DynamicFieldSchemaSync::fieldHasData(), die bei
     * Checkbox-Spalten auch den Wert "0" als "keine Daten" werten muss.
     */
    public function columnIsCheckbox(string $type, string $suffix): bool
    {
        if (self::CHECKBOX === $type && '' === $suffix) {
            return true;
        }

        return self::LINK === $type && self::LINK_SUFFIX_TARGET === $suffix;
    }

    /**
     * Baut die DCA-Felddefinition(en) für einen Feld-Builder-Eintrag.
     * Bei einfachen Typen genau ein Eintrag (Suffix ""), bei "Link" vier
     * zusammengehörige Teilfelder (siehe columnSqlTypes()) - alle vier
     * werden von DynamicFieldInjector gemeinsam, direkt hintereinander,
     * in die Palette der Zieltabelle eingehängt.
     *
     * @param array<string, mixed> $row Zeile aus tl_dynamic_field
     *
     * @return array<string, array<string, mixed>> Suffix => DCA-Felddefinition
     */
    public function buildDcaFields(array $row): array
    {
        if (self::LINK === (string) $row['fieldType']) {
            return $this->buildLinkDcaFields($row);
        }

        return ['' => $this->buildSimpleDcaField($row)];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function buildSimpleDcaField(array $row): array
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
            default => [
                'label' => $label,
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['tl_class' => 'w50', 'maxlength' => 255],
            ],
        };
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, array<string, mixed>> Suffix => DCA-Felddefinition
     */
    private function buildLinkDcaFields(array $row): array
    {
        $baseLabel = (string) $row['fieldLabel'];
        $description = '' !== (string) $row['fieldDescription']
            ? (string) $row['fieldDescription']
            : 'Über den Feld-Builder angelegtes Link-Feld.';
        $widthClass = $this->groupWidthClass(4);

        return [
            self::LINK_SUFFIX_URL => [
                'label' => [$baseLabel.' - Link', $description],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'url', 'tl_class' => $widthClass, 'placeholder' => 'https://... oder /interne-seite'],
            ],
            self::LINK_SUFFIX_TITLE => [
                'label' => [$baseLabel.' - Titel', 'Optionaler Title-/Tooltip-Text des Links.'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['tl_class' => $widthClass, 'maxlength' => 255],
            ],
            self::LINK_SUFFIX_TEXT => [
                'label' => [$baseLabel.' - Text', 'Sichtbarer Linktext bzw. Button-Beschriftung.'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['tl_class' => $widthClass, 'maxlength' => 255],
            ],
            self::LINK_SUFFIX_TARGET => [
                'label' => [$baseLabel.' - Neuer Tab', 'Link in neuem Tab öffnen (target="_blank").'],
                'exclude' => true,
                'inputType' => 'checkbox',
                'eval' => ['tl_class' => $widthClass.' m12'],
            ],
        ];
    }

    /**
     * Contao-Spaltenbreite für eine Gruppe von $count zusammengehörigen
     * Teilfeldern, die gemeinsam in einer Reihe stehen sollen: durch 4
     * teilbare Anzahl (4, 8, ...) -> w25, sonst durch 3 teilbare (3, 6,
     * 9, ...) -> w33, bei genau 2 Elementen -> w50. Allgemein gehalten
     * (nicht Link-spezifisch), falls ein künftiger weiterer
     * zusammengesetzter Feldtyp eine andere Elementanzahl braucht.
     */
    private function groupWidthClass(int $count): string
    {
        return match (true) {
            $count > 0 && 0 === $count % 4 => 'w25',
            $count > 0 && 0 === $count % 3 => 'w33',
            default => 'w50',
        };
    }

    /**
     * Liest die per listWizard erfassten Listeneinträge aus dem
     * serialisierten "selectOptions"-Feld (wie Contaos natives
     * "Aufzählung"-Inhaltselement, siehe tl_content.listitems). Seit
     * 2026-11-02 kein "wert:Label"-Freitext mehr - der eingegebene Text
     * ist zugleich gespeicherter Wert UND Label, ein separates
     * "wert1" ist damit obsolet.
     *
     * @return array<string, string> Wert => Label (identisch)
     */
    public function parseSelectOptions(string $raw): array
    {
        $options = [];

        foreach (StringUtil::deserialize($raw, true) as $item) {
            $item = trim((string) $item);

            if ('' === $item) {
                continue;
            }

            $options[$item] = $item;
        }

        return $options;
    }
}
