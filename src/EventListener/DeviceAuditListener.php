<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AppUser;
use App\Entity\AuditLog;
use App\Entity\Device;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Changes of a tablet (name, mode, screensaver, font size) land in the audit log with old and new value.
 * Pairing and unpairing are logged by their own actions (App\Service\Tablet\DevicePairing); "last seen" is not logged.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class DeviceAuditListener
{
    /** Columns that are logged. */
    public const array COLUMNS = ['name', 'mode', 'dim_after_seconds', 'font_size'];

    public function __construct(
        private Security $security,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(Device::class);

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Device) {
                continue;
            }

            $changes = [];
            foreach ($unitOfWork->getEntityChangeSet($entity) as $property => $change) {
                $column = \is_array($change) ? $metadata->getColumnName($property) : null;
                if (null === $column || !\in_array($column, self::COLUMNS, true)) {
                    continue;
                }
                $changes[$column] = [
                    'alt' => $change[0] instanceof \BackedEnum ? $change[0]->value : $change[0],
                    'neu' => $change[1] instanceof \BackedEnum ? $change[1]->value : $change[1],
                ];
            }
            if ([] === $changes) {
                continue;
            }

            $actor = $this->security->getUser();
            $entry = new AuditLog(
                AuditAction::GeraetAendern->value,
                $actor instanceof AppUser ? AuditVia::App : AuditVia::System,
                $actor instanceof AppUser ? $actor : null,
                null,
                'device',
                $entity->getId(),
                ['felder' => $changes],
            );
            $entityManager->persist($entry);
            $unitOfWork->computeChangeSet($entityManager->getClassMetadata(AuditLog::class), $entry);
        }
    }
}
