<?php

declare(strict_types=1);

namespace App\Doctrine\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;

/**
 * Configured as doctrine.dbal.schema_manager_factory; HomeCrew only runs on MariaDB.
 */
final class MariaDbSchemaManagerFactory implements SchemaManagerFactory
{
    /**
     * @return AbstractSchemaManager<AbstractMySQLPlatform>
     */
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        $platform = $connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            throw new \LogicException(\sprintf('HomeCrew needs MariaDB, got platform "%s".', $platform::class));
        }

        return new MariaDbSchemaManager($connection, $platform);
    }
}
