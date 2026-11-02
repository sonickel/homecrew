<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AppUser;
use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Changes of a person's master data and rights land in the audit log with old and new value – whatever page
 * saved them. Login data (passwords, PINs, codes), lock and leaving are logged by their own actions
 * (App\Service\Account\PersonAccess); personal settings like the font size are not logged.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class PersonAuditListener
{
    /** Columns that are logged. */
    public const array COLUMNS = ['role', 'is_admin', 'username', 'display_name', 'color', 'active_from'];

    public function __construct(
        private Security $security,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(AppUser::class);

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof AppUser) {
                continue;
            }

            $changes = [];
            foreach ($unitOfWork->getEntityChangeSet($entity) as $property => $change) {
                $column = \is_array($change) ? $metadata->getColumnName($property) : null;
                if (null === $column || !\in_array($column, self::COLUMNS, true)) {
                    continue;
                }
                $changes[$column] = ['alt' => self::plain($change[0]), 'neu' => self::plain($change[1])];
            }
            if ([] === $changes) {
                continue;
            }

            $actor = $this->security->getUser();
            $entry = new AuditLog(
                AuditAction::PersonAendern->value,
                $actor instanceof AppUser ? AuditVia::App : AuditVia::System,
                $actor instanceof AppUser ? $actor : null,
                null,
                'app_user',
                $entity->getId(),
                ['felder' => $changes],
            );
            $entityManager->persist($entry);
            $unitOfWork->computeChangeSet($entityManager->getClassMetadata(AuditLog::class), $entry);
        }
    }

    private static function plain(mixed $value): string|bool|null
    {
        return match (true) {
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            \is_bool($value), null === $value => $value,
            default => \is_scalar($value) ? (string) $value : get_debug_type($value),
        };
    }
}
