<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Enum\AiProvider;
use App\Enum\AssetStatus;
use App\Enum\AssigneeRole;
use App\Enum\AuditVia;
use App\Enum\CalendarView;
use App\Enum\CompletionAction;
use App\Enum\CreatedVia;
use App\Enum\DeviceMode;
use App\Enum\DueRule;
use App\Enum\EmailNotification;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Enum\ExtensionStatus;
use App\Enum\FontSize;
use App\Enum\GuidePublishMode;
use App\Enum\GuideType;
use App\Enum\MinijobProcedure;
use App\Enum\ProofType;
use App\Enum\RecurrenceType;
use App\Enum\SmtpEncryption;
use App\Enum\SpeechRecognition;
use App\Enum\StepFreeAccess;
use App\Enum\TaskCategory;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Enum\Visibility;
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
        yield 'device.mode' => ['device', 'ck_device_mode', DeviceMode::class];
        yield 'device.font_size' => ['device', 'ck_device_font', FontSize::class];
        yield 'audit_log.via' => ['audit_log', 'ck_audit_via', AuditVia::class];
        yield 'emergency_contact.category' => ['emergency_contact', 'ck_emergency_category', EmergencyCategory::class];
        yield 'emergency_contact.visibility' => ['emergency_contact', 'ck_emergency_visible', EmergencyVisibility::class];
        yield 'poi.a11y_step_free' => ['poi', 'ck_poi_stepfree', StepFreeAccess::class];
        yield 'guide.guide_type' => ['guide', 'ck_guide_type', GuideType::class];
        yield 'asset.status' => ['asset', 'ck_asset_status', AssetStatus::class];
        yield 'shopping_list.assignee_role' => ['shopping_list', 'ck_shopping_list_role', AssigneeRole::class];
        yield 'task_template.category' => ['task_template', 'ck_template_category', TaskCategory::class];
        yield 'task_template.priority' => ['task_template', 'ck_template_priority', TaskPriority::class];
        yield 'task_template.proof_type' => ['task_template', 'ck_template_proof', ProofType::class];
        yield 'task_template.visibility' => ['task_template', 'ck_template_visible', Visibility::class];
        yield 'task_template.assignee_role' => ['task_template', 'ck_template_role', AssigneeRole::class];
        yield 'task_template.recurrence_type' => ['task_template', 'ck_template_recur', RecurrenceType::class];
        yield 'task.category' => ['task', 'ck_task_category', TaskCategory::class];
        yield 'task.priority' => ['task', 'ck_task_priority', TaskPriority::class];
        yield 'task.proof_type' => ['task', 'ck_task_proof', ProofType::class];
        yield 'task.visibility' => ['task', 'ck_task_visible', Visibility::class];
        yield 'task.assignee_role' => ['task', 'ck_task_role', AssigneeRole::class];
        yield 'task.due_rule' => ['task', 'ck_task_duerule', DueRule::class];
        yield 'task.status' => ['task', 'ck_task_status', TaskStatus::class];
        yield 'task.created_via' => ['task', 'ck_task_via', CreatedVia::class];
        yield 'task_completion.action' => ['task_completion', 'ck_completion_action', CompletionAction::class];
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
