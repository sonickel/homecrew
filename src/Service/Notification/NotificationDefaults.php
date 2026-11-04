<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\AppUser;
use App\Enum\NotificationEvent;
use App\Enum\UserRole;

/**
 * Who gets which notification in the app by default (konzept-13 §7) – the only place for these rules.
 * Admins (the manager is always one) use the admin column, everybody else the column of their role.
 * From M13 a person may deviate (notification_preference stores only the deviations).
 */
final readonly class NotificationDefaults
{
    /** event => [admins, Angehörige, Assistenz] */
    private const array IN_APP = [
        'meldung' => [true, false, false],
        'meldung_erledigt' => [false, false, false],
        'frage' => [true, false, false],
        'uebergabe_wichtig' => [true, true, true],
        'nachricht' => [true, true, true],
        'dienst' => [false, false, true],
        'urlaub' => [true, false, true],
        'aufgabe' => [true, true, true],
        'gespraech' => [true, false, false],
        'termin' => [true, true, true],
        'frist' => [true, false, false],
        'checkliste' => [true, false, false],
        'anleitung' => [true, false, false],
        'wartung' => [true, false, false],
    ];

    public function inApp(NotificationEvent $event, AppUser $person): bool
    {
        [$admins, $relatives, $assistants] = self::IN_APP[$event->value];

        return match (true) {
            $person->isAdmin() => $admins,
            UserRole::Angehoerige === $person->getRole() => $relatives,
            UserRole::Assistenz === $person->getRole() => $assistants,
            default => false,
        };
    }
}
