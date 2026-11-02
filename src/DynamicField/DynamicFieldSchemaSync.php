<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DynamicField;

use Contao\DataContainer;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Legt nach dem Speichern eines tl_dynamic_field-Eintrags die passende
 * DB-Spalte in der Zieltabelle an - direkt per Doctrine ALTER TABLE,
 * beschränkt auf genau diese eine Spalte, statt den vollen
 * "contao:migrate"-Lauf anzustoßen. Schlägt das fehl (z.B. fehlendes
 * ALTER-Recht), bleibt das Feld trotzdem aktiv im DCA eingehängt und es
 * erscheint nur ein Hinweis mit dem manuellen Fallback-Befehl.
 */
class DynamicFieldSchemaSync
{
    private const NAME_PREFIX = 'dynField';

    /**
     * Schnappschuss der Zeile VOR dem gerade laufenden Speichern, id =>
     * Zeile - siehe guardDeactivation() (wo er befüllt wird) und
     * onSubmit() (wo er für die "nur umbenennen/verschieben, solange
     * keine Daten existieren"-Logik konsumiert wird). Contao führt alle
     * save_callbacks VOR dem eigentlichen UPDATE aus, onsubmit_callback
     * aber DANACH - zu dem Zeitpunkt stehen in der DB schon die NEUEN
     * Werte, die alten (insbesondere die alte Zieltabelle/der alte
     * Feldtyp, falls diese sich gerade ändern) wären sonst nicht mehr
     * rekonstruierbar.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $preSaveRows = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly DynamicFieldTableRegistry $tableRegistry,
        private readonly DynamicFieldTypeRegistry $typeRegistry,
        #[Autowire('%contao_backend_suite.ssh_migrate_hint%')]
        private readonly string $sshMigrateHint,
    ) {
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM tl_dynamic_field WHERE id = ?', [(int) $dc->id]);

        if (false === $row) {
            return;
        }

        // Mehrfachauswahl seit 2026-11-02: EIN Feld kann jetzt gleich-
        // zeitig in mehreren Zieltabellen angelegt werden (je eine
        // eigene Spalte unter demselben technischen Namen) - unbekannte
        // Tabellen (z.B. veraltete Auswahl) werden stillschweigend
        // rausgefiltert statt die ganze Anlage zu blockieren.
        $targetTables = array_values(array_intersect(
            StringUtil::deserialize($row['targetTable'] ?? '', true),
            $this->tableRegistry->getTables(),
        ));

        if ([] === $targetTables) {
            Message::addError('Feld-Builder: Bitte mindestens eine bekannte Zieltabelle wählen, Feld wurde nicht angelegt.');

            return;
        }

        if (DynamicFieldTypeRegistry::SELECT === (string) $row['fieldType']
            && !isset(DynamicFieldTypeRegistry::OPTIONS_SOURCE_LABELS[(string) $row['optionsSource']])
        ) {
            Message::addError('Feld-Builder: Bitte eine Optionsquelle wählen, Feld wurde nicht angelegt.');

            return;
        }

        $oldRow = $this->preSaveRows[(int) $dc->id] ?? null;
        unset($this->preSaveRows[(int) $dc->id]);

        $oldTargetTables = null !== $oldRow ? StringUtil::deserialize($oldRow['targetTable'] ?? '', true) : [];
        $oldFieldType = null !== $oldRow ? (string) $oldRow['fieldType'] : (string) $row['fieldType'];
        $existingFieldName = (string) $row['fieldName'];

        // Der technische Name/Spaltenort darf nur neu bestimmt werden,
        // wenn er noch nie vergeben wurde, oder die bisherigen Spalten
        // (in JEDER bisherigen Zieltabelle) nachweislich noch keine
        // echten Daten enthalten (siehe Klassenkommentar zu
        // $preSaveRows + fieldHasDataAnywhere()) - sonst bleibt alles
        // wie gehabt für immer eingefroren.
        $mayRederive = '' === $existingFieldName || (
            null !== $oldRow && !$this->fieldHasDataAnywhere($oldTargetTables, $existingFieldName, $oldFieldType)
        );

        $fieldName = $mayRederive ? $this->deriveFieldName($row) : $existingFieldName;
        $nameChanged = $fieldName !== $existingFieldName;
        $typeChanged = $oldFieldType !== (string) $row['fieldType'];

        // Für jede ALTE Zieltabelle, die entweder (a) jetzt nicht mehr
        // ausgewählt ist, oder (b) sich Name/Typ geändert haben (dann
        // muss auch in einer weiterhin ausgewählten Tabelle die alte
        // Spalte weichen) - aber nur, wenn mayRederive (sonst ist
        // wegen guardImmutable() ohnehin nichts an targetTable/
        // fieldType/customFieldName wirklich geändert worden).
        if ($mayRederive && null !== $oldRow && '' !== $existingFieldName) {
            foreach ($oldTargetTables as $oldTable) {
                if ($nameChanged || $typeChanged || !\in_array($oldTable, $targetTables, true)) {
                    $this->dropColumnsIfEmpty((string) $oldTable, $existingFieldName, $oldFieldType);
                }
            }
        }

        if ($nameChanged) {
            $this->connection->update('tl_dynamic_field', ['fieldName' => $fieldName], ['id' => (int) $dc->id]);
        }

        foreach ($targetTables as $targetTable) {
            $this->ensureColumn((string) $targetTable, $fieldName, $row);
        }
    }

    /**
     * save_callback für das "Aktiv"-Feld: verhindert, dass ein Feld
     * deaktiviert wird (= aus der Zieltabelle verschwindet), solange
     * dort bereits echte Daten drinstehen - sonst würden Redakteure den
     * Zugriff auf bereits erfasste Inhalte verlieren, ohne dass die
     * Daten selbst gelöscht würden (die Spalte bleibt ja erhalten,
     * siehe die "nur deaktivieren, nie löschen"-Regel).
     */
    public function guardDeactivation(mixed $value, DataContainer $dc): mixed
    {
        if (!$dc->id) {
            return $value;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM tl_dynamic_field WHERE id = ?', [(int) $dc->id]);

        // "active" ist immer Teil der Palette und wird deshalb bei JEDEM
        // Speichern validiert, unabhängig davon, ob sich der Wert
        // tatsächlich ändert - genau deshalb eignet sich dieser Callback
        // als zuverlässiger Ort für den Vor-Speichern-Schnappschuss (vgl.
        // Klassenkommentar zu $preSaveRows).
        if (false !== $row) {
            $this->preSaveRows[(int) $dc->id] = $row;
        }

        if ('1' === (string) $value || false === $row || '1' !== (string) $row['active'] || '' === (string) $row['fieldName']) {
            // War vorher schon inaktiv, oder hat noch gar keine echte
            // Spalte (z.B. frisch dupliziert) - nichts zu schützen.
            return $value;
        }

        $tables = StringUtil::deserialize($row['targetTable'] ?? '', true);

        if ($this->fieldHasDataAnywhere($tables, (string) $row['fieldName'], (string) $row['fieldType'])) {
            throw new \RuntimeException(sprintf(
                'Dieses Feld enthält bereits Daten in "%s" und kann deshalb nicht deaktiviert werden - Redakteure würden sonst den Zugriff darauf verlieren, obwohl die Daten noch da sind.',
                implode(', ', $tables),
            ));
        }

        return $value;
    }

    /**
     * ondelete_callback: ein Feld-Eintrag darf gelöscht werden, solange
     * seine Spalte in der Zieltabelle keine echten Daten enthält -
     * dieselbe Prüfung wie bei guardDeactivation(). Die Spalte selbst
     * wird dabei NIE gedroppt, nur der Konfigurationseintrag entfernt -
     * eine eventuell doch vorhandene, leere Spalte bleibt harmlos liegen
     * (siehe die etablierte "nie automatisch droppen"-Regel).
     */
    public function guardDeletion(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT targetTable, fieldName, fieldType FROM tl_dynamic_field WHERE id = ?',
            [(int) $dc->id],
        );

        if (false === $row || '' === (string) $row['fieldName']) {
            return;
        }

        $tables = StringUtil::deserialize($row['targetTable'] ?? '', true);

        if ($this->fieldHasDataAnywhere($tables, (string) $row['fieldName'], (string) $row['fieldType'])) {
            throw new \RuntimeException(sprintf(
                'Dieses Feld enthält bereits Daten in "%s" und kann deshalb nicht gelöscht werden - bitte stattdessen deaktivieren, oder die Daten zuerst anderweitig sichern.',
                implode(', ', $tables),
            ));
        }
    }

