<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\AppUser;
use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes the log of sensitive actions (konzept-5 §4.4). Details are short facts only:
 * never IP addresses, entered texts, passwords, codes or secrets.
 *
 * log() flushes: the entry is saved together with the change it records (callers change the entity first,
 * then log), so a logged action never exists without its change and vice versa.
 */
final readonly class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $details
     */
    public function log(
        AuditAction $action,
        ?AppUser $actor,
        ?object $subject = null,
        array $details = [],
        AuditVia $via = AuditVia::App,
    ): void {
        $this->entityManager->persist(new AuditLog(
            $action->value,
            $via,
            $actor,
            null,
            null === $subject ? null : self::entityType($subject),
            null === $subject ? null : self::entityId($subject),
            [] === $details ? null : $details,
        ));
        $this->entityManager->flush();
    }

    /**
     * Table name of the subject as the log page shows it, e.g. "app_user".
     */
    private static function entityType(object $subject): string
    {
        $short = (new \ReflectionClass($subject))->getShortName();

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
    }

    private static function entityId(object $subject): ?int
    {
        return method_exists($subject, 'getId') ? $subject->getId() : null;
    }
}
