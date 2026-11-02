<?php

declare(strict_types=1);

namespace App\Service\Schema;

use Doctrine\DBAL\Connection;

/**
 * Reads the structure of one database from information_schema as comparable text,
 * per table: table options, columns, indexes, foreign keys and CHECK constraints.
 */
final readonly class SchemaSnapshotReader
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, array<string, string>> table name => (aspect => description)
     */
    public function read(string $schema): array
    {
        $snapshot = [];

        foreach ($this->fetch('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_COMMENT FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'', $schema) as $row) {
            $snapshot[$row['TABLE_NAME']]['Tabelle'] = \sprintf('%s, %s, Kommentar „%s“', $row['ENGINE'], $row['TABLE_COLLATION'], $row['TABLE_COMMENT']);
        }

        foreach ($this->fetch('SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA,
                GENERATION_EXPRESSION, COLLATION_NAME, COLUMN_COMMENT FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', $schema) as $row) {
            $snapshot[$row['TABLE_NAME']]['Spalte '.$row['COLUMN_NAME']] = implode(' ', array_filter([
                'Position '.$row['ORDINAL_POSITION'].':',
                $row['COLUMN_TYPE'],
                'YES' === $row['IS_NULLABLE'] ? 'NULL' : 'NOT NULL',
                null === $row['COLUMN_DEFAULT'] ? null : 'DEFAULT '.$row['COLUMN_DEFAULT'],
                '' === $row['EXTRA'] ? null : $row['EXTRA'],
                null === $row['GENERATION_EXPRESSION'] ? null : 'AS ('.$row['GENERATION_EXPRESSION'].')',
                $row['COLLATION_NAME'],
                '' === $row['COLUMN_COMMENT'] ? null : 'Kommentar „'.$row['COLUMN_COMMENT'].'“',
            ], static fn (?string $part): bool => null !== $part));
        }

        $indexes = [];
        foreach ($this->fetch('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART, INDEX_TYPE FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX', $schema) as $row) {
            $key = $row['TABLE_NAME']."\0".$row['INDEX_NAME'];
            $indexes[$key]['kind'] = ('0' === (string) $row['NON_UNIQUE'] ? 'UNIQUE ' : '').$row['INDEX_TYPE'];
            $indexes[$key]['columns'][] = $row['COLUMN_NAME'].(null === $row['SUB_PART'] ? '' : '('.$row['SUB_PART'].')');
        }
        foreach ($indexes as $key => $index) {
            [$table, $name] = explode("\0", $key);
            $snapshot[$table]['Index '.$name] = $index['kind'].' ('.implode(', ', $index['columns']).')';
        }

        $foreignKeys = [];
        foreach ($this->fetch('SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                r.UPDATE_RULE, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k
                JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                    AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION', $schema) as $row) {
            $key = $row['TABLE_NAME']."\0".$row['CONSTRAINT_NAME'];
            $foreignKeys[$key]['target'] = $row['REFERENCED_TABLE_NAME'];
            $foreignKeys[$key]['rules'] = 'ON DELETE '.$row['DELETE_RULE'].' ON UPDATE '.$row['UPDATE_RULE'];
            $foreignKeys[$key]['columns'][] = $row['COLUMN_NAME'];
            $foreignKeys[$key]['referenced'][] = $row['REFERENCED_COLUMN_NAME'];
        }
        foreach ($foreignKeys as $key => $foreignKey) {
            [$table, $name] = explode("\0", $key);
            $snapshot[$table]['Fremdschlüssel '.$name] = \sprintf('(%s) → %s (%s) %s', implode(', ', $foreignKey['columns']),
                $foreignKey['target'], implode(', ', $foreignKey['referenced']), $foreignKey['rules']);
        }

        foreach ($this->fetch('SELECT TABLE_NAME, CONSTRAINT_NAME, LEVEL, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = ?', $schema) as $row) {
            $snapshot[$row['TABLE_NAME']]['CHECK '.$row['CONSTRAINT_NAME']] = '('.$row['LEVEL'].') '.$row['CHECK_CLAUSE'];
        }

        ksort($snapshot);

        return $snapshot;
    }

    /**
     * @return list<array<string, string|null>> all values as text (the driver returns numbers as int)
     */
    private function fetch(string $sql, string $schema): array
    {
        return array_map(
            static fn (array $row): array => array_map(static fn (mixed $value): ?string => null === $value ? null : (string) $value, $row),
            $this->connection->fetchAllAssociative($sql, [$schema]),
        );
    }
}
