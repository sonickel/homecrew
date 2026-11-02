<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Account;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Security\TwoFactor\TwoFactorPolicy;
use App\Service\Account\AccountSetup;
use App\Service\Account\PrivacyNotice;
use PHPUnit\Framework\TestCase;

final class AccountSetupTest extends TestCase
{
    public function testStepsComeInOrder(): void
    {
        $setup = self::accountSetup(true);
        $max = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $max->changePassword('hash', mustChange: true);

        self::assertSame(AccountSetup::STEP_PASSWORD, $setup->nextStep($max));
        $max->changePassword('new-hash');
        self::assertSame(AccountSetup::STEP_PRIVACY, $setup->nextStep($max));
        $max->acknowledgePrivacyNotice(PrivacyNotice::VERSION, new \DateTimeImmutable());
        self::assertSame(AccountSetup::STEP_TWO_FACTOR, $setup->nextStep($max));
        $max->enableAuthenticatorApp('encrypted');
        self::assertSame(AccountSetup::STEP_RECOVERY_CODES, $setup->nextStep($max));
        $max->replaceRecoveryCodeHashes(['hash']);
        self::assertNull($setup->nextStep($max));
        // The last recovery code used: new ones are due
        $max->removeRecoveryCodeHash('hash');
        self::assertSame(AccountSetup::STEP_RECOVERY_CODES, $setup->nextStep($max));
    }

    public function testAnOlderPrivacyNoticeIsShownAgain(): void
    {
        $papa = new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01'));
        $papa->acknowledgePrivacyNotice('2026-01-01', new \DateTimeImmutable('2026-01-01'));

        self::assertSame(AccountSetup::STEP_PRIVACY, self::accountSetup(true)->nextStep($papa));
    }

    public function testSecondFactorIsOnlyForcedForAdminsAndWithTheSwitchOn(): void
    {
        $papa = new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01'));
        $max = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $sister = new AppUser(UserRole::Angehoerige, 'schwester', 'Schwester', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        foreach ([$papa, $max, $sister] as $person) {
            $person->acknowledgePrivacyNotice(PrivacyNotice::VERSION, new \DateTimeImmutable());
        }

        self::assertNull(self::accountSetup(true)->nextStep($papa));
        self::assertSame(AccountSetup::STEP_TWO_FACTOR, self::accountSetup(true)->nextStep($sister));
        self::assertNull(self::accountSetup(false)->nextStep($max));
    }

    public function testRoutesOfAStep(): void
    {
        $setup = self::accountSetup(true);

        self::assertTrue($setup->belongsToStep(AccountSetup::STEP_TWO_FACTOR, 'webauthn.controller.creation.response.add_passkey'));
        self::assertTrue($setup->belongsToStep(AccountSetup::STEP_TWO_FACTOR, 'app_account_two_factor_app'));
        self::assertTrue($setup->belongsToStep(AccountSetup::STEP_PRIVACY, 'app_account_privacy'));
        self::assertFalse($setup->belongsToStep(AccountSetup::STEP_PASSWORD, 'app_account_two_factor_app'));
        self::assertFalse($setup->belongsToStep(AccountSetup::STEP_RECOVERY_CODES, 'app_home'));
    }

    private static function accountSetup(bool $twoFactorRequired): AccountSetup
    {
        return new AccountSetup(new TwoFactorPolicy($twoFactorRequired), new PrivacyNotice());
    }
}
