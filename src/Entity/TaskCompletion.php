<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CompletionAction;
use App\Repository\TaskCompletionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * How and when a task was closed – by a person or at the team tablet (konzept-12 §5: then the device, never a person).
 * The person is removed after app_setting.retention_completion_days (App\MessageHandler\AnonymizeTaskCompletionsHandler);
 * only admins see who it was.
 */
#[ORM\Entity(repositoryClass: TaskCompletionRepository::class)]
#[ORM\Table(name: 'task_completion', options: ['comment' => 'Abschluss eines Vorkommens (wer/wann/Kommentar)'])]
#[ORM\UniqueConstraint(name: 'uq_completion_task', columns: ['task_id'])]
#[ORM\Index(name: 'ix_completion_anon', columns: ['anonymized_at', 'done_at'])]
#[ORM\Index(name: 'ix_completion_user', columns: ['user_id'])]
#[ORM\Index(name: 'ix_completion_device', columns: ['device_id'])]
class TaskCompletion
{
    public const int COMMENT_MAX = 500;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    /** At most one completion per task (uq_completion_task); undoing deletes it. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\Column(name: 'action', length: 15, enumType: CompletionAction::class)]
    private CompletionAction $action;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'device_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Device $device;

    #[ORM\Column(name: 'done_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $doneAt;

    #[ORM\Column(name: 'comment', length: 500, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'anonymized_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    /** @var Collection<int, TaskItemResult> one per checklist point, only when the task was completed */
    #[ORM\OneToMany(targetEntity: TaskItemResult::class, mappedBy: 'completion', cascade: ['persist'])]
    private Collection $results;

    public function __construct(Task $task, CompletionAction $action, AppUser|Device $by, \DateTimeImmutable $doneAt)
    {
        $this->task = $task;
        $this->action = $action;
        $this->user = $by instanceof AppUser ? $by : null;
        $this->device = $by instanceof Device ? $by : null;
        $this->doneAt = $doneAt;
        $this->results = new ArrayCollection();
    }

    /**
     * Ticks off the task's checklist: null = every point (one tap on "Erledigt"), else the points with these ids.
     *
     * @param list<int>|null $checkedItemIds
     */
    public function tickOff(?array $checkedItemIds): void
    {
        foreach ($this->task->getItems() as $item) {
            $this->results->add(new TaskItemResult($this, $item, null === $checkedItemIds || \in_array($item->getId(), $checkedItemIds, true)));
        }
    }

    /**
     * @return Collection<int, TaskItemResult>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function countChecked(): int
    {
        return $this->results->filter(static fn (TaskItemResult $result): bool => $result->isChecked())->count();
    }

    public function isChecked(TaskItem $item): bool
    {
        return $this->results->exists(static fn (int $key, TaskItemResult $result): bool => $result->getTaskItem() === $item && $result->isChecked());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getAction(): CompletionAction
    {
        return $this->action;
    }

    public function getUser(): ?AppUser
    {
        return $this->user;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function getDoneAt(): \DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Why it was not possible ("Seife leer") – the report carries the same title.
     */
    public function explain(?string $comment): void
    {
        $this->comment = null === $comment ? null : mb_substr($comment, 0, self::COMMENT_MAX);
    }

    public function getAnonymizedAt(): ?\DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    /**
     * True for the person or tablet that closed the task.
     */
    public function isBy(AppUser|Device $actor): bool
    {
        $by = $actor instanceof AppUser ? $this->user : $this->device;

        return null !== $by && ($by === $actor || (null !== $actor->getId() && $by->getId() === $actor->getId()));
    }
}
