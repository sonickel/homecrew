<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShoppingItem>
 */
final class ShoppingItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingItem::class);
    }

    /**
     * @return list<ShoppingItem> oldest first – the order things were put on the list
     */
    public function findOpen(ShoppingList $list): array
    {
        return $this->findBy(['list' => $list, 'done' => false], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Entries ticked off since the point in time ("Abgehakt", to take back by mistake).
     *
     * @return list<ShoppingItem>
     */
    public function findCheckedSince(ShoppingList $list, \DateTimeImmutable $since): array
    {
        /** @var list<ShoppingItem> $items */
        $items = $this->createQueryBuilder('i')
            ->andWhere('i.list = :list')
            ->andWhere('i.done = true')
            ->andWhere('i.doneAt >= :since')
            ->setParameter('list', $list)
            ->setParameter('since', $since->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable')
            ->orderBy('i.doneAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /**
     * The open entry of that name (the column collation ignores case), to link a second report instead of adding it twice.
     */
    public function findOpenByName(ShoppingList $list, string $name): ?ShoppingItem
    {
        return $this->findOneBy(['list' => $list, 'done' => false, 'name' => $name]);
    }

    /**
     * @return array<int, int> list id => number of open entries
     */
    public function countOpenPerList(): array
    {
        /** @var list<array{list: int|string, entries: int|string}> $rows */
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.list) AS list', 'COUNT(i.id) AS entries')
            ->andWhere('i.done = false')
            ->groupBy('i.list')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['list']] = (int) $row['entries'];
        }

        return $counts;
    }

    /**
     * Entries added since the point in time, on active lists ("Neu seit gestern" at the team tablet).
     *
     * @return list<ShoppingItem>
     */
    public function findAddedSince(\DateTimeImmutable $since): array
    {
        /** @var list<ShoppingItem> $items */
        $items = $this->createQueryBuilder('i')
            ->join('i.list', 'l')->addSelect('l')
            ->andWhere('l.isActive = true')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('since', $since->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable')
            ->orderBy('l.sort', 'ASC')
            ->addOrderBy('l.name', 'ASC')
            ->addOrderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /**
     * Night job: entries ticked off before the point in time disappear from the lists.
     */
    public function deleteCheckedBefore(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('i')
            ->delete()
            ->andWhere('i.done = true')
            ->andWhere('i.doneAt < :before')
            ->setParameter('before', $before->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable')
            ->getQuery()
            ->execute();
    }
}
