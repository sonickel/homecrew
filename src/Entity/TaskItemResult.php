<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Whether a checklist point was ticked off when the task was closed (measured values follow in stage 2).
 * Undoing the completion deletes the results with it (ON DELETE CASCADE).
 */
#[ORM\Entity]
#[ORM\Table(name: 'task_item_result', options: ['comment' => 'Ergebnis je Checklistenpunkt (abgehakt, Messwert)'])]
#[ORM\UniqueConstraint(name: 'uq_item_result', columns: ['completion_id', 'task_item_id'])]
#[ORM\Index(name: 'ix_item_result_item', columns: ['task_item_id'])]
class TaskItemResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(name: 'completion_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TaskCompletion $completion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'task_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TaskItem $taskItem;

    #[ORM\Column(name: 'is_checked', options: ['default' => false])]
    private bool $checked;

    #[ORM\Column(name: 'measured_value', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $measuredValue = null;

    public function __construct(TaskCompletion $completion, TaskItem $taskItem, bool $checked)
    {
        $this->completion = $completion;
        $this->taskItem = $taskItem;
        $this->checked = $checked;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskItem(): TaskItem
    {
        return $this->taskItem;
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }
}
