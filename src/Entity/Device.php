<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeviceMode;
use App\Enum\FontSize;
use App\Repository\DeviceRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paired tablet: team tablet (sees what the role assistant sees, no person) and/or personal PIN login (konzept-12).
 *
 * Also the security user of the team tablet (App\Security\Device\DeviceAuthenticator): it only grants
 * ROLE_TEAM_DEVICE and is no AppUser – no admin rights, no "Mein Konto", entries carry the device instead of a person.
 */
#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[ORM\Table(name: 'device', options: ['comment' => 'Gekoppeltes Tablet: Team-Tablet (sieht alles für die Rolle Assistenz, keine Person) und/oder persönliche PIN-Anmeldung'])]
#[ORM\UniqueConstraint(name: 'uq_device_token', columns: ['token_hash'])]
#[ORM\Index(name: 'fk_device_paired', columns: ['paired_by_user_id'])]
class Device implements UserInterface
{
    public const string ROLE = 'ROLE_TEAM_DEVICE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 50)]
    #[Assert\NotBlank(message: 'Bitte gib dem Tablet einen Namen, z. B. „Flur“ oder „Küche“.')]
    #[Assert\Length(max: 50, maxMessage: 'Der Name darf höchstens {{ limit }} Zeichen haben.')]
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

    public function setName(?string $name): void
    {
        $this->name = trim((string) $name);
    }

    public function hasTokenHash(string $tokenHash): bool
    {
        return hash_equals($this->tokenHash, $tokenHash);
    }

    public function getPairedBy(): ?AppUser
    {
        return $this->pairedBy;
    }

    public function getMode(): DeviceMode
    {
        return $this->mode;
    }

    public function setMode(DeviceMode $mode): void
    {
        $this->mode = $mode;
    }

    public function getDimAfterSeconds(): int
    {
        return $this->dimAfterSeconds;
    }

    /**
     * Admin form: screensaver after this many minutes without touch.
     */
    public function getDimAfterMinutes(): int
    {
        return intdiv($this->dimAfterSeconds, 60);
    }

    public function setDimAfterMinutes(int $minutes): void
    {
        $this->dimAfterSeconds = $minutes * 60;
    }

    public function getFontSize(): FontSize
    {
        return $this->fontSize;
    }

    public function setFontSize(FontSize $fontSize): void
    {
        $this->fontSize = $fontSize;
    }

    public function getPairedAt(): \DateTimeImmutable
    {
        return $this->pairedAt;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(\DateTimeImmutable $at): void
    {
        $this->lastSeenAt = $at;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    /**
     * Unpairing makes the token worthless at once; the row stays as anchor for the log and completions.
     */
    public function revoke(\DateTimeImmutable $at): void
    {
        $this->revokedAt ??= $at;
    }

    // Security user

    public function getUserIdentifier(): string
    {
        \assert(null !== $this->id);

        return 'geraet-'.$this->id;
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Nothing to erase: the entity holds only the hash of the token
    }

    /**
     * Only what App\Security\Device\DeviceUserProvider needs to reload the device from the database.
     *
     * @return array{id: ?int}
     */
    public function __serialize(): array
    {
        return ['id' => $this->id];
    }

    /**
     * @param array{id: ?int} $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
    }
}
