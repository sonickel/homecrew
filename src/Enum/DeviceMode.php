<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a paired tablet is used (device.mode): team view without login, personal PIN login, or both (konzept-12 §2).
 */
enum DeviceMode: string
{
    case Team = 'team';
    case Persoenlich = 'persoenlich';
    case Beides = 'beides';

    public function label(): string
    {
        return match ($this) {
            self::Team => 'Team',
            self::Persoenlich => 'Persönlich',
            self::Beides => 'Beides',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Team => 'Team – zeigt sofort „Heute“ für das Team, ohne Anmeldung (empfohlen)',
            self::Persoenlich => 'Persönlich – Namen antippen und mit PIN anmelden',
            self::Beides => 'Beides – Team-Ansicht und „Persönlich anmelden“ mit PIN',
        };
    }

    public function showsTeamView(): bool
    {
        return self::Persoenlich !== $this;
    }

    public function offersPinLogin(): bool
    {
        return self::Team !== $this;
    }
}
