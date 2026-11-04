<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A checklist point of a plan; every occurrence gets a copy (App\Entity\TaskItem).
 * Target values and parts of aids (target_value, unit, asset_component_id) follow in stage 2.
 */
#[ORM\Entity]
#[ORM\Table(name: 'task_template_item', options: ['comment' => 'Checklistenpunkte einer Vorlage (zusätzlich zu den automatisch erzeugten Sollwert-Punkten)'])]
#[ORM\Index(name: 'ix_template_item', columns: ['template_id', 'sort'])]
#[ORM\Index(name: 'fk_template_item_component', columns: ['asset_component_id'])]
class TaskTemplateItem
{
    public const int LABEL_MAX = 150;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TaskTemplate $template;

    #[ORM\Column(name: 'sort', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $sort;

    #[ORM\Column(name: 'label', length: 150)]
    private string $label;

    #[ORM\Column(name: 'target_value', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $targetValue = null;

    #[ORM\Column(name: 'unit', length: 12, nullable: true)]
    private ?string $unit = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'asset_component_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AssetComponent $assetComponent = null;

    public function __construct(TaskTemplate $template, int $sort, string $label)
    {
        $this->template = $template;
        $this->sort = $sort;
        $this->label = $label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function relabel(string $label): void
    {
        $this->label = $label;
    }

    public function getTargetValue(): ?string
    {
        return $this->targetValue;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function getAssetComponent(): ?AssetComponent
    {
        return $this->assetComponent;
    }
}
