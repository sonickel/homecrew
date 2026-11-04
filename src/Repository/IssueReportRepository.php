<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IssueReport;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Enum\ReportVisibility;
use App\Service\Viewer\Viewer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Reports (konzept-13). What a viewer sees: reports for everybody – reports "nur für Admins" only admins
 * (App\Security\Voter\ReportVoter decides the same for single reports).
 *
 * @extends ServiceEntityRepository<IssueReport>
 */
final class IssueReportRepository extends ServiceEntityRepository
{
    public const string FILTER_OPEN = 'offen';
    public const string FILTER_CLOSED = 'abgeschlossen';
    public const string FILTER_ALL = 'alle';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueReport::class);
    }

    public function findOneByPublicId(string $publicId): ?IssueReport
    {
        return Uuid::isValid($publicId) ? $this->findOneBy(['publicId' => Uuid::fromString($publicId)]) : null;
    }

    public function findForTask(Task $task): ?IssueReport
    {
        return $this->findOneBy(['task' => $task]);
    }

    /**
     * Open reports the viewer sees, newest first ("Meldungen", suggestions and "Auch bemerkt").
     *
     * @return list<IssueReport>
     */
    public function findOpenVisibleTo(Viewer $viewer): array
    {
        $query = $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', ReportStatus::open())
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC');
        $this->whereVisible($query, $viewer);

        /** @var list<IssueReport> $reports */
        $reports = $query->getQuery()->getResult();

        return $reports;
    }

    /**
     * "Meldungen: 2 offen – 1 dringend" on "Heute" of the admins.
     *
     * @return array{open: int, urgent: int}
     */
    public function countOpen(): array
    {
        /** @var array{open: int|string, urgent: int|string|null} $row */
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS open', 'SUM(CASE WHEN r.urgent = true THEN 1 ELSE 0 END) AS urgent')
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', ReportStatus::open())
            ->getQuery()
            ->getSingleResult();

        return ['open' => (int) $row['open'], 'urgent' => (int) $row['urgent']];
    }

    /**
     * Report log of the admins: open, closed or all, optionally of one kind and since a day; newest first.
     *
     * @return list<IssueReport>
     */
    public function findLog(string $status, ?ReportKind $kind, ?\DateTimeImmutable $since, int $limit = 200): array
    {
        $query = $this->createQueryBuilder('r')
            ->leftJoin('r.createdBy', 'p')->addSelect('p')
            ->leftJoin('r.createdByDevice', 'd')->addSelect('d')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit);
        if (self::FILTER_OPEN === $status) {
            $query->andWhere('r.status IN (:status)')->setParameter('status', ReportStatus::open());
        } elseif (self::FILTER_CLOSED === $status) {
            $query->andWhere('r.status IN (:status)')->setParameter('status', [ReportStatus::Erledigt, ReportStatus::Verworfen]);
        }
        if (null !== $kind) {
            $query->andWhere('r.kind = :kind')->setParameter('kind', $kind);
        }
        if (null !== $since) {
            $query->andWhere('r.createdAt >= :since')->setParameter('since', $since->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable');
        }

        /** @var list<IssueReport> $reports */
        $reports = $query->getQuery()->getResult();

        return $reports;
    }

    /**
     * "Was fehlt oft?": titles reported at least twice since the day, most often first.
     *
     * @return list<array{title: string, count: int, first: \DateTimeImmutable, last: \DateTimeImmutable}>
     */
    public function findFrequent(\DateTimeImmutable $since, int $limit = 5): array
    {
        // The column collation ignores case: "Seife leer" and "seife leer" count together
        /** @var list<array{title: string, reports: int|string, first: string, last: string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('MIN(r.title) AS title', 'COUNT(r.id) AS reports', 'MIN(r.createdAt) AS first', 'MAX(r.createdAt) AS last')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('since', $since->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable')
            ->groupBy('r.title')
            ->having('COUNT(r.id) >= 2')
            ->orderBy('reports', 'DESC')
            ->addOrderBy('last', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $utc = new \DateTimeZone('UTC');

        return array_map(static fn (array $row): array => [
            'title' => $row['title'],
            'count' => (int) $row['reports'],
            'first' => new \DateTimeImmutable($row['first'], $utc),
            'last' => new \DateTimeImmutable($row['last'], $utc),
        ], $rows);
    }

    /**
     * Reports whose follow-up is this list entry (several reports may share one entry).
     *
     * @return list<IssueReport>
     */
    public function findByFollowUpItem(ShoppingItem $item): array
    {
        return $this->findBy(['followUpItem' => $item]);
    }

    /**
     * Open reports waiting for an entry that is still open on the list.
     *
     * @return list<IssueReport>
     */
    public function findOpenWithOpenEntryOn(ShoppingList $list): array
    {
        /** @var list<IssueReport> $reports */
        $reports = $this->createQueryBuilder('r')
            ->join('r.followUpItem', 'i')
            ->andWhere('i.list = :list')
            ->andWhere('i.done = false')
            ->andWhere('r.status IN (:open)')
            ->setParameter('list', $list)
            ->setParameter('open', ReportStatus::open())
            ->getQuery()
            ->getResult();

        return $reports;
    }

    /**
     * @return list<IssueReport>
     */
    public function findByFollowUpTask(Task $task): array
    {
        return $this->findBy(['followUpTask' => $task]);
    }

    /**
     * Plans with an open report on one of their occurrences that the viewer sees ("Meldung offen" in "Routinen & Wartung").
     *
     * @param list<TaskTemplate> $plans
     *
     * @return list<int> plan ids
     */
    public function findPlansWithOpenReports(array $plans, Viewer $viewer): array
    {
        if ([] === $plans) {
            return [];
        }
        $query = $this->createQueryBuilder('r')
            ->select('DISTINCT IDENTITY(t.template) AS plan')
            ->join('r.task', 't')
            ->andWhere('t.template IN (:plans)')
            ->andWhere('r.status IN (:open)')
            ->setParameter('plans', $plans)
            ->setParameter('open', ReportStatus::open());
        $this->whereVisible($query, $viewer);

        return array_values(array_map('intval', $query->getQuery()->getSingleColumnResult()));
    }

    /**
     * Reports for everybody since the point in time ("Neu seit gestern" at the team tablet).
     *
     * @return list<IssueReport>
     */
    public function findForAllSince(\DateTimeImmutable $since): array
    {
        /** @var list<IssueReport> $reports */
        $reports = $this->createQueryBuilder('r')
            ->andWhere('r.visibility = :all')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('all', ReportVisibility::Alle)
            ->setParameter('since', $since->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $reports;
    }

    /**
     * Retention job: removes the person from reports sent before the point in time (konzept-13 §8); the device stays.
     */
    public function anonymizeBefore(\DateTimeImmutable $before, \DateTimeImmutable $now): int
    {
        $utc = new \DateTimeZone('UTC');

        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.createdBy', ':nobody')
            ->set('r.anonymizedAt', ':now')
            ->andWhere('r.anonymizedAt IS NULL')
            ->andWhere('r.createdAt < :before')
            ->setParameter('nobody', null)
            ->setParameter('now', $now->setTimezone($utc), 'datetime_immutable')
            ->setParameter('before', $before->setTimezone($utc), 'datetime_immutable')
            ->getQuery()
            ->execute();
    }

    /**
     * Retention job: removes closed reports whose delete date has passed; open ones have none.
     */
    public function deleteExpired(\DateTimeImmutable $today): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.deleteAfter < :today')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->execute();
    }

    private function whereVisible(QueryBuilder $query, Viewer $viewer): void
    {
        if (!$viewer->isAdmin()) {
            $query->andWhere('r.visibility = :visible_all')->setParameter('visible_all', ReportVisibility::Alle);
        }
    }
}
