<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldSchemaSync;
use Futuraweb\ContaoBackendSuite\DynamicField\DynamicFieldTableRegistry;

/**
 * Hängt die service-abhängigen Teile des Feld-Builder-eigenen Formulars
 * (tl_dynamic_field) ein: welche Zieltabellen/Abschnitte zur Auswahl
 * stehen (DynamicFieldTableRegistry), und das Anlegen der DB-Spalte nach
 * dem Speichern (DynamicFieldSchemaSync). Alles andere an diesem
 * Formular steht bereits statisch in contao/dca/tl_dynamic_field.php.
 */
#[AsHook('loadDataContainer')]
class DynamicFieldAdminFormInjector
{
    /**
     * Felder, die nach dem allerersten Speichern (sobald "fieldName"
     * feststeht) nicht mehr geändert werden dürfen - serverseitig
     * durchgesetzt per save_callback, siehe guardImmutable(). Die
     * "readonly"-Eval-Option allein reicht NICHT: Contao rendert sie bei
     * inputType "select" zwar als HTML-Attribut, aber "readonly" hat
     * dort schlicht keine Wirkung (nur "disabled" würde den Browser am
     * Ändern hindern, das würde das Feld aber auch nicht mehr
     * mitsenden) - am 2026-11-02 live beobachtet: "fieldType" ließ sich
     * trotz readonly-Markierung ganz normal im Browser umstellen und
     * wurde anstandslos gespeichert.
     *
     * @var list<string>
     */
    private const IMMUTABLE_AFTER_CREATION = [
        'targetTable', 'fieldType', 'contentElementType', 'optionsSource', 'newLegendTitle', 'customFieldName',
    ];

    public function __construct(
        private readonly DynamicFieldTableRegistry $tableRegistry,
        private readonly DynamicFieldSchemaSync $schemaSync,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ('tl_dynamic_field' !== $table) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_dynamic_field'];

        $dca['fields']['targetTable']['options_callback'] = function (): array {
            return $this->tableRegistry->getTableOptions();
        };

        $dca['fields']['legend']['options_callback'] = function (DataContainer $dc): array {
            $targetTable = Input::post('targetTable');

            if (!\is_string($targetTable) || '' === $targetTable) {
                $record = $dc->activeRecord;
                $targetTable = \is_object($record) ? (string) ($record->targetTable ?? '') : '';
            }

            return $this->tableRegistry->getLegendOptions('' !== $targetTable ? $targetTable : null);
        };

        $dca['fields']['active']['save_callback'][] = function (mixed $value, DataContainer $dc): mixed {
            return $this->schemaSync->guardDeactivation($value, $dc);
        };

        foreach (self::IMMUTABLE_AFTER_CREATION as $field) {
            $dca['fields'][$field]['save_callback'][] = function (mixed $value, DataContainer $dc) use ($field): mixed {
                return $this->guardImmutable($field, $value, $dc);
            };
        }

        $dca['config']['onload_callback'][] = function (DataContainer|null $dc = null): void {
            $this->lockAfterCreation($dc);
        };

        $dca['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            $this->schemaSync->onSubmit($dc);
        };

        $dca['config']['ondelete_callback'][] = function (DataContainer $dc): void {
            $this->schemaSync->guardDeletion($dc);
        };

        $dca['config']['oncopy_callback'][] = function (int $insertId): void {
            $this->resetCopiedField($insertId);
        };
    }

