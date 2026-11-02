<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use App\Entity\AppUser;
use App\Service\Security\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Backup\BackupCodeManagerInterface;

/**
 * Recovery codes for scheb/2fa-backup-code: stored as keyed hashes (SecretBox::fingerprint), each usable once.
 * Spaces, dashes and case do not matter when a person types a code.
 */
final readonly class RecoveryCodeManager implements BackupCodeManagerInterface
{
    private const string PURPOSE = 'recovery-code';

    public function __construct(
        private SecretBox $secretBox,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $plainCodes shown once to the person, never stored
     */
    public function replace(AppUser $user, array $plainCodes): void
    {
        $user->replaceRecoveryCodeHashes(array_map($this->hash(...), $plainCodes));
    }

    public function isBackupCode(object $user, string $code): bool
    {
        return $user instanceof AppUser && $user->hasRecoveryCodeHash($this->hash($code));
    }

    public function invalidateBackupCode(object $user, string $code): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        $user->removeRecoveryCodeHash($this->hash($code));
        $this->entityManager->flush();
    }

    private function hash(string $code): string
    {
        return $this->secretBox->fingerprint(strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $code)), self::PURPOSE);
    }
}
