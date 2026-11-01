<?php

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\TinyIntType;
use App\Enum\AiProvider;
use App\Enum\ExtensionStatus;
use App\Enum\GuidePublishMode;
use App\Enum\MinijobProcedure;
use App\Enum\SmtpEncryption;
use App\Enum\SpeechRecognition;
use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Settings of the household: exactly one row (id = 1), created by the first migration.
 * Mapped column by column after docs/datenbank/schema.sql; CHECK constraints live in the migration.
 */
#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
#[ORM\Table(name: 'app_setting', options: ['comment' => 'Globale Einstellungen des Haushalts (genau eine Zeile, Admin-Menü)'])]
class AppSetting
{
    public const int ID = 1;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: TinyIntType::NAME, options: ['unsigned' => true, 'default' => 1])]
    private int $id = self::ID;

    #[ORM\Column(name: 'household_name', length: 100, options: ['default' => 'Mein Haushalt'])]
    private string $householdName = 'Mein Haushalt';

    #[ORM\Column(name: 'timezone', length: 40, options: ['default' => 'Europe/Berlin'])]
    private string $timezone = 'Europe/Berlin';

    #[ORM\Column(name: 'holiday_region', length: 20, options: ['default' => 'BY'])]
    private string $holidayRegion = 'BY';

    #[ORM\Column(name: 'minijob_procedure', length: 20, enumType: MinijobProcedure::class, options: ['default' => 'haushaltsscheck'])]
    private MinijobProcedure $minijobProcedure = MinijobProcedure::Haushaltsscheck;

    #[ORM\Column(name: 'guide_publish_mode', length: 20, enumType: GuidePublishMode::class, options: ['default' => 'freigabe'])]
    private GuidePublishMode $guidePublishMode = GuidePublishMode::Freigabe;

    #[ORM\Column(name: 'tablet_lock_seconds', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 180])]
    private int $tabletLockSeconds = 180;

    #[ORM\Column(name: 'pin_lan_only', options: ['default' => true])]
    private bool $pinLanOnly = true;

    #[ORM\Column(name: 'max_shift_minutes', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 600])]
    private int $maxShiftMinutes = 600;

    #[ORM\Column(name: 'time_view_months', type: TinyIntType::NAME, options: ['unsigned' => true, 'default' => 3])]
    private int $timeViewMonths = 3;

    #[ORM\Column(name: 'video_max_seconds', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 90])]
    private int $videoMaxSeconds = 90;

    #[ORM\Column(name: 'voice_messages_enabled', options: ['default' => true])]
    private bool $voiceMessagesEnabled = true;

    #[ORM\Column(name: 'assistant_direct_chat', options: ['default' => false])]
    private bool $assistantDirectChat = false;

    #[ORM\Column(name: 'push_allowed', options: ['default' => false])]
    private bool $pushAllowed = false;

    #[ORM\Column(name: 'daily_digest_time', type: Types::TIME_IMMUTABLE, options: ['default' => '18:00:00'])]
    private \DateTimeImmutable $dailyDigestTime;

    #[ORM\Column(name: 'ai_enabled', options: ['default' => false])]
    private bool $aiEnabled = false;

    #[ORM\Column(name: 'ai_provider', length: 20, enumType: AiProvider::class, options: ['default' => 'anthropic'])]
    private AiProvider $aiProvider = AiProvider::Anthropic;

    #[ORM\Column(name: 'ai_model', length: 60, options: ['default' => 'claude-haiku-5-5'])]
    private string $aiModel = 'claude-haiku-5-5';

    #[ORM\Column(name: 'ai_api_key_enc', type: Types::BINARY, length: 512, nullable: true)]
    private ?string $aiApiKeyEnc = null;

    #[ORM\Column(name: 'ai_api_key_hint', length: 4, nullable: true, options: ['fixed' => true])]
    private ?string $aiApiKeyHint = null;

    #[ORM\Column(name: 'ai_key_status', length: 12, enumType: ExtensionStatus::class, options: ['default' => 'fehlt'])]
    private ExtensionStatus $aiKeyStatus = ExtensionStatus::Fehlt;

    #[ORM\Column(name: 'ai_key_checked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $aiKeyCheckedAt = null;

    #[ORM\Column(name: 'ai_key_error', length: 255, nullable: true)]
    private ?string $aiKeyError = null;

    #[ORM\Column(name: 'ai_feature_assistant', options: ['default' => true])]
    private bool $aiFeatureAssistant = true;

    #[ORM\Column(name: 'ai_feature_writing', options: ['default' => true])]
    private bool $aiFeatureWriting = true;

    #[ORM\Column(name: 'ai_feature_translation', options: ['default' => false])]
    private bool $aiFeatureTranslation = false;

    #[ORM\Column(name: 'ai_team_device', options: ['default' => true])]
    private bool $aiTeamDevice = true;

    #[ORM\Column(name: 'ai_monthly_budget_eur', type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $aiMonthlyBudgetEur = null;

    #[ORM\Column(name: 'speech_recognition', length: 10, enumType: SpeechRecognition::class, options: ['default' => 'lokal'])]
    private SpeechRecognition $speechRecognition = SpeechRecognition::Lokal;

    #[ORM\Column(name: 'mail_enabled', options: ['default' => false])]
    private bool $mailEnabled = false;

    #[ORM\Column(name: 'smtp_host', length: 190, nullable: true)]
    private ?string $smtpHost = null;

    #[ORM\Column(name: 'smtp_port', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $smtpPort = null;

    #[ORM\Column(name: 'smtp_encryption', length: 10, enumType: SmtpEncryption::class, options: ['default' => 'starttls'])]
    private SmtpEncryption $smtpEncryption = SmtpEncryption::Starttls;

    #[ORM\Column(name: 'smtp_username', length: 190, nullable: true)]
    private ?string $smtpUsername = null;

    #[ORM\Column(name: 'smtp_password_enc', type: Types::BINARY, length: 512, nullable: true)]
    private ?string $smtpPasswordEnc = null;

    #[ORM\Column(name: 'mail_from_address', length: 190, nullable: true)]
    private ?string $mailFromAddress = null;

    #[ORM\Column(name: 'mail_from_name', length: 100, nullable: true)]
    private ?string $mailFromName = null;

    #[ORM\Column(name: 'mail_status', length: 12, enumType: ExtensionStatus::class, options: ['default' => 'fehlt'])]
    private ExtensionStatus $mailStatus = ExtensionStatus::Fehlt;

    #[ORM\Column(name: 'mail_checked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mailCheckedAt = null;

    #[ORM\Column(name: 'retention_handover_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 30])]
    private int $retentionHandoverDays = 30;

    #[ORM\Column(name: 'retention_completion_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 90])]
    private int $retentionCompletionDays = 90;

    #[ORM\Column(name: 'completion_keep_last', type: TinyIntType::NAME, options: ['unsigned' => true, 'default' => 5])]
    private int $completionKeepLast = 5;

    #[ORM\Column(name: 'retention_done_task_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 365])]
    private int $retentionDoneTaskDays = 365;

    #[ORM\Column(name: 'retention_chat_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 365])]
    private int $retentionChatDays = 365;

    #[ORM\Column(name: 'retention_notification_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 30])]
    private int $retentionNotificationDays = 30;

    #[ORM\Column(name: 'retention_report_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 365])]
    private int $retentionReportDays = 365;

    #[ORM\Column(name: 'retention_audit_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 365])]
    private int $retentionAuditDays = 365;

    #[ORM\Column(name: 'retention_ai_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 7])]
    private int $retentionAiDays = 7;

    #[ORM\Column(name: 'retention_hr_years', type: TinyIntType::NAME, nullable: true, options: ['unsigned' => true])]
    private ?int $retentionHrYears = null;

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

    public function __construct()
    {
        // Only the time is stored; a fixed date keeps the value independent of today
        $this->dailyDigestTime = new \DateTimeImmutable('1970-01-01 18:00:00');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHouseholdName(): string
    {
        return $this->householdName;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }
}
