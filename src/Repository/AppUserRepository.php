<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppUser;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppUser>
 */
final class AppUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppUser::class);
    }

    /**
     * The assisted person – there is at most one (unique index on manager_marker).
     */
    public function findManager(): ?AppUser
    {
        return $this->findOneBy(['role' => UserRole::Manager]);
    }
}
