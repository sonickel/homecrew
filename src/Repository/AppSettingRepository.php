<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
final class AppSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }

    /**
     * The one settings row; it is created by the first migration.
     */
    public function get(): AppSetting
    {
        return $this->find(AppSetting::ID)
            ?? throw new \LogicException('The settings row is missing – run the database migrations.');
    }
}
