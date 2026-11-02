<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WebauthnCredentialRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Passkey of a person. Only what the reference schema keeps is stored; the WebAuthn library's
 * credential record is built from it (App\Security\Passkey\PasskeyCredentialRepository).
 */
#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential', options: ['comment' => 'Passkeys'])]
#[ORM\UniqueConstraint(name: 'uq_webauthn_cred', columns: ['credential_id'])]
#[ORM\Index(name: 'fk_webauthn_user', columns: ['user_id'])]
class WebauthnCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AppUser $user;

    #[ORM\Column(name: 'credential_id', type: Types::BINARY, length: 255)]
    private string $credentialId;

    #[ORM\Column(name: 'public_key', type: Types::BLOB, length: 65535)]
    private mixed $publicKey;

    #[ORM\Column(name: 'sign_count', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $signCount = 0;

    #[ORM\Column(name: 'aaguid', type: Types::BINARY, length: 16, nullable: true, options: ['fixed' => true])]
    private ?string $aaguid = null;

    /** Comma-separated transports reported by the authenticator (usb, nfc, ble, hybrid, internal). */
    #[ORM\Column(name: 'transports', length: 100, nullable: true)]
    private ?string $transports = null;

    #[ORM\Column(name: 'label', length: 100, nullable: true)]
    private ?string $label = null;

    /** Set by the database on insert. */
    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        generated: 'INSERT',
        options: ['default' => new CurrentTimestamp()],
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_used_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    /**
     * @param list<string> $transports
     */
    public function __construct(AppUser $user, string $credentialId, string $publicKey, int $signCount, ?string $aaguid, array $transports)
    {
        $this->user = $user;
        $this->credentialId = $credentialId;
        $this->publicKey = $publicKey;
        $this->signCount = $signCount;
        $this->aaguid = $aaguid;
        $this->transports = [] === $transports ? null : implode(',', $transports);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): AppUser
    {
        return $this->user;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    /**
     * The BLOB arrives as a stream from the database and as a string from the constructor.
     */
    public function getPublicKey(): string
    {
        if (\is_resource($this->publicKey)) {
            $this->publicKey = (string) stream_get_contents($this->publicKey, -1, 0);
        }

        return (string) $this->publicKey;
    }

    public function getSignCount(): int
    {
        return $this->signCount;
    }

    public function getAaguid(): ?string
    {
        return $this->aaguid;
    }

    /**
     * @return list<string>
     */
    public function getTransports(): array
    {
        return null === $this->transports ? [] : explode(',', $this->transports);
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function recordUse(int $signCount, \DateTimeImmutable $usedAt): void
    {
        $this->signCount = $signCount;
        $this->lastUsedAt = $usedAt;
    }
}
