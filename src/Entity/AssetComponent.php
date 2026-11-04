<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Part of an aid, e.g. "Reifen hinten links" (konzept-3 §6).
 * Created in M6 because checklist points may refer to it; target values and measurements follow in stage 2.
 */
#[ORM\Entity]
#[ORM\Table(name: 'asset_component', options: ['comment' => 'Bauteile eines Hilfsmittels'])]
#[ORM\Index(name: 'ix_component_asset', columns: ['asset_id', 'sort'])]
class AssetComponent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Asset $asset;

    #[ORM\Column(name: 'name', length: 100)]
    private string $name;

    /** reifen, akku, polster … */
    #[ORM\Column(name: 'component_type', length: 30, nullable: true)]
    private ?string $componentType = null;

    #[ORM\Column(name: 'sort', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $sort = 0;

    public function __construct(Asset $asset, string $name)
    {
        $this->asset = $asset;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
