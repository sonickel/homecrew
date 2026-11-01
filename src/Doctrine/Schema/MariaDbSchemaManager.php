<?php

declare(strict_types=1);

namespace App\Doctrine\Schema;

use App\Doctrine\Type\TinyIntType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager;

/**
 * Reads TINYINT columns like the reference schema means them: TINYINT(1) is a boolean,
 * every other TINYINT (e.g. "tinyint(3) unsigned", generated markers "tinyint(4)") is an integer.
 * Without this, doctrine:migrations:diff would try to change those columns on every run.
 */
final class MariaDbSchemaManager extends MySQLSchemaManager
{
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $column = parent::_getPortableTableColumnDefinition($tableColumn);

        $tableColumn = array_change_key_case($tableColumn, \CASE_LOWER);
        $columnType = strtolower((string) $tableColumn['column_type']);

        if ('tinyint' === strtolower((string) $tableColumn['type']) && !str_starts_with($columnType, 'tinyint(1)')) {
            return $column->edit()->setTypeName(TinyIntType::NAME)->create();
        }

        return $column;
    }
}
