<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Enum\AiProvider;
use App\Enum\CalendarView;
use App\Enum\EmailNotification;
use App\Enum\ExtensionStatus;
use App\Enum\FontSize;
use App\Enum\GuidePublishMode;
use App\Enum\MinijobProcedure;
use App\Enum\SmtpEncryption;
use App\Enum\SpeechRecognition;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every PHP enum offers exactly the values the CHECK constraint of its column allows.
 */
final class EnumCheckConstraintTest extends DatabaseTestCase
{
    /**
     * @return iterable<string, array{string, string, class-string<\BackedEnum>}>
     */
    public static function enumColumns(): iterable
    {
        yield 'app_user.role' => ['app_user', 'ck_user_role', UserRole::class];
        yield 'app_user.twofa_method' => ['app_user', 'ck_user_2fa', TwoFactorMethod::class];
        yield 'app_user.font_size' => ['app_user', 'ck_user_font', FontSize::class];
        yield 'app_user.calendar_view' => ['app_user', 'ck_user_calview', CalendarView::class];
        yield 'app_user.notify_email' => ['app_user', 'ck_user_mail', EmailNotification::class];
        yield 'app_setting.minijob_procedure' => ['app_setting', 'ck_setting_minijob', MinijobProcedure::class];
        yield 'app_setting.guide_publish_mode' => ['app_setting', 'ck_setting_publish', GuidePublishMode::class];
        yield 'app_setting.speech_recognition' => ['app_setting', 'ck_setting_speech', SpeechRecognition::class];
        yield 'app_setting.ai_provider' => ['app_setting', 'ck_setting_ai_prov', AiProvider::class];
        yield 'app_setting.ai_key_status' => ['app_setting', 'ck_setting_ai_stat', ExtensionStatus::class];
        yield 'app_setting.mail_status' => ['app_setting', 'ck_setting_mail_st', ExtensionStatus::class];
        yield 'app_setting.smtp_encryption' => ['app_setting', 'ck_setting_mail_enc', SmtpEncryption::class];
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    #[DataProvider('enumColumns')]
    public function testEnumMatchesCheckConstraint(string $table, string $constraint, string $enum): void
    {
        $clause = (string) $this->connection()->fetchOne(
            'SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint],
        );
        preg_match_all("/'([^']*)'/", $clause, $matches);

        $allowed = $matches[1];
        $values = array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enum::cases());
        sort($allowed);
        sort($values);

        self::assertNotSame([], $allowed, 'CHECK constraint not found: '.$constraint);
        self::assertSame($allowed, $values);
    }
}
