<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Schema;

use App\Service\Schema\SchemaComparator;
use PHPUnit\Framework\TestCase;

final class SchemaComparatorTest extends TestCase
{
    private const array REFERENCE = [
        'app_setting' => ['Spalte id' => 'Position 1: tinyint(3) unsigned NOT NULL DEFAULT 1', 'CHECK ck_setting_single' => '(Table) `id` = 1'],
        'app_user' => ['Spalte id' => 'Position 1: bigint(20) unsigned NOT NULL auto_increment'],
        'device' => ['Spalte id' => 'Position 1: bigint(20) unsigned NOT NULL auto_increment'],
    ];

    public function testIdenticalTablesPassAndMissingTablesAreOnlyCounted(): void
    {
        $migrated = [
            'app_setting' => self::REFERENCE['app_setting'],
            'app_user' => self::REFERENCE['app_user'],
            'doctrine_migration_versions' => ['Spalte version' => 'Position 1: varchar(191) NOT NULL'],
        ];

        $result = (new SchemaComparator())->compare(self::REFERENCE, $migrated, ['doctrine_migration_versions']);

        self::assertTrue($result->isIdentical());
        self::assertSame(['app_setting', 'app_user'], $result->identical);
        self::assertSame(['device'], $result->notYetMigrated);
    }

    public function testReportsChangedMissingAndAdditionalAspects(): void
    {
        $migrated = ['app_setting' => [
            'Spalte id' => 'Position 1: tinyint(4) NOT NULL DEFAULT 1',
            'Index extra' => 'BTREE (id)',
        ]];

        $result = (new SchemaComparator())->compare(self::REFERENCE, $migrated, []);

        self::assertFalse($result->isIdentical());
        self::assertSame([
            "Spalte id weicht ab\n  Referenz:  Position 1: tinyint(3) unsigned NOT NULL DEFAULT 1\n  Migration: Position 1: tinyint(4) NOT NULL DEFAULT 1",
            'CHECK ck_setting_single fehlt in der Migration (Referenz: (Table) `id` = 1)',
            'Index extra gibt es nur in der Migration (BTREE (id))',
        ], $result->differences['app_setting']);
    }

    public function testTableOutsideTheReferenceFails(): void
    {
        $result = (new SchemaComparator())->compare(self::REFERENCE, ['scratch' => ['Spalte id' => 'x']], []);

        self::assertFalse($result->isIdentical());
        self::assertSame(['scratch'], $result->unknown);
    }
}
