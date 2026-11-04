<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Form\Task\PlanData;
use App\Form\Task\PlanType;
use App\Repository\TaskCompletionRepository;
use App\Repository\TaskRepository;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Permission;
use App\Service\Household\HouseholdContext;
use App\Service\Task\AssigneeOptions;
use App\Service\Task\Assignment;
use App\Service\Task\PlanActions;
use App\Service\Task\PlanOverview;
use App\Service\Task\TaskActions;
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
 * "Routinen & Wartung" (konzept-2 §5.6, decisions M6): the plans by urgency, a plan with its history and
 * "Jetzt erledigt" – for every person and the team tablet; creating, changing, ending and resuming plans for
 * admins and persons with the switch "wiederkehrende Aufgaben" (App\Security\Voter\PlanVoter).
 */
final class PlanController extends AbstractController
{
    public function __construct(
        private readonly ViewerProvider $viewerProvider,
        private readonly PlanActions $actions,
        private readonly HouseholdContext $household,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/routinen', name: 'app_plan_index', methods: ['GET'])]
    #[IsGranted(Permission::PLAN_VIEW)]
    #[TeamTabletAllowed]
    public function index(PlanOverview $overview): Response
    {
        return $this->render('plan/index.html.twig', [
            'rows' => $overview->forViewer($this->viewer()),
        ]);
    }

    #[Route('/routinen/neu', name: 'app_plan_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::PLAN_MANAGE)]
    public function new(Request $request, AssigneeOptions $assigneeOptions, VisibilityRules $rules): Response
    {
        $viewer = $this->viewer();
        $today = $this->household->today();
        $data = new PlanData($today->format('Y-m-d'));
        $data->startsOn = new \DateTimeImmutable($today->format('Y-m-d'));
        $data->weekdays = [['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'][(int) $today->format('N') - 1]];
        $data->monthDay = (int) $today->format('j');
        $data->assignee = Assignment::role(AssigneeRole::Assistenz);

        $form = $this->createForm(PlanType::class, $data, [
            'assignees' => $assigneeOptions->choices(),
            'allow_private' => $rules->mayKeepPrivate($viewer),
            'manager_name' => $this->household->managerName(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $plan = $this->actions->create($data, $viewer);
            $this->addFlash('ok', $this->translator->trans('„%title%“ ist angelegt.', ['%title%' => $plan->getTitle()]));

            return $this->redirectToRoute('app_plan_show', ['id' => $plan->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('plan/new.html.twig', $form);
    }

    #[Route('/routinen/{id}', name: 'app_plan_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(Permission::PLAN_VIEW, 'plan')]
    #[TeamTabletAllowed]
    public function show(TaskTemplate $plan, PlanOverview $overview, TaskCompletionRepository $completionRepository): Response
    {
        $viewer = $this->viewer();

        return $this->render('plan/show.html.twig', [
            'row' => $overview->row($plan, $viewer),
            'history' => $completionRepository->findHistory($plan),
            'admin' => $viewer->isAdmin(),
        ]);
    }

    #[Route('/routinen/{id}/bearbeiten', name: 'app_plan_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::PLAN_MANAGE, 'plan')]
    public function edit(TaskTemplate $plan, Request $request, AssigneeOptions $assigneeOptions, VisibilityRules $rules): Response
    {
        $viewer = $this->viewer();
        $data = PlanData::fromPlan($plan, $this->household->today()->format('Y-m-d'));
        $creator = $plan->getCreatedBy();
        $form = $this->createForm(PlanType::class, $data, [
            'assignees' => $assigneeOptions->choices(Assignment::of($plan)),
            // Whoever keeps a private plan may keep it private; nobody else turns a plan private
            'allow_private' => $rules->mayKeepPrivate($viewer) && null !== $creator && $viewer->isPerson($creator),
            'manager_name' => $this->household->managerName(),
            'with_pause' => true,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->actions->update($plan, $data, $viewer);
            $this->addFlash('ok', 'Der Plan ist gespeichert.');

            return $this->redirectToRoute('app_plan_show', ['id' => $plan->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('plan/edit.html.twig', $form, ['plan' => $plan]);
    }

    /**
     * "Jetzt erledigt": closes the open occurrence due first – every point of its checklist counts as ticked off.
     */
    #[Route('/routinen/{id}/erledigt', name: 'app_plan_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::PLAN_VIEW, 'plan')]
    #[TeamTabletAllowed]
    public function complete(TaskTemplate $plan, Request $request, TaskRepository $taskRepository, TaskActions $taskActions): Response
    {
        $this->checkToken($request);
        $task = $taskRepository->findFirstOpenOccurrence($plan);
        if (null !== $task && $this->isGranted(Permission::TASK_ACT, $task) && $taskActions->close($task, CompletionAction::Erledigt, $this->viewer())) {
            $this->addFlash('undo', ['id' => $task->getId(), 'title' => $task->getTitle(), 'action' => CompletionAction::Erledigt->value, 'back' => 'plan']);
        } else {
            $this->addFlash('info', $this->translator->trans('Bei „%title%“ ist gerade nichts offen.', ['%title%' => $plan->getTitle()]));
        }

        return $this->redirectToRoute('app_plan_show', ['id' => $plan->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/routinen/{id}/beenden', name: 'app_plan_end', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::PLAN_MANAGE, 'plan')]
    public function end(TaskTemplate $plan, Request $request): Response
    {
        $this->checkToken($request);
        $this->actions->end($plan);
        $this->addFlash('ok', $this->translator->trans('„%title%“ ist beendet. Es entstehen keine neuen Aufgaben mehr.', ['%title%' => $plan->getTitle()]));

        return $this->redirectToRoute('app_plan_show', ['id' => $plan->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/routinen/{id}/fortsetzen', name: 'app_plan_resume', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::PLAN_MANAGE, 'plan')]
    public function resume(TaskTemplate $plan, Request $request): Response
    {
        $this->checkToken($request);
        $this->actions->resume($plan);
        $this->addFlash('ok', $this->translator->trans('„%title%“ läuft wieder.', ['%title%' => $plan->getTitle()]));

        return $this->redirectToRoute('app_plan_show', ['id' => $plan->getId()], Response::HTTP_SEE_OTHER);
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
