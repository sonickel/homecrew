<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmergencyContact;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmergencyContact>
 */
final class EmergencyContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmergencyContact::class);
    }

    /**
     * Contacts of the emergency page, grouped by category in the order of EmergencyCategory.
     *
     * @param bool $withAdminOnly true only for viewers with the admin permission (App\Security\Voter\AdminVoter)
     *
     * @return array<string, list<EmergencyContact>> category value => contacts by sort and name; empty categories left out
     */
    public function findForEmergencyPage(bool $withAdminOnly): array
    {
        $query = $this->createQueryBuilder('c')->orderBy('c.sort')->addOrderBy('c.name');
        if (!$withAdminOnly) {
            $query->andWhere('c.visibility = :everybody')->setParameter('everybody', EmergencyVisibility::Alle);
        }

        /** @var list<EmergencyContact> $contacts */
        $contacts = $query->getQuery()->getResult();

        return self::byCategory($contacts);
    }

    /**
     * What the screensaver of the team tablet shows without any login: visible to everybody and marked for the lock screen.
     *
     * @return list<EmergencyContact> in the order of the emergency page
     */
    public function findForLockScreen(): array
    {
        /** @var list<EmergencyContact> $contacts */
        $contacts = $this->createQueryBuilder('c')
            ->andWhere('c.visibility = :everybody')
            ->andWhere('c.showOnLockscreen = true')
            ->setParameter('everybody', EmergencyVisibility::Alle)
            ->orderBy('c.sort')
            ->addOrderBy('c.name')
            ->getQuery()
            ->getResult();

        return array_merge(...array_values(self::byCategory($contacts)));
    }

    /**
     * @param list<EmergencyContact> $contacts
     *
     * @return array<string, list<EmergencyContact>>
     */
    private static function byCategory(array $contacts): array
    {
        $groups = [];
        foreach (EmergencyCategory::cases() as $category) {
            $inCategory = array_values(array_filter($contacts, static fn (EmergencyContact $contact): bool => $contact->getCategory() === $category));
            if ([] !== $inCategory) {
                $groups[$category->value] = $inCategory;
            }
        }

        return $groups;
    }
}
