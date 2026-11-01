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
use Symfony\Component\Uid\Uuid;

/**
 * Person in the household: role, admin right, login data (password/passkey/2FA and/or tablet PIN), personal settings.
 * Mapped column by column after docs/datenbank/schema.sql; CHECK constraints and the generated
 * column manager_marker live in the migration. External services are no AppUser (see visitor).
 */
#[ORM\Entity(repositoryClass: AppUserRepository::class)]
#[ORM\Table(name: 'app_user', options: ['comment' => 'Person im Haushalt: Rolle, Admin-Recht, Anmeldung (Passwort/Passkey/2FA und/oder Tablet-PIN), Einstellungen'])]
#[ORM\UniqueConstraint(name: 'uq_user_public', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uq_user_username', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'uq_user_display', columns: ['display_name'])]
#[ORM\UniqueConstraint(name: 'uq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uq_user_manager', columns: ['manager_marker'])]
class AppUser
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
    private string $username;

    #[ORM\Column(name: 'display_name', length: 50)]
    private string $displayName;

    #[ORM\Column(name: 'color', length: 7, nullable: true, options: ['fixed' => true])]
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

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getActiveFrom(): \DateTimeImmutable
    {
        return $this->activeFrom;
    }

    public function getTwofaMethod(): TwoFactorMethod
    {
        return $this->twofaMethod;
    }

    public function getFontSize(): FontSize
    {
        return $this->fontSize;
    }

    public function isHighContrast(): bool
    {
        return $this->highContrast;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
