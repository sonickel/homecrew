<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppUser;
use App\Entity\Notification;
use App\Enum\NotificationKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
final class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @param NotificationKind|null $hidden notifications of this kind do not count (App\Service\Notification\Inbox)
     */
    public function countUnread(AppUser $person, ?NotificationKind $hidden = null): int
    {
        $query = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :person')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('person', $person);
        self::hide($query, $hidden);

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Newest first; unread only for "Neu seit deinem letzten Besuch".
     *
     * @return list<Notification>
     */
    public function findForPerson(AppUser $person, ?NotificationKind $hidden = null, bool $unreadOnly = false, ?int $limit = null): array
    {
        $query = $this->createQueryBuilder('n')
            ->andWhere('n.user = :person')
            ->setParameter('person', $person)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit);
        self::hide($query, $hidden);
        if ($unreadOnly) {
            $query->andWhere('n.readAt IS NULL');
        }

        /** @var list<Notification> $notifications */
        $notifications = $query->getQuery()->getResult();

        return $notifications;
    }

    /**
     * The unread notification about this page (a list), to add another entry to it.
     */
    public function findUnreadWithLink(AppUser $person, string $link): ?Notification
    {
        /** @var Notification|null $notification */
        $notification = $this->createQueryBuilder('n')
            ->andWhere('n.user = :person')
            ->andWhere('n.link = :link')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('person', $person)
            ->setParameter('link', $link)
            ->orderBy('n.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $notification;
    }

    public function markAllRead(AppUser $person, ?NotificationKind $hidden, \DateTimeImmutable $at): int
    {
        $query = $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':at')
            ->andWhere('n.user = :person')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('at', $at->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable')
            ->setParameter('person', $person);
        self::hide($query, $hidden);

        return $query->getQuery()->execute();
    }

    private static function hide(QueryBuilder $query, ?NotificationKind $hidden): void
    {
        if (null !== $hidden) {
            $query->andWhere('n.kind <> :hidden')->setParameter('hidden', $hidden);
        }
    }

    /**
     * Retention job: removes notifications whose delete date has passed.
     */
    public function deleteExpired(\DateTimeImmutable $today): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.deleteAfter < :today')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->execute();
    }
}