    /**
     * Zieltabelle und Feldtyp dürfen nach dem ersten erfolgreichen
     * Speichern (sobald "fieldName" feststeht und die Spalte existiert)
     * nicht mehr geändert werden - sonst würde das DCA auf eine andere
     * Tabelle/einen anderen Typ zeigen, während die tatsächliche
     * DB-Spalte noch in der alten Tabelle mit dem alten Typ liegt, was
     * beim nächsten Speichern eines Datensatzes dort zu einem
     * SQL-Fehler ("Unknown column") führen würde.
     *
     * "Titel des neuen Abschnitts" aus demselben Grund gesperrt: Der
     * Titel bestimmt direkt den Namen des Palette-Abschnitts (siehe
     * DynamicFieldInjector::legendKeyForTitle()) - würde er nachträglich
     * geändert, würde das Feld beim nächsten Laden der Zieltabelle in
     * einen ANDEREN (neuen) Abschnitt wandern, statt im bestehenden zu
     * bleiben, was für Redakteure, die sich an die bisherige Stelle
     * gewöhnt haben, überraschend wäre.
     */
    private function lockAfterCreation(DataContainer|null $dc): void
    {
        if (!$dc instanceof DataContainer) {
            return;
        }

        $record = $dc->activeRecord;

        if (!\is_object($record) || '' === (string) ($record->fieldName ?? '')) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_dynamic_field'];
        $dca['fields']['targetTable']['eval']['readonly'] = true;
        $dca['fields']['fieldType']['eval']['readonly'] = true;
        $dca['fields']['newLegendTitle']['eval']['readonly'] = true;
        // Gleicher Grund wie bei targetTable: bestimmt, in welche Palette
        // (welcher Inhaltselement-Typ) injiziert wird - nachträglich
        // geändert würde das Feld aus der Form des bisherigen Typs
        // verschwinden, ohne dass jemand das erwartet.
        $dca['fields']['contentElementType']['eval']['readonly'] = true;
        // "optionsSource" bestimmt, wie gespeicherte Werte zu
        // interpretieren sind (freier Text vs. eine ID aus tl_module/
        // tl_form/tl_content) - ein nachträglicher Wechsel würde bereits
        // gespeicherte Werte falsch auslegen.
        $dca['fields']['optionsSource']['eval']['readonly'] = true;
        // "customFieldName" bestimmt den technischen Namen nur beim
        // ALLERERSTEN Speichern (siehe DynamicFieldSchemaSync::
        // resolveFieldName(), bricht bei bereits gesetztem "fieldName"
        // sofort ab) - eine spätere Änderung hätte also ohnehin keinen
        // Effekt mehr auf den echten Spaltennamen. Gesperrt, damit das
        // nicht den Eindruck erweckt, man könnte die Spalte so umbenennen.
        $dca['fields']['customFieldName']['eval']['readonly'] = true;
    }

    /**
     * Echte, serverseitige Durchsetzung von IMMUTABLE_AFTER_CREATION -
     * siehe Klassenkommentar dort, warum die reine "readonly"-Eval-
     * Option dafür nicht ausreicht. Wirft bei einem Änderungsversuch
     * eine Exception, die Contao als normalen Formular-Validierungs-
     * fehler anzeigt (wie guardDeactivation() in DynamicFieldSchemaSync).
     */
    private function guardImmutable(string $field, mixed $value, DataContainer $dc): mixed
    {
        if (!$dc->id) {
            return $value;
        }

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT fieldName, %s AS currentValue FROM tl_dynamic_field WHERE id = ?', $field),
            [(int) $dc->id],
        );

        if (false === $row || '' === (string) $row['fieldName']) {
            // Noch nicht angelegt (kein fieldName) - frei änderbar.
            return $value;
        }

        if ((string) $value !== (string) $row['currentValue']) {
            $label = $GLOBALS['TL_LANG']['tl_dynamic_field'][$field][0] ?? $field;

            throw new \RuntimeException(sprintf('"%s" kann nach dem ersten Speichern nicht mehr geändert werden.', $label));
        }

        return $value;
    }

    /**
     * Ein dupliziertes Feld zeigt sonst (readonly, siehe oben) weiterhin
     * auf denselben technischen Namen wie das Original und wird beim
     * Laden der Zieltabelle stillschweigend übersprungen (schon
     * belegter Feldname) - die Kopie wäre ein wirkungsloses Phantom.
     * Stattdessen den Namen zurücksetzen, damit die Kopie beim nächsten
     * Speichern ganz normal ihren eigenen Namen/ihre eigene Spalte
     * bekommt.
     */
    private function resetCopiedField(int $insertId): void
    {
        $this->connection->update('tl_dynamic_field', ['fieldName' => ''], ['id' => $insertId]);
    }
}
