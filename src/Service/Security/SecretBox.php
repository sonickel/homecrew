<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts the *_enc columns (TOTP secret, later SMTP password and AI key) with libsodium secretbox.
 *
 * Stored format: version byte · nonce · ciphertext with MAC. The master key comes from the environment
 * (Symfony/Docker secret), never from the database or the repository.
 */
final readonly class SecretBox
{
    private const string VERSION = "\x01";
    private const int MIN_MASTER_KEY_LENGTH = 32;

    public function __construct(
        #[\SensitiveParameter]
        #[Autowire('%env(APP_ENCRYPTION_KEY)%')]
        private string $masterKey,
    ) {
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::VERSION.$nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key());
    }

    /**
     * @throws SecretBoxException when the value was not encrypted with the current master key or was altered
     */
    public function decrypt(string $encrypted): string
    {
        $headerLength = 1 + \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (\strlen($encrypted) < $headerLength + \SODIUM_CRYPTO_SECRETBOX_MACBYTES || self::VERSION !== $encrypted[0]) {
            throw new SecretBoxException('The encrypted value has an unknown format.');
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($encrypted, $headerLength),
            substr($encrypted, 1, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key(),
        );
        if (false === $plaintext) {
            throw new SecretBoxException('The encrypted value cannot be decrypted with the current master key.');
        }

        return $plaintext;
    }

    /**
     * Keyed hash (HMAC-SHA256) for values that are only compared, never read back – e.g. recovery codes.
     * Without the master key a copy of the database is not enough to try out codes offline.
     * $purpose separates the keys of different uses.
     */
    public function fingerprint(#[\SensitiveParameter] string $value, string $purpose): string
    {
        return hash_hmac('sha256', $value, hash_hmac('sha256', $purpose, $this->key(), true));
    }

    private function key(): string
    {
        if (\strlen($this->masterKey) < self::MIN_MASTER_KEY_LENGTH) {
            throw new SecretBoxException('APP_ENCRYPTION_KEY is missing or shorter than 32 characters.');
        }

        // Any long random string works as master key; BLAKE2b turns it into the 32 bytes secretbox needs
        return sodium_crypto_generichash($this->masterKey, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
