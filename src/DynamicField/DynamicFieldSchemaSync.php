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

        $fieldName = $this->resolveFieldName($row);

        if ($fieldName !== (string) $row['fieldName']) {
            $this->connection->update('tl_dynamic_field', ['fieldName' => $fieldName], ['id' => (int) $dc->id]);
        }

        $this->ensureColumn($targetTable, $fieldName, $row);
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

        $base = self::NAME_PREFIX.$this->studlify((string) $row['fieldLabel']);
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
