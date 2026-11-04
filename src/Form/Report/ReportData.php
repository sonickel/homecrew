<?php

declare(strict_types=1);

namespace App\Form\Report;

use App\Entity\IssueReport;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Enum\ReportVisibility;
use App\Service\Task\Assignment;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * What the report dialog edits ("Melden", "Nicht möglich", "Weitergeben" in the log); App\Service\Report\IssueReports
 * applies it. "Was soll passieren?": only report · an entry on a list · a task for a person or role (konzept-13 §2).
 */
final class ReportData
{
    public const string FOLLOW_NONE = 'keine';
    public const string FOLLOW_LIST = 'liste';
    public const string FOLLOW_TASK = 'aufgabe';

    #[Assert\NotBlank(message: 'Bitte schreib kurz, was los ist.')]
    #[Assert\Length(max: IssueReport::TITLE_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen – Einzelheiten passen in die Notiz.')]
    public ?string $title = null;

    #[Assert\Length(max: IssueReport::NOTE_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $note = null;

    public bool $urgent = false;

    public bool $adminsOnly = false;

    #[Assert\Choice(choices: [self::FOLLOW_NONE, self::FOLLOW_LIST, self::FOLLOW_TASK])]
    public string $followUp = self::FOLLOW_NONE;

    public ?ShoppingList $list = null;

    #[Assert\Length(max: ShoppingItem::NAME_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $itemName = null;

    public ?Assignment $assignee = null;

    public ?\DateTimeImmutable $dueDate = null;

    /** "Trotzdem neu melden" after similar open reports were shown. */
    public bool $confirmedNew = false;

    /** Earliest due date of a follow-up task (Y-m-d): today. */
    public string $earliestDueDate;

    public function __construct(string $today)
    {
        $this->earliestDueDate = $today;
        $this->dueDate = new \DateTimeImmutable($today);
    }

    /**
     * What goes on the list – the title when nothing else is given.
     */
    public function itemForList(): string
    {
        $item = trim((string) $this->itemName);

        return '' === $item ? trim((string) $this->title) : $item;
    }

    public function visibility(): ReportVisibility
    {
        return $this->adminsOnly ? ReportVisibility::Admins : ReportVisibility::Alle;
    }

    #[Assert\Callback]
    public function validateFollowUp(ExecutionContextInterface $context): void
    {
        if (self::FOLLOW_LIST === $this->followUp) {
            if (null === $this->list) {
                $context->buildViolation('Bitte wähle eine Liste.')->atPath('list')->addViolation();
            }
            if ('' === $this->itemForList() || ($this->adminsOnly && '' === trim((string) $this->itemName))) {
                $context->buildViolation($this->adminsOnly ? 'Bitte schreib, was auf die Liste soll – den Eintrag sehen alle.' : 'Bitte schreib, was auf die Liste soll.')->atPath('itemName')->addViolation();
            } elseif (mb_strlen($this->itemForList()) > ShoppingItem::NAME_MAX) {
                $context->buildViolation('Bitte schreib kürzer, was auf die Liste soll (höchstens {{ limit }} Zeichen).')->setParameter('{{ limit }}', (string) ShoppingItem::NAME_MAX)->atPath('itemName')->addViolation();
            }
        }
        if (self::FOLLOW_TASK === $this->followUp) {
            if (null === $this->assignee) {
                $context->buildViolation('Bitte wähle, wer sich kümmert.')->atPath('assignee')->addViolation();
            }
            if (null === $this->dueDate) {
                $context->buildViolation('Bitte wähle einen Tag.')->atPath('dueDate')->addViolation();
            } elseif ($this->dueDate->format('Y-m-d') < $this->earliestDueDate) {
                $context->buildViolation('Der Tag liegt in der Vergangenheit. Bitte wähle heute oder später.')->atPath('dueDate')->addViolation();
            }
        }
    }
}
