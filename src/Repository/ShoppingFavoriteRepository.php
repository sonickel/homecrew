<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShoppingFavorite;
use App\Entity\ShoppingList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShoppingFavorite>
 */
final class ShoppingFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingFavorite::class);
    }

    /**
     * Favourites of the list and those for every list (without a list); all of them without a list given.
     *
     * @return list<ShoppingFavorite>
     */
    public function findForList(?ShoppingList $list = null): array
    {
        $query = $this->createQueryBuilder('f')
            ->orderBy('f.sort', 'ASC')
            ->addOrderBy('f.name', 'ASC');
        if (null !== $list) {
            $query->andWhere('f.list = :list OR f.list IS NULL')->setParameter('list', $list);
        }

        /** @var list<ShoppingFavorite> $favorites */
        $favorites = $query->getQuery()->getResult();

        return $favorites;
    }
}
