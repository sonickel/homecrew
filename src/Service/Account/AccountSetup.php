<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\AppUser;
use App\Security\TwoFactor\TwoFactorPolicy;

/**
 * Mandatory steps after a login, in this order: change the start password, read the privacy notice,
 * set up a second factor (admins, when the policy demands it), keep recovery codes.
 * Until they are done nothing else is reachable.
 */
final readonly class AccountSetup
{
    public const string STEP_PASSWORD = 'app_account_password';
    public const string STEP_PRIVACY = 'app_account_privacy';
    public const string STEP_TWO_FACTOR = 'app_account_two_factor';
    public const string STEP_RECOVERY_CODES = 'app_account_recovery_codes';

    /** Routes that belong to a step besides its own page. */
    private const array STEP_ROUTES = [
        self::STEP_PASSWORD => [self::STEP_PASSWORD],
        self::STEP_PRIVACY => [self::STEP_PRIVACY],
        // Setting up a second factor may ask for a confirmation (App\Service\Account\RecentConfirmation)
        self::STEP_TWO_FACTOR => [self::STEP_TWO_FACTOR, 'app_account_two_factor_app', 'app_account_two_factor_passkey', 'webauthn.controller.creation.request.add_passkey', 'webauthn.controller.creation.response.add_passkey', 'app_account_confirm', 'webauthn.controller.request.request.confirm', 'webauthn.controller.request.response.confirm'],
        self::STEP_RECOVERY_CODES => [self::STEP_RECOVERY_CODES],
    ];

    public function __construct(
        private TwoFactorPolicy $twoFactorPolicy,
        private PrivacyNotice $privacyNotice,
    ) {
    }

    /**
     * Route of the next mandatory step, or null when the account is ready.
     */
    public function nextStep(AppUser $user): ?string
    {
        if ($user->isPasswordMustChange()) {
            return self::STEP_PASSWORD;
        }
        if ($this->privacyNotice->needsAcknowledgement($user)) {
            return self::STEP_PRIVACY;
        }
        if ($this->twoFactorPolicy->isRequiredFor($user) && !$user->hasTwoFactor()) {
            return self::STEP_TWO_FACTOR;
        }
        if ($user->hasTwoFactor() && 0 === $user->countRecoveryCodes()) {
            return self::STEP_RECOVERY_CODES;
        }

        return null;
    }

    public function belongsToStep(string $step, string $route): bool
    {
        return \in_array($route, self::STEP_ROUTES[$step] ?? [], true);
    }
}
