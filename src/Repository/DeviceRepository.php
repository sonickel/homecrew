<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Device>
 */
final class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    /**
     * The device of a token cookie – also an unpaired one, so the tablet can say so.
     */
    public function findOneByTokenHash(string $tokenHash): ?Device
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }
}