    /**
     * Public, da auch DynamicFieldAdminFormInjector::guardImmutable()
     * dieselbe Prüfung braucht: Zieltabelle/Feldtyp/etc. dürfen frei
     * korrigiert werden, solange die Zielspalte noch keine echten Daten
     * enthält - erst sobald wirklich etwas drinsteht, lohnt sich der
     * Schutz vor einem verwaisenden Datensatz.
     */
    public function fieldHasData(string $table, string $fieldName, string $fieldType): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^dynField[A-Za-z0-9]+$/', $fieldName)) {
            // Sollte nach den Validierungen beim Anlegen nie vorkommen -
            // im Zweifel konservativ blockieren statt ungeprüft zuzulassen.
            return true;
        }

        // Bei zusammengesetzten Typen (aktuell nur "Link") zählt JEDE der
        // zugehörigen Spalten (URL/Titel/Text/Blank-Checkbox) - sobald
        // auch nur eine davon befüllt ist, gilt das Feld als "hat Daten".
        $conditions = [];

        foreach (array_keys($this->typeRegistry->columnSqlTypes($fieldType)) as $suffix) {
            $columnName = $fieldName.$suffix;

            if (!$this->columnExists($table, $columnName)) {
                // Keine Spalte, keine Daten - nichts zu schützen. Kann
                // vorkommen, wenn die Spalte außerhalb des Bundles
                // verloren ging (z.B. durch ein volles "contao:migrate
                // --with-deletes"). Die zuvor hier stehende Variante
                // behandelte genau diesen Fall faelschlich als "nicht
                // pruefbar -> blockieren", was ein Deaktivieren
                // unmoeglich machte, obwohl nichts verloren gehen konnte.
                continue;
            }

            $conditions[] = match (true) {
                DynamicFieldTypeRegistry::GALLERY === $fieldType => "(`$columnName` IS NOT NULL AND `$columnName` NOT IN ('', 'a:0:{}'))",
                $this->typeRegistry->columnIsCheckbox($fieldType, $suffix) => "(`$columnName` IS NOT NULL AND `$columnName` NOT IN ('', '0', 'a:0:{}'))",
                default => "(`$columnName` IS NOT NULL AND `$columnName` != '')",
            };
        }

        if ([] === $conditions) {
            return false;
        }

        try {
            $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, implode(' OR ', $conditions)));
        } catch (DbalException) {
            return true;
        }

        return (int) $count > 0;
    }

    /**
     * Wie fieldHasData(), aber über MEHRERE Zieltabellen gleichzeitig
     * (Mehrfachauswahl seit 2026-11-02) - "hat Daten" sobald auch nur
     * EINE der Tabellen welche enthält.
     *
     * @param list<string> $tables
     */
    public function fieldHasDataAnywhere(array $tables, string $fieldName, string $fieldType): bool
    {
        foreach ($tables as $table) {
            if ($this->fieldHasData((string) $table, $fieldName, $fieldType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Leitet einen frischen technischen Namen aus "Eigene Bezeichnung"
     * (falls gesetzt) bzw. sonst "Name" ab - aufgerufen von onSubmit(),
     * wenn entweder noch gar keiner vergeben ist (Erstanlage), oder die
     * bisherige Spalte nachweislich noch keine echten Daten enthält
     * (siehe dortiges $mayRederive) und ein Umbenennen deshalb gefahrlos
     * möglich ist.
     *
     * @param array<string, mixed> $row
     */
    private function deriveFieldName(array $row): string
    {
        $custom = (string) ($row['customFieldName'] ?? '');
        $base = self::NAME_PREFIX.$this->studlify('' !== $custom ? $custom : (string) $row['fieldLabel']);
        $name = $base;
        $suffix = 1;

        while ($this->fieldNameTaken($name, (int) $row['id'])) {
            $name = $base.(++$suffix);
        }

        return $name;
    }

    private function fieldNameTaken(string $name, int $ownId): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_dynamic_field WHERE fieldName = ? AND id != ?',
            [$name, $ownId],
        );

        return (int) $count > 0;
    }

    /**
     * Baut aus dem frei eingegebenen Label einen gültigen, mit
     * "dynField" präfixten technischen Feldnamen (z.B. "Zusatz Info!"
     * -> "dynFieldZusatzInfo").
     */
    private function studlify(string $label): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]+/', ' ', $label) ?? '';
        $parts = array_filter(explode(' ', $clean));
        $studly = implode('', array_map(static fn (string $part): string => ucfirst(strtolower($part)), $parts));

        if ('' === $studly) {
            $studly = 'Field';
        }

        return preg_match('/^[0-9]/', $studly) ? 'F'.$studly : $studly;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function ensureColumn(string $table, string $fieldName, array $row): void
    {
        if (!preg_match('/^dynField[A-Za-z0-9]+$/', $fieldName) || !preg_match('/^[a-z0-9_]+$/', $table)) {
            Message::addError('Feld-Builder: ungültiger Tabellen- oder Feldname, Spalte wurde nicht angelegt.');

            return;
        }

        // Bei zusammengesetzten Typen (aktuell nur "Link") mehrere
        // Spalten anlegen (fieldName + jeweiliger Suffix) - bei allen
        // anderen Typen liefert columnSqlTypes() genau einen Eintrag mit
        // Suffix "", also unverändertes Verhalten.
        foreach ($this->typeRegistry->columnSqlTypes((string) $row['fieldType']) as $suffix => $sqlType) {
            $columnName = $fieldName.$suffix;

            try {
                if ($this->columnExists($table, $columnName)) {
                    continue;
                }

                $this->connection->executeStatement(sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                    $table,
                    $columnName,
                    $sqlType,
                ));

                Message::addConfirmation(sprintf('Feld-Builder: Spalte "%s" wurde in "%s" angelegt.', $columnName, $table));
            } catch (DbalException $exception) {
                Message::addInfo(sprintf(
                    'Feld-Builder: Spalte "%s" konnte nicht automatisch in "%s" angelegt werden (%s). Bitte manuell aktualisieren: %s',
                    $columnName,
                    $table,
                    $exception->getMessage(),
                    $this->sshMigrateHint,
                ));
            }
        }
    }

    /**
     * Entfernt die Spalte(n) eines Feldes, dessen technischer Name/Ort
     * sich gerade ändert (siehe onSubmit()) - der neue Name bekommt
     * gleich danach über ensureColumn() seine eigene(n) frische(n)
     * Spalte(n), die alte(n) würde(n) sonst als ungenutzte Leiche(n) in
     * der (alten) Zieltabelle liegen bleiben. Prüft NOCHMALS selbst, ob
     * wirklich keine Daten drinstehen (nie blind auf den Aufrufer
     * vertrauen) - verstößt nicht gegen die "nie automatisch droppen"-
     * Regel, die sich ausdrücklich nur auf Spalten MIT echten Daten
     * bezieht.
     */
    private function dropColumnsIfEmpty(string $table, string $fieldName, string $fieldType): void
    {
        if (!preg_match('/^dynField[A-Za-z0-9]+$/', $fieldName) || !preg_match('/^[a-z0-9_]+$/', $table)) {
            return;
        }

        if ($this->fieldHasData($table, $fieldName, $fieldType)) {
            return;
        }

        foreach (array_keys($this->typeRegistry->columnSqlTypes($fieldType)) as $suffix) {
            $columnName = $fieldName.$suffix;

            if (!$this->columnExists($table, $columnName)) {
                continue;
            }

            try {
                $this->connection->executeStatement(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $columnName));
            } catch (DbalException) {
                // Konservativ ignorieren - die neue Spalte wird trotzdem
                // angelegt, die alte (nachweislich leere) bleibt dann
                // halt ungenutzt liegen statt den Rename abzubrechen.
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        foreach ($this->connection->createSchemaManager()->listTableColumns($table) as $existingColumn) {
            if (0 === strcasecmp($existingColumn->getName(), $column)) {
                return true;
            }
        }

        return false;
    }
}
