<?php

declare(strict_types=1);

namespace App\Service\Schema;

/**
 * Result of comparing a migrated database with the reference schema.
 */
final readonly class SchemaComparison
{
    /**
     * @param list<string>                $identical      migrated tables that match the reference
     * @param array<string, list<string>> $differences    migrated tables that differ => readable differences
     * @param list<string>                $unknown        migrated tables that the reference does not know
     * @param list<string>                $notYetMigrated reference tables without a migration yet (expected until the end of Stufe 1)
     */
    public function __construct(
        public array $identical,
        public array $differences,
        public array $unknown,
        public array $notYetMigrated,
    ) {
    }

    public function isIdentical(): bool
    {
        return [] === $this->differences && [] === $this->unknown;
    }
}
