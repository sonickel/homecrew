<?php

declare(strict_types=1);

namespace App\Service\Schema;

/**
 * Compares two snapshots of {@see SchemaSnapshotReader}: every migrated table must be identical
 * to the same table in the reference (docs/konzept/konzept-6-datenbank.md §6).
 */
final class SchemaComparator
{
    /**
     * @param array<string, array<string, string>> $reference
     * @param array<string, array<string, string>> $migrated
     * @param list<string>                         $technicalTables tables outside the reference, e.g. doctrine_migration_versions
     */
    public function compare(array $reference, array $migrated, array $technicalTables): SchemaComparison
    {
        $identical = [];
        $differences = [];
        $unknown = [];

        foreach ($migrated as $table => $aspects) {
            if (\in_array($table, $technicalTables, true)) {
                continue;
            }
            if (!isset($reference[$table])) {
                $unknown[] = $table;
                continue;
            }

            $tableDifferences = $this->compareTable($reference[$table], $aspects);
            if ([] === $tableDifferences) {
                $identical[] = $table;
            } else {
                $differences[$table] = $tableDifferences;
            }
        }

        $notYetMigrated = array_values(array_diff(array_keys($reference), array_keys($migrated)));

        return new SchemaComparison($identical, $differences, $unknown, $notYetMigrated);
    }

    /**
     * @param array<string, string> $reference
     * @param array<string, string> $migrated
     *
     * @return list<string>
     */
    private function compareTable(array $reference, array $migrated): array
    {
        $differences = [];
        foreach ($reference as $aspect => $expected) {
            $actual = $migrated[$aspect] ?? null;
            if (null === $actual) {
                $differences[] = \sprintf('%s fehlt in der Migration (Referenz: %s)', $aspect, $expected);
            } elseif ($actual !== $expected) {
                $differences[] = \sprintf("%s weicht ab\n  Referenz:  %s\n  Migration: %s", $aspect, $expected, $actual);
            }
        }
        foreach (array_diff_key($migrated, $reference) as $aspect => $actual) {
            $differences[] = \sprintf('%s gibt es nur in der Migration (%s)', $aspect, $actual);
        }

        return $differences;
    }
}
