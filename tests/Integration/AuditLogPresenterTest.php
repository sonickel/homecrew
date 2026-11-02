<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\AuditLog;
use App\Enum\AuditVia;
use App\Enum\UserRole;
use App\Service\Audit\AuditLogPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AuditLogPresenterTest extends KernelTestCase
{
    public function testEntriesReadInPlainGerman(): void
    {
        $papa = new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01'));
        static::getContainer()->get(EntityManagerInterface::class)->persist($papa);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $presenter = static::getContainer()->get(AuditLogPresenter::class);

        $login = new AuditLog('anmeldung.erfolg', AuditVia::App, $papa, null, 'app_user', $papa->getId(), ['methode' => 'passkey', 'zweiter_schritt' => false]);
        self::assertSame('Angemeldet', $presenter->action($login));
        self::assertSame('Papa', $presenter->who($login));
        self::assertSame('Person: Papa', $presenter->subject($login));
        self::assertSame('Methode: Passkey; Zweiter Schritt: nein', $presenter->details($login));

        $settings = new AuditLog('einstellungen.aendern', AuditVia::System, null, null, 'app_setting', 1, ['felder' => ['tablet_lock_seconds' => ['alt' => 180, 'neu' => 300], 'smtp_password_enc' => 'geändert']]);
        self::assertSame('System (Server-Befehl)', $presenter->who($settings));
        self::assertSame('Einstellungen', $presenter->subject($settings));
        self::assertSame('Auto-Sperre Tablet (Sekunden): 180 → 300; smtp_password_enc: geändert', $presenter->details($settings));
    }
}
