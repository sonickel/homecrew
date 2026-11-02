<?php

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\TinyIntType;
use App\Enum\CalendarView;
use App\Enum\EmailNotification;
use App\Enum\FontSize;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Repository\AppUserRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Person in the household: role, admin right, login data (password/passkey/2FA and/or tablet PIN), personal settings.
 * Mapped column by column after docs/datenbank/schema.sql; CHECK constraints and the generated
 * column manager_marker live in the migration. External services are no AppUser (see visitor).
 *
 * Also the security user of a personal login. It only grants ROLE_USER – the admin right is decided by
 * App\Security\Voter\AdminVoter (admin flag + 2FA + full login), never by a role.
 *
 * The validation constraints serve the person form ("Verwaltung → Personen"); the database enforces the
 * rights rules once more (ck_user_manager, ck_user_assist_admin, uq_user_manager).
 */
#[ORM\Entity(repositoryClass: AppUserRepository::class)]
#[ORM\Table(name: 'app_user', options: ['comment' => 'Person im Haushalt: Rolle, Admin-Recht, Anmeldung (Passwort/Passkey/2FA und/oder Tablet-PIN), Einstellungen'])]
#[ORM\UniqueConstraint(name: 'uq_user_public', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uq_user_username', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'uq_user_display', columns: ['display_name'])]
#[ORM\UniqueConstraint(name: 'uq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uq_user_manager', columns: ['manager_marker'])]
#[UniqueEntity(fields: ['username'], message: 'Diesen Benutzernamen gibt es schon.')]
#[UniqueEntity(fields: ['displayName'], message: 'Diesen Namen gibt es schon. Nimm z. B. einen Spitznamen oder hänge einen Buchstaben an.')]
class AppUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'public_id', type: UuidType::NAME)]
    private Uuid $publicId;

    // Person & rights
    #[ORM\Column(name: 'role', length: 12, enumType: UserRole::class)]
    private UserRole $role;

    #[ORM\Column(name: 'is_admin', options: ['default' => false])]
    private bool $isAdmin;

    #[ORM\Column(name: 'username', length: 50)]
    #[Assert\NotBlank(message: 'Bitte gib einen Benutzernamen ein.')]
    #[Assert\Length(min: 3, max: 50, minMessage: 'Der Benutzername braucht mindestens {{ limit }} Zeichen.', maxMessage: 'Der Benutzername darf höchstens {{ limit }} Zeichen haben.')]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9._-]*$/', message: 'Erlaubt sind Kleinbuchstaben ohne Umlaute, Ziffern, Punkt, Bindestrich und Unterstrich; am Anfang ein Buchstabe oder eine Ziffer.')]
    private string $username;

    #[ORM\Column(name: 'display_name', length: 50)]
    #[Assert\NotBlank(message: 'Bitte gib einen Namen ein.')]
    #[Assert\Length(max: 50, maxMessage: 'Der Name darf höchstens {{ limit }} Zeichen haben.')]
    private string $displayName;

    #[ORM\Column(name: 'color', length: 7, nullable: true, options: ['fixed' => true])]
    #[Assert\Regex(pattern: '/^#[0-9a-f]{6}$/', message: 'Bitte wähle eine Farbe.')]
    private ?string $color = null;

    #[ORM\Column(name: 'active_from', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $activeFrom;

    #[ORM\Column(name: 'active_until', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $activeUntil = null;

    #[ORM\Column(name: 'disabled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $disabledAt = null;

    #[ORM\Column(name: 'anonymized_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    // Login on phone/PC (optional)
    #[ORM\Column(name: 'email', length: 190, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'email_verified_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(name: 'password_hash', length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(name: 'password_must_change', options: ['default' => false])]
    private bool $passwordMustChange = false;

    #[ORM\Column(name: 'twofa_method', length: 10, enumType: TwoFactorMethod::class, options: ['default' => 'keine'])]
    private TwoFactorMethod $twofaMethod = TwoFactorMethod::Keine;

    #[ORM\Column(name: 'totp_secret_enc', type: Types::BINARY, length: 255, nullable: true)]
    private ?string $totpSecretEnc = null;

    #[ORM\Column(name: 'email_code_hash', length: 64, nullable: true, options: ['fixed' => true])]
    private ?string $emailCodeHash = null;

    #[ORM\Column(name: 'email_code_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailCodeExpiresAt = null;

    #[ORM\Column(name: 'email_code_attempts', type: TinyIntType::NAME, options: ['unsigned' => true, 'default' => 0])]
    private int $emailCodeAttempts = 0;

    /** @var list<string>|null hashes of the recovery codes */
    #[ORM\Column(name: 'twofa_backup_codes', type: Types::JSON, nullable: true)]
    private ?array $twofaBackupCodes = null;

    #[ORM\Column(name: 'last_login_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    // Tablet login (PIN, optional)
    #[ORM\Column(name: 'pin_hash', length: 255, nullable: true)]
    private ?string $pinHash = null;

    #[ORM\Column(name: 'pin_login_enabled', options: ['default' => false])]
    private bool $pinLoginEnabled = false;

    #[ORM\Column(name: 'pin_must_change', options: ['default' => true])]
    private bool $pinMustChange = true;

    #[ORM\Column(name: 'pin_failed_count', type: TinyIntType::NAME, options: ['unsigned' => true, 'default' => 0])]
    private int $pinFailedCount = 0;

    #[ORM\Column(name: 'pin_locked_until', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pinLockedUntil = null;

    // Permission switches (set by an admin)
    #[ORM\Column(name: 'can_publish_guides', options: ['default' => false])]
    private bool $canPublishGuides = false;

    #[ORM\Column(name: 'can_manage_recurring_tasks', options: ['default' => false])]
    private bool $canManageRecurringTasks = false;

    #[ORM\Column(name: 'can_create_team_appointments', options: ['default' => false])]
    private bool $canCreateTeamAppointments = false;

    #[ORM\Column(name: 'can_use_ai', options: ['default' => true])]
    private bool $canUseAi = true;

    #[ORM\Column(name: 'ai_voice_confirm', options: ['default' => false])]
    private bool $aiVoiceConfirm = false;

    // Personal settings
    #[ORM\Column(name: 'font_size', length: 12, enumType: FontSize::class, options: ['default' => 'normal'])]
    private FontSize $fontSize = FontSize::Normal;

    #[ORM\Column(name: 'high_contrast', options: ['default' => false])]
    private bool $highContrast = false;

    #[ORM\Column(name: 'speech_output', options: ['default' => false])]
    private bool $speechOutput = false;

    #[ORM\Column(name: 'calendar_view', length: 10, enumType: CalendarView::class, options: ['default' => 'agenda'])]
    private CalendarView $calendarView = CalendarView::Agenda;

    #[ORM\Column(name: 'preferred_language', length: 10, options: ['default' => 'de'])]
    private string $preferredLanguage = 'de';

    #[ORM\Column(name: 'notify_email', length: 10, enumType: EmailNotification::class, options: ['default' => 'aus'])]
    private EmailNotification $notifyEmail = EmailNotification::Aus;

    #[ORM\Column(name: 'notify_push', options: ['default' => false])]
    private bool $notifyPush = false;

    #[ORM\Column(name: 'notify_mail_details', options: ['default' => false])]
    private bool $notifyMailDetails = false;

    #[ORM\Column(name: 'quiet_from', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $quietFrom = null;

    #[ORM\Column(name: 'quiet_until', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $quietUntil = null;

    #[ORM\Column(name: 'show_read_receipts', options: ['default' => true])]
    private bool $showReadReceipts = true;

    #[ORM\Column(name: 'privacy_notice_version', length: 20, nullable: true)]
    private ?string $privacyNoticeVersion = null;

    #[ORM\Column(name: 'privacy_notice_ack_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $privacyNoticeAckAt = null;

    /** Set by the database on insert. */
    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        generated: 'INSERT',
        options: ['default' => new CurrentTimestamp()],
    )]
    private \DateTimeImmutable $createdAt;

    /** Set by the database (ON UPDATE CURRENT_TIMESTAMP). */
    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        columnDefinition: 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        generated: 'ALWAYS',
        options: ['default' => new CurrentTimestamp()],
    )]
    private \DateTimeImmutable $updatedAt;

    /** Generated column: 1 for the manager, otherwise NULL – its unique index allows exactly one manager. */
    #[ORM\Column(
        name: 'manager_marker',
        type: TinyIntType::NAME,
        nullable: true,
        insertable: false,
        updatable: false,
        columnDefinition: "TINYINT AS (IF(role = 'manager', 1, NULL)) STORED",
        generated: 'ALWAYS',
    )]
    private ?int $managerMarker = null;

    public function __construct(
        UserRole $role,
        string $username,
        string $displayName,
        \DateTimeImmutable $activeFrom,
        bool $isAdmin = false,
    ) {
        $this->publicId = Uuid::v7();
        $this->role = $role;
        $this->username = $username;
        $this->displayName = $displayName;
        $this->activeFrom = $activeFrom;
        $this->isAdmin = $isAdmin;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): void
    {
        $this->role = $role;
    }

    public function isManager(): bool
    {
        return UserRole::Manager === $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Stored in lower case: the login ignores case (collation), so the uniqueness check must as well.
     */
    public function setUsername(?string $username): void
    {
        $this->username = mb_strtolower(trim((string) $username));
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = trim((string) $displayName);
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = null === $color || '' === $color ? null : strtolower($color);
    }

    public function getActiveFrom(): \DateTimeImmutable
    {
        return $this->activeFrom;
    }

    public function setActiveFrom(\DateTimeImmutable $activeFrom): void
    {
        $this->activeFrom = $activeFrom;
    }

    public function getActiveUntil(): ?\DateTimeImmutable
    {
        return $this->activeUntil;
    }

    public function getDisabledAt(): ?\DateTimeImmutable
    {
        return $this->disabledAt;
    }

    /**
     * Rights rules of konzept-1 §2 for the person form: the manager is always admin, assistants never.
     */
    #[Assert\Callback]
    public function validateRights(ExecutionContextInterface $context): void
    {
        if (UserRole::Assistenz === $this->role && $this->isAdmin) {
            $context->buildViolation('Assistenzkräfte können keine Admins sein.')->atPath('admin')->addViolation();
        }
        if (UserRole::Manager === $this->role && !$this->isAdmin) {
            $context->buildViolation('Der Manager ist immer Admin.')->atPath('admin')->addViolation();
        }
        if (null !== $this->activeUntil && $this->activeUntil < $this->activeFrom) {
            $context->buildViolation('Der Eintritt muss vor dem letzten Arbeitstag liegen.')->atPath('activeFrom')->addViolation();
        }
    }

    // Lock and leaving (konzept-1 §5)

    public function lock(\DateTimeImmutable $at): void
    {
        $this->disabledAt = $at;
    }

    public function unlock(): void
    {
        $this->disabledAt = null;
    }

    public function isLocked(): bool
    {
        return null !== $this->disabledAt;
    }

    /**
     * @param \DateTimeImmutable $lastDay last working day; the person stays as anchor for working times
     */
    public function leave(\DateTimeImmutable $lastDay): void
    {
        $this->activeUntil = $lastDay;
    }

    public function undoLeave(): void
    {
        $this->activeUntil = null;
    }

    public function hasLeft(): bool
    {
        return null !== $this->activeUntil;
    }

    /**
     * Locked, or the last working day is over – then nobody may log in or stay logged in as this person.
     *
     * @param \DateTimeImmutable $today the household's date
     */
    public function isBlockedOn(\DateTimeImmutable $today): bool
    {
        return null !== $this->disabledAt
            || (null !== $this->activeUntil && $this->activeUntil->format('Y-m-d') < $today->format('Y-m-d'));
    }

    /**
     * Leaving removes every way to log in: password, PIN and second factor. Passkeys and invitations are
     * separate rows, App\Service\Account\PersonAccess removes them.
     */
    public function removeAccess(): void
    {
        $this->passwordHash = null;
        $this->passwordMustChange = false;
        $this->pinHash = null;
        $this->pinLoginEnabled = false;
        $this->pinMustChange = true;
        $this->pinFailedCount = 0;
        $this->pinLockedUntil = null;
        $this->resetTwoFactor();
    }

    public function hasPassword(): bool
    {
        return null !== $this->passwordHash;
    }

    public function getTwofaMethod(): TwoFactorMethod
    {
        return $this->twofaMethod;
    }

    public function hasTwoFactor(): bool
    {
        return TwoFactorMethod::Keine !== $this->twofaMethod;
    }

    public function getFontSize(): FontSize
    {
        return $this->fontSize;
    }

    public function isHighContrast(): bool
    {
        return $this->highContrast;
    }

    public function changeDisplay(FontSize $fontSize, bool $highContrast): void
    {
        $this->fontSize = $fontSize;
        $this->highContrast = $highContrast;
    }

    public function getPrivacyNoticeVersion(): ?string
    {
        return $this->privacyNoticeVersion;
    }

    public function getPrivacyNoticeAckAt(): ?\DateTimeImmutable
    {
        return $this->privacyNoticeAckAt;
    }

    public function acknowledgePrivacyNotice(string $version, \DateTimeImmutable $at): void
    {
        $this->privacyNoticeVersion = $version;
        $this->privacyNoticeAckAt = $at;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Security user

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->username);

        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Nothing to erase: the entity never holds a plain password, PIN or secret
    }

    /**
     * Only what the firewall needs to reload the person; the password hash only as checksum,
     * so a password change ends other sessions (Symfony compares it on every request).
     *
     * @return array{id: ?int, username: string, password: ?string}
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password' => null === $this->passwordHash ? null : hash('crc32c', $this->passwordHash),
        ];
    }

    /**
     * @param array{id: ?int, username: string, password: ?string} $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->username = $data['username'];
        $this->passwordHash = $data['password'];
    }

    public function isPasswordMustChange(): bool
    {
        return $this->passwordMustChange;
    }

    /**
     * @param string $passwordHash already hashed by the password hasher
     * @param bool   $mustChange   true for a start password set by an admin or the setup command
     */
    public function changePassword(string $passwordHash, bool $mustChange = false): void
    {
        $this->passwordHash = $passwordHash;
        $this->passwordMustChange = $mustChange;
    }

    public function recordLogin(\DateTimeImmutable $at): void
    {
        $this->lastLoginAt = $at;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    // Tablet PIN (the PIN login follows in M4)

    /**
     * @param string $pinHash    hashed like a password
     * @param bool   $mustChange true for a start PIN set by an admin
     */
    public function changePin(string $pinHash, bool $mustChange): void
    {
        $this->pinHash = $pinHash;
        $this->pinMustChange = $mustChange;
        $this->pinFailedCount = 0;
        $this->pinLockedUntil = null;
    }

    public function hasPin(): bool
    {
        return null !== $this->pinHash;
    }

    public function isPinMustChange(): bool
    {
        return $this->pinMustChange;
    }

    public function isPinLoginEnabled(): bool
    {
        return $this->pinLoginEnabled;
    }

    /**
     * The PIN login needs a PIN (ck_user_pin).
     */
    public function enablePinLogin(bool $enabled): void
    {
        if ($enabled && null === $this->pinHash) {
            throw new \LogicException('The PIN login needs a PIN.');
        }
        $this->pinLoginEnabled = $enabled;
    }

    // Second factor

    /**
     * @param string $encryptedSecret encrypted by App\Service\Security\SecretBox
     */
    public function enableAuthenticatorApp(string $encryptedSecret): void
    {
        $this->totpSecretEnc = $encryptedSecret;
        $this->twofaMethod = TwoFactorMethod::Totp;
    }

    public function getTotpSecretEnc(): ?string
    {
        return $this->totpSecretEnc;
    }

    /**
     * A passkey becomes the second factor only when there is none yet; an authenticator app stays in charge.
     */
    public function addPasskeyAsTwoFactor(): void
    {
        if (TwoFactorMethod::Keine === $this->twofaMethod) {
            $this->twofaMethod = TwoFactorMethod::Passkey;
        }
    }

    /**
     * Remaining passkeys take over as second factor (the recovery codes stay with them); without passkeys
     * there is no second factor any more.
     */
    public function removeAuthenticatorApp(bool $passkeysRemain): void
    {
        if (!$passkeysRemain) {
            $this->resetTwoFactor();

            return;
        }

        $this->totpSecretEnc = null;
        $this->twofaMethod = TwoFactorMethod::Passkey;
    }

    /**
     * Called after the last passkey was deleted: if it was the second factor, there is none any more.
     */
    public function removeLastPasskey(): void
    {
        if (TwoFactorMethod::Passkey === $this->twofaMethod) {
            $this->resetTwoFactor();
        }
    }

    /**
     * Removes app secret and recovery codes (passkeys are separate rows, see WebauthnCredentialRepository).
     */
    public function resetTwoFactor(): void
    {
        $this->twofaMethod = TwoFactorMethod::Keine;
        $this->totpSecretEnc = null;
        $this->twofaBackupCodes = null;
    }

    /**
     * Recovery codes are stored as keyed hashes only (App\Security\TwoFactor\RecoveryCodeManager);
     * the plain codes are shown once and never saved.
     *
     * @param list<string> $hashes
     */
    public function replaceRecoveryCodeHashes(array $hashes): void
    {
        $this->twofaBackupCodes = $hashes;
    }

    public function countRecoveryCodes(): int
    {
        return \count($this->twofaBackupCodes ?? []);
    }

    public function hasRecoveryCodeHash(string $hash): bool
    {
        foreach ($this->twofaBackupCodes ?? [] as $storedHash) {
            if (hash_equals($storedHash, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function removeRecoveryCodeHash(string $hash): void
    {
        $this->twofaBackupCodes = array_values(array_filter(
            $this->twofaBackupCodes ?? [],
            static fn (string $storedHash): bool => !hash_equals($storedHash, $hash),
        ));
    }
}
