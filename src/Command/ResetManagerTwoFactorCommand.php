<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\AuditAction;
use App\Enum\AuditVia;
use App\Repository\AppUserRepository;
use App\Repository\WebauthnCredentialRepository;
use App\Security\PasswordRules;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validation;

/**
 * Last resort when the manager lost phone, passkeys and recovery codes (konzept-1 §5): only someone with
 * access to the server can run it. Removes app secret, recovery codes and passkeys; optionally sets a
 * new start password, because without e-mail the manager has no other way back.
 */
#[AsCommand(name: 'app:manager:reset-2fa', description: 'Setzt den zweiten Schritt des Managers zurück (Notfall, nur am Server)')]
final readonly class ResetManagerTwoFactorCommand
{
    public function __construct(
        private AppUserRepository $userRepository,
        private WebauthnCredentialRepository $credentialRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Zusätzlich ein neues Start-Passwort setzen', name: 'passwort')]
        bool $password = false,
    ): int {
        $manager = $this->userRepository->findManager();
        if (null === $manager) {
            $io->error('Es gibt noch keinen Manager. Zuerst: php bin/console app:setup');

            return Command::FAILURE;
        }

        $io->warning(\sprintf('Der zweite Schritt von „%s" wird entfernt: Authenticator-App, Wiederherstellungscodes und alle Passkeys.', $manager->getDisplayName()));
        if (!$io->askQuestion(new ConfirmationQuestion('Wirklich zurücksetzen? (ja/nein)', false, '/^(j|y)/i'))) {
            $io->text('Nichts geändert.');

            return Command::SUCCESS;
        }

        if ($password) {
            $new = (string) $io->askHidden('Neues Start-Passwort', static function (mixed $value): mixed {
                $violations = Validation::createValidator()->validate($value, PasswordRules::constraints());
                if (\count($violations) > 0) {
                    throw new \RuntimeException((string) $violations->get(0)->getMessage());
                }

                return $value;
            });
            if (!hash_equals($new, (string) $io->askHidden('Neues Start-Passwort wiederholen'))) {
                $io->error('Die beiden Passwörter sind nicht gleich. Es wurde nichts geändert.');

                return Command::FAILURE;
            }
            $manager->changePassword($this->passwordHasher->hashPassword($manager, $new), mustChange: true);
        }

        $manager->resetTwoFactor();
        $removedPasskeys = $this->credentialRepository->deleteForUser($manager);
        $this->entityManager->flush();
        $this->auditLogger->log(AuditAction::ZweifaktorZuruecksetzen, null, $manager, [
            'passkeys_entfernt' => $removedPasskeys,
            'neues_start_passwort' => $password,
        ], AuditVia::System);

        $io->success('Zurückgesetzt. Bei der nächsten Anmeldung richtet der Manager den zweiten Schritt neu ein.');

        return Command::SUCCESS;
    }
}
