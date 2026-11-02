<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StepFreeAccess;
use App\Repository\PoiRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Place with accessibility details and navigation link (konzept-3 §7).
 * Created in M4 because emergency_contact refers to it; the places pages follow in M12.
 */
#[ORM\Entity(repositoryClass: PoiRepository::class)]
#[ORM\Table(name: 'poi', options: ['comment' => 'Orte mit Barrierefreiheit und Navigations-Link'])]
#[ORM\UniqueConstraint(name: 'uq_poi_public', columns: ['public_id'])]
#[ORM\Index(name: 'ix_poi_cat', columns: ['category'])]
class Poi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'public_id', type: UuidType::NAME)]
    private Uuid $publicId;

    #[ORM\Column(name: 'name', length: 100)]
    private string $name;

    /** Free text such as "arzt", "apotheke", "spazierweg" (no fixed list in the schema). */
    #[ORM\Column(name: 'category', length: 30)]
    private string $category;

    #[ORM\Column(name: 'street', length: 150, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(name: 'postal_code', length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(name: 'city', length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'latitude', type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(name: 'longitude', type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(name: 'phone', length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'website', length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(name: 'opening_hours', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $openingHours = null;

    #[ORM\Column(name: 'note', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'preferences', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $preferences = null;

    #[ORM\Column(name: 'a11y_step_free', length: 10, nullable: true, enumType: StepFreeAccess::class)]
    private ?StepFreeAccess $stepFree = null;

    #[ORM\Column(name: 'a11y_entrance', length: 500, nullable: true)]
    private ?string $entrance = null;

    #[ORM\Column(name: 'a11y_elevator', nullable: true)]
    private ?bool $elevator = null;

    #[ORM\Column(name: 'a11y_toilet', nullable: true)]
    private ?bool $accessibleToilet = null;

    #[ORM\Column(name: 'a11y_parking', nullable: true)]
    private ?bool $accessibleParking = null;

    #[ORM\Column(name: 'a11y_auto_door', nullable: true)]
    private ?bool $automaticDoor = null;

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

    public function __construct(string $name, string $category)
    {
        $this->publicId = Uuid::v7();
        $this->name = $name;
        $this->category = $category;
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

    public function getCategory(): string
    {
        return $this->category;
    }
}
