<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AppSetting;
use App\Entity\AppUser;
use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Every change of the settings lands in the audit log with old and new value – whatever page saved it.
 * Encrypted columns (*_enc) are only named, never their content.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class SettingsAuditListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(AppSetting::class);

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof AppSetting) {
                continue;
            }

            $changes = [];
            foreach ($unitOfWork->getEntityChangeSet($entity) as $property => $change) {
                if (!\is_array($change)) {
                    continue; // collections – the settings have none
                }
                $column = $metadata->getColumnName($property);
                $changes[$column] = str_ends_with($column, '_enc') ? 'geändert' : ['alt' => self::plain($change[0]), 'neu' => self::plain($change[1])];
            }
            if ([] === $changes) {
                continue;
            }

            $actor = $this->security->getUser();
            $entry = new AuditLog(
                AuditAction::EinstellungenAendern->value,
                $actor instanceof AppUser ? AuditVia::App : AuditVia::System,
                $actor instanceof AppUser ? $actor : null,
                null,
                'app_setting',
                $entity->getId(),
                ['felder' => $changes],
            );
            $entityManager->persist($entry);
            $unitOfWork->computeChangeSet($entityManager->getClassMetadata(AuditLog::class), $entry);
        }
    }

    private static function plain(mixed $value): string|int|float|bool|null
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            // TIME columns come with the date 1970-01-01
            $value instanceof \DateTimeInterface => $value->format('1970-01-01' === $value->format('Y-m-d') ? 'H:i:s' : 'Y-m-d H:i:s'),
            \is_scalar($value) || null === $value => $value,
            default => get_debug_type($value),
        };
    }
}
