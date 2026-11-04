<?php

declare(strict_types=1);

namespace App\Controller\Report;

use App\Entity\IssueReport;
use App\Entity\ShoppingFavorite;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Form\Report\ReportData;
use App\Form\Report\ReportType;
use App\Repository\IssueReportRepository;
use App\Repository\ShoppingFavoriteRepository;
use App\Repository\ShoppingListRepository;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Permission;
use App\Service\Household\HouseholdContext;
use App\Service\Report\IssueReports;
use App\Service\Report\SimilarReports;
use App\Service\Task\AssigneeOptions;
use App\Service\Viewer\Viewer;
use App\Service\Viewer\ViewerProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reports (konzept-13): "Melden" and the open reports for every person and the team tablet (without senders),
 * the report log with senders, handing on and closing only for admins (ReportVoter).
 */
final class ReportController extends AbstractController
{
    private const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** Where "Auch bemerkt" leads back to – a fixed list, never a URL from the request. */
    private const array BACK = ['heute' => 'app_home', 'meldungen' => 'app_report_index'];

    /** Period filter of the report log: days back, "alle" without limit. */
    private const array PERIODS = ['30' => 30, '90' => 90, '365' => 365, 'alle' => null];

    public function __construct(
        private readonly ViewerProvider $viewerProvider,
        private readonly IssueReports $reports,
        private readonly IssueReportRepository $reportRepository,
        private readonly HouseholdContext $household,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Step 1 of "Melden": what is it – leer · kaputt · fehlt · sonstiges.
     */
    #[Route('/melden', name: 'app_report_new', methods: ['GET'])]
    #[TeamTabletAllowed]
    #[IsGranted(Permission::REPORT_CREATE)]
    public function kinds(): Response
    {
        return $this->render('report/kinds.html.twig', ['kinds' => ReportKind::forReporting()]);
    }

    /**
     * Step 2: title with suggestions, optional details and what should happen. Similar open reports come first
     * ("Auch bemerkt") – while typing, and again when sending until "Trotzdem neu melden".
     */
    #[Route('/melden/{art}', name: 'app_report_form', requirements: ['art' => 'leer|kaputt|fehlt|sonstiges'], methods: ['GET', 'POST'])]
    #[TeamTabletAllowed]
    #[IsGranted(Permission::REPORT_CREATE)]
    public function report(string $art, Request $request, SimilarReports $similarReports, ShoppingFavoriteRepository $favoriteRepository, ShoppingListRepository $listRepository, AssigneeOptions $assigneeOptions): Response
    {
        $kind = ReportKind::from($art);
        $viewer = $this->viewer();
        $data = new ReportData($this->today());
        $form = $this->createForm(ReportType::class, $data, [
            'lists' => $listRepository->findActive(),
            'assignees' => $assigneeOptions->choices(),
        ]);
        $form->handleRequest($request);
        $similar = [];
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$request->getPayload()->getBoolean('trotzdem')) {
                $similar = $similarReports->find((string) $data->title, $viewer);
            }
            if ([] === $similar) {
                $this->reports->report($kind, $data, $viewer);
                $this->addFlash('ok', 'Gemeldet. Die Admins wissen Bescheid.');

                return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
            }
        }

        $suggestions = array_merge(
            array_map(static fn (ShoppingFavorite $favorite): string => $favorite->getName(), $favoriteRepository->findForList()),
            array_map(static fn (IssueReport $report): string => $report->getTitle(), $this->reportRepository->findOpenVisibleTo($viewer)),
        );

