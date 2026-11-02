<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeviceMode;
use App\Enum\FontSize;
use App\Repository\DeviceRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paired tablet: team tablet (sees what the role assistant sees, no person) and/or personal PIN login.
 * Created in M2 because audit_log refers to it; pairing and the device login follow in M4.
 */
#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[ORM\Table(name: 'device', options: ['comment' => 'Gekoppeltes Tablet: Team-Tablet (sieht alles für die Rolle Assistenz, keine Person) und/oder persönliche PIN-Anmeldung'])]
#[ORM\UniqueConstraint(name: 'uq_device_token', columns: ['token_hash'])]
#[ORM\Index(name: 'fk_device_paired', columns: ['paired_by_user_id'])]
class Device
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 50)]
    private string $name;

    /** SHA-256 of the device token; the token itself only lives in the tablet's cookie. */
    #[ORM\Column(name: 'token_hash', length: 64, options: ['fixed' => true])]
    private string $tokenHash;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'paired_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $pairedBy = null;

    #[ORM\Column(name: 'mode', length: 12, enumType: DeviceMode::class, options: ['default' => 'team'])]
    private DeviceMode $mode = DeviceMode::Team;

    #[ORM\Column(name: 'dim_after_seconds', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 300])]
    private int $dimAfterSeconds = 300;

    #[ORM\Column(name: 'font_size', length: 12, enumType: FontSize::class, options: ['default' => 'normal'])]
    private FontSize $fontSize = FontSize::Normal;

    /** Set by the database on insert. */
    #[ORM\Column(
        name: 'paired_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        generated: 'INSERT',
        options: ['default' => new CurrentTimestamp()],
    )]
    private \DateTimeImmutable $pairedAt;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(string $name, string $tokenHash, ?AppUser $pairedBy = null)
    {
        $this->name = $name;
        $this->tokenHash = $tokenHash;
        $this->pairedBy = $pairedBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMode(): DeviceMode
    {
        return $this->mode;
    }

    public function getFontSize(): FontSize
    {
        return $this->fontSize;
    }

    public function getPairedAt(): \DateTimeImmutable
    {
        return $this->pairedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }
}
