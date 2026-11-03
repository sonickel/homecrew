<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssetStatus;
use App\Repository\AssetRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Aid such as a wheelchair or a lifter (konzept-3 §6).
 * Created in M5 because tasks and plans may link an aid; the aids pages follow in stage 2.
 */
#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'asset', options: ['comment' => 'Hilfsmittel'])]
#[ORM\UniqueConstraint(name: 'uq_asset_public', columns: ['public_id'])]
#[ORM\Index(name: 'fk_asset_supplier', columns: ['supplier_poi_id'])]
class Asset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'public_id', type: UuidType::NAME)]
    private Uuid $publicId;

    #[ORM\Column(name: 'name', length: 100)]
    private string $name;

    /** E-Rollstuhl, Aktivrollstuhl, Rollator, Lifter … */
    #[ORM\Column(name: 'asset_type', length: 50, nullable: true)]
    private ?string $assetType = null;

    #[ORM\Column(name: 'manufacturer', length: 100, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(name: 'model', length: 100, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(name: 'serial_number', length: 100, nullable: true)]
    private ?string $serialNumber = null;

    /** Where in the flat. */
    #[ORM\Column(name: 'location_text', length: 200, nullable: true)]
    private ?string $locationText = null;

    /** Medical supply store. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'supplier_poi_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Poi $supplier = null;

    #[ORM\Column(name: 'status', length: 15, enumType: AssetStatus::class, options: ['default' => 'in_betrieb'])]
    private AssetStatus $status = AssetStatus::InBetrieb;

    #[ORM\Column(name: 'note', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $note = null;

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

    /** Set by the database (ON UPDATE CURRENT_TIMESTAMP). */
    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        columnDefinition: 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        generated: 'ALWAYS',
        options: ['default' => new CurrentTimestamp()],
    )]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name)
    {
        $this->publicId = Uuid::v7();
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): AssetStatus
    {
        return $this->status;
    }
}
