<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Security\TwoFactor\TwoFactorPolicy;
use App\Service\Account\AccountSetup;
use PHPUnit\Framework\TestCase;

final class AccountSetupTest extends TestCase
{
    public function testStepsComeInOrder(): void
    {
        $setup = new AccountSetup(new TwoFactorPolicy(true));
        $max = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $max->changePassword('hash', mustChange: true);

        self::assertSame(AccountSetup::STEP_PASSWORD, $setup->nextStep($max));
        $max->changePassword('new-hash');
        self::assertSame(AccountSetup::STEP_TWO_FACTOR, $setup->nextStep($max));
        $max->enableAuthenticatorApp('encrypted');
        self::assertSame(AccountSetup::STEP_RECOVERY_CODES, $setup->nextStep($max));
        $max->replaceRecoveryCodeHashes(['hash']);
        self::assertNull($setup->nextStep($max));
        // The last recovery code used: new ones are due
        $max->removeRecoveryCodeHash('hash');
        self::assertSame(AccountSetup::STEP_RECOVERY_CODES, $setup->nextStep($max));
    }

    public function testSecondFactorIsOnlyForcedForAdminsAndWithTheSwitchOn(): void
    {
        $papa = new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01'));
        $max = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);

        self::assertNull((new AccountSetup(new TwoFactorPolicy(true)))->nextStep($papa));
        self::assertNull((new AccountSetup(new TwoFactorPolicy(false)))->nextStep($max));
    }

    public function testRoutesOfAStep(): void
    {
        $setup = new AccountSetup(new TwoFactorPolicy(true));

        self::assertTrue($setup->belongsToStep(AccountSetup::STEP_TWO_FACTOR, 'webauthn.controller.creation.response.add_passkey'));
        self::assertTrue($setup->belongsToStep(AccountSetup::STEP_TWO_FACTOR, 'app_account_two_factor_app'));
        self::assertFalse($setup->belongsToStep(AccountSetup::STEP_PASSWORD, 'app_account_two_factor_app'));
        self::assertFalse($setup->belongsToStep(AccountSetup::STEP_RECOVERY_CODES, 'app_home'));
    }
}
