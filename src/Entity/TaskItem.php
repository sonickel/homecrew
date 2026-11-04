<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A checklist point of a task – copied from its plan when the occurrence is created
 * (App\Service\Task\PlanSync); ticked off when the task is closed (App\Entity\TaskItemResult).
 */
#[ORM\Entity]
#[ORM\Table(name: 'task_item', options: ['comment' => 'Checkliste eines Vorkommens (inkl. Sollwerte aus Hilfsmittel-Bauteilen)'])]
#[ORM\Index(name: 'ix_task_item', columns: ['task_id', 'sort'])]
#[ORM\Index(name: 'fk_task_item_component', columns: ['asset_component_id'])]
class TaskItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\Column(name: 'sort', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $sort;

    #[ORM\Column(name: 'label', length: 150)]
    private string $label;

    #[ORM\Column(name: 'target_value', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $targetValue;

    #[ORM\Column(name: 'unit', length: 12, nullable: true)]
    private ?string $unit;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'asset_component_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AssetComponent $assetComponent;

    public function __construct(Task $task, TaskTemplateItem $from)
    {
        $this->task = $task;
        $this->sort = $from->getSort();
        $this->label = $from->getLabel();
        $this->targetValue = $from->getTargetValue();
        $this->unit = $from->getUnit();
        $this->assetComponent = $from->getAssetComponent();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
