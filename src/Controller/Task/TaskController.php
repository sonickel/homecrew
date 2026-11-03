<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Entity\Task;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\UserRole;
use App\Form\Task\HandOverType;
use App\Form\Task\PostponeType;
use App\Form\Task\TaskData;
use App\Form\Task\TaskType;
use App\Repository\TaskCompletionRepository;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Permission;
use App\Service\Household\HouseholdContext;
use App\Service\Household\WeekDays;
use App\Service\Task\AssigneeOptions;
use App\Service\Task\Assignment;
use App\Service\Task\TaskActions;
use App\Service\Task\TodayTasks;
use App\Service\Viewer\Viewer;
use App\Service\Viewer\ViewerProvider;
use App\Service\Visibility\VisibilityRules;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * One-off tasks (konzept-2 §5, decisions M5): list of open tasks, detail, create, change, complete, skip,
 * "Rückgängig", postpone and hand on – for every person and the team tablet; the rights are in TaskVoter.
 */
#[TeamTabletAllowed]
final class TaskController extends AbstractController
{
    /** Where the small action forms lead back to – a fixed list, never a URL from the request. */
    private const array BACK = ['heute' => 'app_home', 'aufgaben' => 'app_task_index'];

    public function __construct(
        private readonly ViewerProvider $viewerProvider,
        private readonly TaskActions $actions,
        private readonly TodayTasks $todayTasks,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/aufgaben', name: 'app_task_index', methods: ['GET'])]
    #[IsGranted(Permission::TASK_CREATE)]
    public function index(): Response
    {
        return $this->render('task/index.html.twig', [
            'groups' => $this->todayTasks->allOpen($this->viewer()),
        ]);
    }

    #[Route('/aufgaben/neu', name: 'app_task_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::TASK_CREATE)]
    public function new(Request $request, AssigneeOptions $assigneeOptions, VisibilityRules $rules, HouseholdContext $household): Response
    {
        $viewer = $this->viewer();
        $today = $this->todayTasks->today();
        $data = new TaskData($today);
        $data->dueDate = new \DateTimeImmutable($today);
        // Assistants and the tablet mostly note something for whoever is there, the family first for the admins
        $data->assignee = null === $viewer->getPerson() || $viewer->hasRole(UserRole::Assistenz)
            ? Assignment::present()
            : Assignment::role(AssigneeRole::Admins);

        $form = $this->createForm(TaskType::class, $data, [
            'assignees' => $assigneeOptions->choices(),
            'allow_private' => $rules->mayKeepPrivate($viewer),
            'manager_name' => $household->managerName(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $task = $this->actions->create($data, $viewer);
            $this->addFlash('ok', $this->translator->trans('„%title%“ ist angelegt.', ['%title%' => $task->getTitle()]));

            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('task/new.html.twig', $form);
    }

    #[Route('/aufgaben/{id}', name: 'app_task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(Permission::TASK_VIEW, 'task')]
    public function show(Task $task, TaskCompletionRepository $completionRepository): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'completion' => $task->isOpen() ? null : $completionRepository->findForTask($task),
            'admin' => $this->viewer()->isAdmin(),
        ]);
    }

    #[Route('/aufgaben/{id}/bearbeiten', name: 'app_task_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::TASK_VIEW, 'task')]
    public function edit(Task $task, Request $request, AssigneeOptions $assigneeOptions, VisibilityRules $rules, HouseholdContext $household): Response
    {
        if (!$task->isOpen()) {
            return $this->alreadyClosed($task);
        }
        $this->denyAccessUnlessGranted(Permission::TASK_EDIT, $task);
        $viewer = $this->viewer();
        $data = TaskData::fromTask($task, $this->todayTasks->today());
        $form = $this->createForm(TaskType::class, $data, [
            'assignees' => $assigneeOptions->choices(Assignment::of($task)),
            // Whoever keeps a private task may keep it private; nobody else turns a task private
            'allow_private' => $rules->mayKeepPrivate($viewer) && null !== $task->getCreatedBy() && $viewer->isPerson($task->getCreatedBy()),
            'manager_name' => $household->managerName(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->actions->update($task, $data, $viewer)) {
                return $this->alreadyClosed($task);
            }
            $this->addFlash('ok', 'Die Aufgabe ist gespeichert.');

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('task/edit.html.twig', $form, ['task' => $task]);
    }

    #[Route('/aufgaben/{id}/erledigt', name: 'app_task_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::TASK_VIEW, 'task')]
    public function complete(Task $task, Request $request): Response
    {
        return $this->close($task, CompletionAction::Erledigt, $request);
    }

    #[Route('/aufgaben/{id}/ueberspringen', name: 'app_task_skip', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::TASK_VIEW, 'task')]
    public function skip(Task $task, Request $request): Response
    {
        return $this->close($task, CompletionAction::Uebersprungen, $request);
    }

    #[Route('/aufgaben/{id}/rueckgaengig', name: 'app_task_undo', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::TASK_VIEW, 'task')]
    public function undo(Task $task, Request $request): Response
    {
        $this->checkToken($request);
        if ($this->actions->undo($task, $this->viewer())) {
            $this->addFlash('ok', $this->translator->trans('„%title%“ ist wieder offen.', ['%title%' => $task->getTitle()]));
        } else {
            $this->addFlash('error', 'Rückgängig geht nur kurz nach dem Antippen – und nur dort, wo es angetippt wurde.');
        }

        return $this->back($request);
    }

    #[Route('/aufgaben/{id}/verschieben', name: 'app_task_postpone', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::TASK_VIEW, 'task')]
    public function postpone(Task $task, Request $request): Response
    {
        if (!$task->isOpen()) {
            return $this->alreadyClosed($task);
        }
        $this->denyAccessUnlessGranted(Permission::TASK_ACT, $task);
        $today = $this->todayTasks->today();
        $form = $this->createForm(PostponeType::class, null, [
            'today' => $today,
            'current' => $task->getDueDate()->format('Y-m-d'),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \DateTimeImmutable $dueDate */
            $dueDate = $form->get('dueDate')->getData();
            if (!$this->actions->postpone($task, $dueDate)) {
                return $this->alreadyClosed($task);
            }
            $this->addFlash('ok', $this->translator->trans('„%title%“ ist verschoben.', ['%title%' => $task->getTitle()]));

            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        // A small week calendar instead of a date field: one tap moves the task (wish 03.11.)
        $offset = max(0, min(WeekDays::MAX_OFFSET, $request->query->getInt('woche')));

        return $this->renderForm('task/postpone.html.twig', $form, [
            'task' => $task,
            'weeks' => WeekDays::weeks($today, $offset),
            'week_offset' => $offset,
            'today' => $today,
        ]);
    }

    #[Route('/aufgaben/{id}/weitergeben', name: 'app_task_handover', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::TASK_VIEW, 'task')]
    public function handOver(Task $task, Request $request, AssigneeOptions $assigneeOptions): Response
    {
        if (!$task->isOpen()) {
            return $this->alreadyClosed($task);
        }
        $this->denyAccessUnlessGranted(Permission::TASK_HANDOVER, $task);
        $current = Assignment::of($task);
        $choices = array_values(array_filter($assigneeOptions->choices(), static fn (Assignment $choice): bool => !$choice->equals($current)));
        $form = $this->createForm(HandOverType::class, null, ['assignees' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Assignment $to */
            $to = $form->get('assignee')->getData();
            if (!$this->actions->handOver($task, $to, $this->viewer())) {
                return $this->alreadyClosed($task);
            }
            $this->addFlash('ok', $this->translator->trans('„%title%“ ist jetzt bei: %name%.', ['%title%' => $task->getTitle(), '%name%' => $to->label()]));

            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('task/handover.html.twig', $form, ['task' => $task, 'current' => $current]);
    }

    private function close(Task $task, CompletionAction $action, Request $request): Response
    {
        $this->checkToken($request);
        if ($this->actions->close($task, $action, $this->viewer())) {
            $this->addFlash('undo', ['id' => $task->getId(), 'title' => $task->getTitle(), 'action' => $action->value, 'back' => $this->backKey($request)]);
        } else {
            $this->addFlash('info', $this->translator->trans('„%title%“ ist schon abgeschlossen.', ['%title%' => $task->getTitle()]));
        }

        return $this->back($request);
    }

    /**
     * Closed meanwhile – e.g. at the tablet while this page was open: a hint instead of "Kein Zutritt".
     */
    private function alreadyClosed(Task $task): Response
    {
        $this->addFlash('info', $this->translator->trans('„%title%“ ist schon abgeschlossen.', ['%title%' => $task->getTitle()]));

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
    }

    private function viewer(): Viewer
    {
        return $this->viewerProvider->current() ?? throw $this->createAccessDeniedException();
    }

    private function checkToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('submit', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function backKey(Request $request): string
    {
        $back = $request->getPayload()->getString('zurueck');

        return \array_key_exists($back, self::BACK) ? $back : 'heute';
    }

    private function back(Request $request): Response
    {
        return $this->redirectToRoute(self::BACK[$this->backKey($request)], status: Response::HTTP_SEE_OTHER);
    }

    /**
     * Forms answer 422 after a failed submit, so Turbo shows the errors.
     *
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $parameters
     */
    private function renderForm(string $template, FormInterface $form, array $parameters = []): Response
    {
        return $this->render($template, ['form' => $form, ...$parameters], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
