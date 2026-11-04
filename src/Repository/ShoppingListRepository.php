<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShoppingList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShoppingList>
 */
final class ShoppingListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingList::class);
    }

    /**
     * The lists everybody uses, in the order the admins chose.
     *
     * @return list<ShoppingList>
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['sort' => 'ASC', 'name' => 'ASC']);
    }
}
