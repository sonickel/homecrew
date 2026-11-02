<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use App\Repository\AppUserRepository;
use App\Repository\DeviceRepository;

/**
 * Turns audit log entries into plain German for the log page – no table or column names.
 * Returns plain text; the caller escapes it.
 */
final readonly class AuditLogPresenter
{
    private const array SUBJECTS = [
        'app_user' => 'Person',
        'app_setting' => 'Einstellungen',
        'webauthn_credential' => 'Passkey',
        'device' => 'Gerät',
        'invitation' => 'Einladung',
    ];

    private const array DETAIL_KEYS = [
        'methode' => 'Methode',
        'zweiter_schritt' => 'Zweiter Schritt',
        'schritt' => 'Bei',
        'verbleibend' => 'Verbleibende Codes',
        'passkeys_entfernt' => 'Entfernte Passkeys',
        'neues_start_passwort' => 'Neues Start-Passwort',
        'rolle' => 'Rolle',
        'admin' => 'Admin-Recht',
        'an' => 'Eingeschaltet',
        'gueltig_bis' => 'Gültig bis',
        'letzter_tag' => 'Letzter Arbeitstag',
        'name' => 'Name',
    ];

    private const array DETAIL_VALUES = [
        'passwort' => 'Passwort',
        'passkey' => 'Passkey',
        'totp' => 'Authenticator-App',
        'code' => 'Code',
        'manager' => 'Manager',
        'assistenz' => 'Assistenz',
        'angehoerige' => 'Angehörige',
        'pin' => 'PIN',
        'tablet' => 'Tablet',
        'team' => 'Team',
        'persoenlich' => 'Persönlich',
        'beides' => 'Beides',
        'normal' => 'normal',
        'gross' => 'groß',
        'sehr_gross' => 'sehr groß',
    ];

    /** Columns of app_setting as the settings pages name them. */
    private const array SETTINGS = [
        'household_name' => 'Name des Haushalts',
        'timezone' => 'Zeitzone',
        'holiday_region' => 'Feiertage',
        'guide_publish_mode' => 'Neue Anleitungen',
        'tablet_lock_seconds' => 'Auto-Sperre Tablet (Sekunden)',
        'pin_lan_only' => 'PIN nur im Heimnetz',
        'max_shift_minutes' => 'Hinweis „Dienst noch offen?“ (Minuten)',
        'time_view_months' => 'Eigene Zeiten zurück (Monate)',
        'video_max_seconds' => 'Längste Videos (Sekunden)',
        'retention_handover_days' => 'Löschfrist Übergaben (Tage)',
        'retention_completion_days' => 'Name an Erledigungen entfernen (Tage)',
        'completion_keep_last' => 'Erledigungen je Wartungsplan behalten',
        'retention_done_task_days' => 'Löschfrist erledigte Aufgaben (Tage)',
        'retention_notification_days' => 'Löschfrist Benachrichtigungen (Tage)',
        'retention_report_days' => 'Löschfrist Meldungen (Tage)',
        'retention_audit_days' => 'Löschfrist Protokoll (Tage)',
    ];

    /** Columns of app_user as the person form names them (App\EventListener\PersonAuditListener). */
    private const array PERSON = [
        'role' => 'Rolle',
        'is_admin' => 'Admin-Recht',
        'username' => 'Benutzername',
        'display_name' => 'Name',
        'color' => 'Farbe',
        'active_from' => 'Eintritt',
    ];

    /** Columns of device as the device form names them (App\EventListener\DeviceAuditListener). */
    private const array DEVICE = [
        'name' => 'Name',
        'mode' => 'Modus',
        'dim_after_seconds' => 'Bildschirmschoner nach (Sekunden)',
        'font_size' => 'Schriftgröße',
    ];

    public function __construct(
        private AppUserRepository $userRepository,
        private DeviceRepository $deviceRepository,
    ) {
    }

    public function action(AuditLog $entry): string
    {
        return AuditAction::tryFrom($entry->getAction())?->label() ?? $entry->getAction();
    }

    /**
     * Person, device or both: a PIN login at the tablet shows "Anna am Tablet „Flur“", the team tablet "Tablet „Flur“".
     */
    public function who(AuditLog $entry): string
    {
        $person = $entry->getActor()?->getDisplayName();
        $device = $entry->getDevice()?->getName();
        if (null !== $device) {
            return null === $person ? \sprintf('Tablet „%s“', $device) : \sprintf('%s am Tablet „%s“', $person, $device);
        }

        return $person
            ?? match ($entry->getVia()) {
                AuditVia::System => 'System (Server-Befehl)',
                AuditVia::Tablet => 'Team-Tablet',
                default => 'unbekannt',
            };
    }

    public function subject(AuditLog $entry): string
    {
        $type = $entry->getEntityType();
        if (null === $type) {
            return '';
        }

        $id = $entry->getEntityId();
        if ('app_user' === $type && null !== $id) {
            $person = $this->userRepository->find($id);

            return 'Person: '.($person?->getDisplayName() ?? 'gelöscht');
        }
        if ('device' === $type && null !== $id) {
            return 'Tablet: '.($this->deviceRepository->find($id)?->getName() ?? 'gelöscht');
        }

        return self::SUBJECTS[$type] ?? $type;
    }

    public function details(AuditLog $entry): string
    {
        $lines = [];
        foreach ($entry->getDetails() ?? [] as $key => $value) {
            if ('felder' === $key && \is_array($value)) {
                foreach ($value as $column => $change) {
                    $label = (match ($entry->getEntityType()) {
                        'app_user' => self::PERSON,
                        'device' => self::DEVICE,
                        default => self::SETTINGS,
                    })[$column] ?? (string) $column;
                    $lines[] = \is_array($change)
                        ? \sprintf('%s: %s → %s', $label, self::value($change['alt'] ?? null), self::value($change['neu'] ?? null))
                        : \sprintf('%s: %s', $label, self::value($change));
                }
                continue;
            }
            $lines[] = \sprintf('%s: %s', self::DETAIL_KEYS[$key] ?? $key, self::value($value));
        }

        return implode('; ', $lines);
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            true === $value => 'ja',
            false === $value => 'nein',
            null === $value => '–',
            \is_string($value) => self::DETAIL_VALUES[$value] ?? $value,
            \is_scalar($value) => (string) $value,
            default => (string) json_encode($value, \JSON_UNESCAPED_UNICODE),
        };
    }
}
