<?php

declare(strict_types=1);

namespace App\Security\Pin;

use App\Entity\AppUser;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Login of a person by PIN at a paired tablet (konzept-1 §5). Never admin rights (App\Security\Voter\AdminVoter),
 * no changes to the own login data (App\Security\Voter\AccountVoter), ends after app_setting.tablet_lock_seconds.
 * A class of its own, so that scheb/2fa (which matches token classes exactly) does not ask for a second factor:
 * without admin rights the PIN login needs none.
 */
final class PinLoginToken extends PostAuthenticationToken
{
    private const string DEVICE = 'device_id';
    private const string PIN = 'pin';

    /**
     * @param string[] $roles
     */
    public function __construct(AppUser $user, string $firewallName, array $roles, int $deviceId)
    {
        parent::__construct($user, $firewallName, $roles);
        $this->setAttribute(self::DEVICE, $deviceId);
        $this->rememberPin($user);
    }

    /**
     * The tablet the person logged in at.
     */
    public function getDeviceId(): int
    {
        $deviceId = $this->getAttribute(self::DEVICE);
        \assert(\is_int($deviceId));

        return $deviceId;
    }

    /**
     * Notes the PIN this login goes with – a fingerprint of its hash, never the PIN. After changing the own PIN
     * the login goes on with the new one.
     */
    public function rememberPin(AppUser $user): void
    {
        $this->setAttribute(self::PIN, self::fingerprint($user));
    }

    /**
     * False once the PIN was replaced elsewhere – e.g. an admin's new start PIN because the old one became known:
     * then the running login must end, or whoever holds it could set a PIN of their own.
     */
    public function hasSamePin(AppUser $user): bool
    {
        return hash_equals((string) $this->getAttribute(self::PIN), self::fingerprint($user));
    }

    private static function fingerprint(AppUser $user): string
    {
        return hash('sha256', (string) $user->getPinHash());
    }
}
