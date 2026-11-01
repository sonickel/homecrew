<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Type;

use App\Doctrine\Type\TinyIntType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\TestCase;

final class TinyIntTypeTest extends TestCase
{
    public function testDeclaresSignedAndUnsignedTinyInt(): void
    {
        $platform = new MariaDBPlatform();

        self::assertSame('TINYINT', (new TinyIntType())->getSQLDeclaration([], $platform));
        self::assertSame('TINYINT UNSIGNED', (new TinyIntType())->getSQLDeclaration(['unsigned' => true], $platform));
    }

    public function testConvertsBetweenIntAndDatabaseValue(): void
    {
        $platform = new MariaDBPlatform();
        $type = new TinyIntType();

        self::assertSame(3, $type->convertToPHPValue('3', $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertSame(5, $type->convertToDatabaseValue(5, $platform));
        self::assertSame(ParameterType::INTEGER, $type->getBindingType());
    }

    public function testDefaultValueIsWrittenWithoutQuotes(): void
    {
        // Introspected defaults arrive as string; both sides must render the same SQL, or diff reports a change
        $platform = new MariaDBPlatform();

        self::assertSame(' DEFAULT 3', $platform->getDefaultValueDeclarationSQL(['type' => new TinyIntType(), 'default' => '3']));
    }
}
