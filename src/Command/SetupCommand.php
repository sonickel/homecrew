<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use App\Enum\UserRole;
use App\Repository\AppUserRepository;
use App\Security\PasswordRules;
use App\Service\Audit\AuditLogger;
use App\Service\Household\HouseholdContext;
use App\Service\Security\SecretBox;
use App\Service\Security\SecretBoxException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validation;

/**
 * First start: creates the manager (the assisted person, always admin) with a start password that must be
 * changed at the first login. Refuses to run once there is a manager (konzept-5 §1 "Einrichtung").
 */
#[AsCommand(name: 'app:setup', description: 'Legt beim ersten Start den Manager an (Benutzername, Anzeigename, Start-Passwort)')]
final readonly class SetupCommand
{
    public function __construct(
        private AppUserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private HouseholdContext $household,
        private AuditLogger $auditLogger,
        private SecretBox $secretBox,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        // The second factor is stored encrypted – without the master key the first login would fail later
        try {
            $this->secretBox->decrypt($this->secretBox->encrypt('probe'));
        } catch (SecretBoxException) {
            $io->error('Der Hauptschlüssel APP_ENCRYPTION_KEY fehlt oder ist kürzer als 32 Zeichen. Bitte zuerst setzen (siehe .env).');

            return Command::FAILURE;
        }

        if (null !== $this->userRepository->findManager()) {
            $io->error('Es gibt schon einen Manager. Die Einrichtung läuft nur einmal.');

            return Command::FAILURE;
        }

        $io->title('HomeCrew einrichten');
        $io->text('Der Manager ist die assistierte Person und immer Admin. Das Start-Passwort muss bei der ersten Anmeldung geändert werden.');

        $username = (string) $io->ask('Benutzername (Kleinbuchstaben, Ziffern, Punkt, Minus, Unterstrich)', null, self::validator([
            new NotBlank(),
            new Regex('/^[a-z0-9._-]{3,50}$/', 'Nur a–z, 0–9, Punkt, Minus und Unterstrich, 3 bis 50 Zeichen.'),
        ]));
        $displayName = trim((string) $io->ask('Anzeigename (Vorname oder Kürzel, für alle sichtbar)', null, self::validator([
            new NotBlank(),
            new Length(max: 50),
        ])));
        $password = (string) $io->askHidden('Start-Passwort', self::validator(PasswordRules::constraints()));
        $repeated = (string) $io->askHidden('Start-Passwort wiederholen');
        if (!hash_equals($password, $repeated)) {
            $io->error('Die beiden Passwörter sind nicht gleich. Es wurde nichts angelegt.');

            return Command::FAILURE;
        }

        $manager = new AppUser(UserRole::Manager, $username, $displayName, $this->household->today(), isAdmin: true);
        $manager->changePassword($this->passwordHasher->hashPassword($manager, $password), mustChange: true);
        $this->entityManager->persist($manager);
        $this->entityManager->flush();
        $this->auditLogger->log(AuditAction::EinrichtungManager, null, $manager, via: AuditVia::System);

        $io->success(\sprintf('Manager „%s“ ist angelegt. Melde dich jetzt unter /anmelden an.', $displayName));

        return Command::SUCCESS;
    }

    /**
     * @param list<\Symfony\Component\Validator\Constraint> $constraints
     *
     * @return callable(mixed): mixed
     */
    private static function validator(array $constraints): callable
    {
        return static function (mixed $value) use ($constraints): mixed {
            $violations = Validation::createValidator()->validate($value, $constraints);
            if (\count($violations) > 0) {
                throw new \RuntimeException((string) $violations->get(0)->getMessage());
            }

            return $value;
        };
    }
}
