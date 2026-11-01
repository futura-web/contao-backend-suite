<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DynamicField;

use Contao\DataContainer;
use Contao\Message;
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

        $targetTable = (string) $row['targetTable'];

        if (!\in_array($targetTable, $this->tableRegistry->getTables(), true)) {
            Message::addError(sprintf('Feld-Builder: "%s" ist keine bekannte Zieltabelle, Feld wurde nicht angelegt.', $targetTable));

            return;
        }

        if (DynamicFieldTableRegistry::CONTENT_ELEMENT_TABLE === $targetTable
            && !isset(DynamicFieldTableRegistry::CONTENT_ELEMENT_TYPES[(string) $row['contentElementType']])
        ) {
            Message::addError('Feld-Builder: Bitte einen Inhaltselement-Typ wählen, Feld wurde nicht angelegt.');

            return;
        }

        $fieldName = $this->resolveFieldName($row);

        if ($fieldName !== (string) $row['fieldName']) {
            $this->connection->update('tl_dynamic_field', ['fieldName' => $fieldName], ['id' => (int) $dc->id]);
        }

        $this->ensureColumn($targetTable, $fieldName, $row);
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
        if ('1' === (string) $value || !$dc->id) {
            return $value;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT active, targetTable, fieldName, fieldType FROM tl_dynamic_field WHERE id = ?',
            [(int) $dc->id],
        );

        if (false === $row || '1' !== (string) $row['active'] || '' === (string) $row['fieldName']) {
            // War vorher schon inaktiv, oder hat noch gar keine echte
            // Spalte (z.B. frisch dupliziert) - nichts zu schützen.
            return $value;
        }

        if ($this->fieldHasData((string) $row['targetTable'], (string) $row['fieldName'], (string) $row['fieldType'])) {
            throw new \RuntimeException(sprintf(
                'Dieses Feld enthält bereits Daten in "%s" und kann deshalb nicht deaktiviert werden - Redakteure würden sonst den Zugriff darauf verlieren, obwohl die Daten noch da sind.',
                $row['targetTable'],
            ));
        }

        return $value;
    }

    private function fieldHasData(string $table, string $fieldName, string $fieldType): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^dynField[A-Za-z0-9]+$/', $fieldName)) {
            // Sollte nach den Validierungen beim Anlegen nie vorkommen -
            // im Zweifel konservativ blockieren statt ungeprüft zuzulassen.
            return true;
        }

        if (!$this->columnExists($table, $fieldName)) {
            // Keine Spalte, keine Daten - nichts zu schützen. Kann
            // vorkommen, wenn die Spalte außerhalb des Bundles verloren
            // ging (z.B. durch ein volles "contao:migrate --with-deletes").
            // Die zuvor hier stehende Variante behandelte genau diesen
            // Fall faelschlich als "nicht pruefbar -> blockieren", was
            // ein Deaktivieren unmoeglich machte, obwohl nichts verloren
            // gehen konnte.
            return false;
        }

        $condition = match ($fieldType) {
            DynamicFieldTypeRegistry::GALLERY => "`$fieldName` IS NOT NULL AND `$fieldName` NOT IN ('', 'a:0:{}')",
            DynamicFieldTypeRegistry::CHECKBOX => "`$fieldName` IS NOT NULL AND `$fieldName` NOT IN ('', '0', 'a:0:{}')",
            default => "`$fieldName` IS NOT NULL AND `$fieldName` != ''",
        };

        try {
            $count = $this->connection->fetchOne("SELECT COUNT(*) FROM `{$table}` WHERE {$condition}");
        } catch (DbalException) {
            return true;
        }

        return (int) $count > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveFieldName(array $row): string
    {
        $existing = (string) ($row['fieldName'] ?? '');

        if ('' !== $existing) {
            return $existing;
        }

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

        try {
            if ($this->columnExists($table, $fieldName)) {
                return;
            }

            $sqlType = $this->typeRegistry->sqlFor((string) $row['fieldType']);

            $this->connection->executeStatement(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                $table,
                $fieldName,
                $sqlType,
            ));

            Message::addConfirmation(sprintf('Feld-Builder: Spalte "%s" wurde in "%s" angelegt.', $fieldName, $table));
        } catch (DbalException $exception) {
            Message::addInfo(sprintf(
                'Feld-Builder: Spalte "%s" konnte nicht automatisch in "%s" angelegt werden (%s). Bitte manuell aktualisieren: %s',
                $fieldName,
                $table,
                $exception->getMessage(),
                $this->sshMigrateHint,
            ));
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