        return $this->renderWithForm('report/new.html.twig', $form, [
            'kind' => $kind,
            'similar' => $similar,
            'suggestions' => array_values(array_unique($suggestions)),
        ]);
    }

    /**
     * "Schon gemeldet?" while typing the title (Turbo frame, similar_reports_controller.js).
     */
    #[Route('/melden/aehnlich', name: 'app_report_similar', methods: ['GET'])]
    #[TeamTabletAllowed]
    #[IsGranted(Permission::REPORT_CREATE)]
    public function similar(Request $request, SimilarReports $similarReports): Response
    {
        return $this->render('report/_similar_frame.html.twig', [
            'similar' => $similarReports->find(mb_substr($request->query->getString('titel'), 0, IssueReport::TITLE_MAX), $this->viewer()),
        ]);
    }

    /**
     * Everybody: the open reports without senders. Admins: the report log with filters, senders and "Was fehlt oft?".
     */
    #[Route('/meldungen', name: 'app_report_index', methods: ['GET'])]
    #[TeamTabletAllowed]
    #[IsGranted(Permission::REPORT_CREATE)]
    public function index(Request $request, ClockInterface $clock): Response
    {
        $viewer = $this->viewer();
        if (!$this->isGranted(Permission::REPORT_MANAGE)) {
            return $this->render('report/index.html.twig', ['reports' => $this->reportRepository->findOpenVisibleTo($viewer)]);
        }

        $status = $request->query->getString('status', IssueReportRepository::FILTER_OPEN);
        if (!\in_array($status, [IssueReportRepository::FILTER_OPEN, IssueReportRepository::FILTER_CLOSED, IssueReportRepository::FILTER_ALL], true)) {
            $status = IssueReportRepository::FILTER_OPEN;
        }
        $kind = ReportKind::tryFrom($request->query->getString('art'));
        $period = $request->query->getString('zeitraum', 'alle');
        $days = self::PERIODS[$period] ?? null;
        $now = $clock->now();

        return $this->render('report/log.html.twig', [
            'reports' => $this->reportRepository->findLog($status, $kind, null === $days ? null : $now->modify(\sprintf('-%d days', $days))),
            'frequent' => $this->reportRepository->findFrequent($now->modify('-365 days')),
            'filter' => ['status' => $status, 'art' => $kind?->value, 'zeitraum' => \array_key_exists($period, self::PERIODS) ? $period : 'alle'],
            'kinds' => [ReportKind::NichtMoeglich, ...ReportKind::forReporting()],
        ]);
    }

    /**
     * "Auch bemerkt": counts instead of a second report.
     */
    #[Route('/meldungen/{publicId}/auch-bemerkt', name: 'app_report_confirm', requirements: ['publicId' => self::UUID], methods: ['POST'])]
    #[TeamTabletAllowed]
    #[IsGranted(Permission::REPORT_CREATE)]
    public function confirm(string $publicId, Request $request): Response
    {
        $report = $this->visibleReport($publicId);
        $this->checkToken($request);
        if ($this->isGranted(Permission::REPORT_CONFIRM, $report) && $this->reports->confirm($report)) {
            $this->addFlash('ok', $this->translator->trans('Danke – „%title%“ ist als „auch bemerkt“ vermerkt.', ['%title%' => $report->getTitle()]));
        } else {
            $this->addFlash('info', $this->translator->trans('„%title%“ ist schon erledigt.', ['%title%' => $report->getTitle()]));
        }
        $back = $request->getPayload()->getString('zurueck');

        return $this->redirectToRoute(self::BACK[$back] ?? 'app_home', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/meldungen/{publicId}', name: 'app_report_show', requirements: ['publicId' => self::UUID], methods: ['GET'])]
    #[IsGranted(Permission::REPORT_MANAGE)]
    public function show(string $publicId): Response
    {
        return $this->render('report/show.html.twig', ['report' => $this->visibleReport($publicId)]);
    }

    /**
     * Admins hand a report on: to a list or as a task for a person or role.
     */
    #[Route('/meldungen/{publicId}/weitergeben', name: 'app_report_forward', requirements: ['publicId' => self::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::REPORT_MANAGE)]
    public function handOn(string $publicId, Request $request, ShoppingListRepository $listRepository, AssigneeOptions $assigneeOptions): Response
    {
        $report = $this->visibleReport($publicId);
        if (!$report->isOpen()) {
            return $this->alreadyClosed($report);
        }
        $data = new ReportData($this->today());
        $data->title = $report->getTitle();
        $data->note = $report->getNote();
        $data->adminsOnly = $report->isForAdminsOnly();
        $data->followUp = ReportData::FOLLOW_LIST;
        $form = $this->createForm(ReportType::class, $data, [
            'mode' => ReportType::MODE_FORWARD,
            'lists' => $listRepository->findActive(),
            'assignees' => $assigneeOptions->choices(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->reports->forward($report, $data, $this->viewer())) {
                return $this->alreadyClosed($report);
            }
            $this->addFlash('ok', $this->translator->trans('„%title%“ ist weitergegeben.', ['%title%' => $report->getTitle()]));

            return $this->toReport($report);
        }

        return $this->renderWithForm('report/forward.html.twig', $form, ['report' => $report]);
    }

    /**
     * Admins close a report as done or discard it ("erledigt" or "verworfen"), with an optional note.
     */
    #[Route('/meldungen/{publicId}/schliessen', name: 'app_report_close', requirements: ['publicId' => self::UUID], methods: ['POST'])]
    #[IsGranted(Permission::REPORT_MANAGE)]
    public function close(string $publicId, Request $request): Response
    {
        $report = $this->visibleReport($publicId);
        $this->checkToken($request);
        $payload = $request->getPayload();
        $status = ReportStatus::tryFrom($payload->getString('status'));
        if (null === $status || $status->isOpen()) {
            throw $this->createNotFoundException('A report closes as "erledigt" or "verworfen".');
        }
        $admin = $this->viewer()->getPerson() ?? throw $this->createAccessDeniedException();
        if (!$this->reports->close($report, $status, $payload->getString('notiz'), $admin)) {
            return $this->alreadyClosed($report);
        }
        $this->addFlash('ok', $this->translator->trans(ReportStatus::Erledigt === $status ? '„%title%“ ist erledigt.' : '„%title%“ ist verworfen.', ['%title%' => $report->getTitle()]));

        return $this->toReport($report);
    }

    /**
     * Unknown and invisible reports look the same: 404.
     */
    private function visibleReport(string $publicId): IssueReport
    {
        $report = $this->reportRepository->findOneByPublicId($publicId);
        if (null === $report || !$this->isGranted(Permission::REPORT_VIEW, $report)) {
            throw $this->createNotFoundException('Unknown report.');
        }

        return $report;
    }

    private function alreadyClosed(IssueReport $report): Response
    {
        $this->addFlash('info', $this->translator->trans('„%title%“ ist schon abgeschlossen.', ['%title%' => $report->getTitle()]));

        return $this->toReport($report);
    }

    private function toReport(IssueReport $report): Response
    {
        return $this->redirectToRoute('app_report_show', ['publicId' => $report->getPublicId()->toRfc4122()], Response::HTTP_SEE_OTHER);
    }

    private function today(): string
    {
        return $this->household->today()->format('Y-m-d');
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
     * Forms answer 422 after a submit that did not lead on, so Turbo shows the page.
     *
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $parameters
     */
    private function renderWithForm(string $template, FormInterface $form, array $parameters = []): Response
    {
        return $this->render($template, ['form' => $form, ...$parameters], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
