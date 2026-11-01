<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\PhpIntegerMappingType;
use Doctrine\DBAL\Types\Type;

/**
 * TINYINT [UNSIGNED] as PHP int (small counters, months, the app_setting id, generated markers).
 *
 * DBAL has no tinyint type and reads every TINYINT column as boolean; TINYINT(1) stays boolean,
 * all other TINYINT columns are mapped to this type by {@see \App\Doctrine\Schema\MariaDbSchemaManager}.
 */
final class TinyIntType extends Type implements PhpIntegerMappingType
{
    public const string NAME = 'tinyint';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TINYINT'.(($column['unsigned'] ?? false) === true ? ' UNSIGNED' : '');
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return null === $value ? null : (int) $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return null === $value ? null : (int) $value;
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }
}
