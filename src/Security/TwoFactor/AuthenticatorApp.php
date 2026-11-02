<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use App\Entity\AppUser;
use App\Service\Security\SecretBox;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Symfony\Component\Clock\ClockInterface;

/**
 * Time-based codes of an authenticator app (RFC 6238: SHA-1, 30 s, 6 digits – what every app understands).
 * The secret is stored encrypted and only decrypted for the moment of the check.
 */
final readonly class AuthenticatorApp
{
    public const string ISSUER = 'HomeCrew';
    /** Seconds a code stays valid after its 30-second window, for slow typing. */
    private const int LEEWAY = 15;

    public function __construct(
        private SecretBox $secretBox,
        private ClockInterface $clock,
    ) {
    }

    /**
     * 160 random bits as base32 (32 characters), the size RFC 4226 recommends.
     */
    public function generateSecret(): string
    {
        return Base32::encodeUpperUnpadded(random_bytes(20));
    }

    /**
     * otpauth:// link for the QR code; the app shows "HomeCrew (username)".
     */
    public function provisioningUri(AppUser $user, #[\SensitiveParameter] string $secret): string
    {
        $totp = $this->totp($secret);
        $totp->setLabel($user->getUserIdentifier());
        $totp->setIssuer(self::ISSUER);

        return $totp->getProvisioningUri();
    }

    public function verify(#[\SensitiveParameter] string $secret, string $code): bool
    {
        $code = (string) preg_replace('/\s+/', '', $code);
        if (1 !== preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return $this->totp($secret)->verify($code, null, self::LEEWAY);
    }

    public function verifyForUser(AppUser $user, string $code): bool
    {
        $encrypted = $user->getTotpSecretEnc();

        return null !== $encrypted && $this->verify($this->secretBox->decrypt($encrypted), $code);
    }

    public function encryptSecret(#[\SensitiveParameter] string $secret): string
    {
        return $this->secretBox->encrypt($secret);
    }

    public function decryptSecret(string $encrypted): string
    {
        return $this->secretBox->decrypt($encrypted);
    }

    private function totp(#[\SensitiveParameter] string $secret): TOTP
    {
        \assert('' !== $secret);

        return TOTP::createFromSecret($secret, $this->clock);
    }
}
