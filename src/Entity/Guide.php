<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GuideType;
use App\Repository\GuideRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Knowledge base entry with a stable identity for links and QR codes; the content lives in guide_version (konzept-3).
 * Created in M5 because tasks and plans may link a guide; the guide pages follow in M12.
 */
#[ORM\Entity(repositoryClass: GuideRepository::class)]
#[ORM\Table(name: 'guide', options: ['comment' => 'Wissensbasis: stabile Identität (für QR/Links), Inhalt in guide_version'])]
#[ORM\UniqueConstraint(name: 'uq_guide_public', columns: ['public_id'])]
#[ORM\Index(name: 'ix_guide', columns: ['guide_type'])]
#[ORM\Index(name: 'fk_guide_locker', columns: ['locked_by_user_id'])]
#[ORM\Index(name: 'fk_guide_creator', columns: ['created_by_user_id'])]
class Guide
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'public_id', type: UuidType::NAME)]
    private Uuid $publicId;

    #[ORM\Column(name: 'guide_type', length: 15, enumType: GuideType::class)]
    private GuideType $guideType;

    #[ORM\Column(name: 'category', length: 50, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(name: 'is_archived', options: ['default' => false])]
    private bool $isArchived = false;

    /** May be translated by the AI (stage 5). */
    #[ORM\Column(name: 'translatable', options: ['default' => true])]
    private bool $translatable = true;

    /** "Ben bearbeitet gerade". */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'locked_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $lockedBy = null;

    #[ORM\Column(name: 'locked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lockedAt = null;

    #[ORM\Column(name: 'last_reviewed_on', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastReviewedOn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $createdBy;

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

    public function __construct(GuideType $guideType, ?AppUser $createdBy = null)
    {
        $this->publicId = Uuid::v7();
        $this->guideType = $guideType;
        $this->createdBy = $createdBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getGuideType(): GuideType
    {
        return $this->guideType;
    }
}
