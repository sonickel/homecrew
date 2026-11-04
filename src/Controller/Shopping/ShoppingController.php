<?php

declare(strict_types=1);

namespace App\Controller\Shopping;

use App\Entity\IssueReport;
use App\Entity\ShoppingFavorite;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Form\Shopping\ShoppingItemData;
use App\Form\Shopping\ShoppingItemType;
use App\Repository\IssueReportRepository;
use App\Repository\ShoppingFavoriteRepository;
use App\Repository\ShoppingItemRepository;
use App\Repository\ShoppingListRepository;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Permission;
use App\Service\Household\HouseholdContext;
use App\Service\Shopping\ShoppingItems;
use App\Service\Viewer\Viewer;
use App\Service\Viewer\ViewerProvider;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lists (konzept-2 §9): every person and the team tablet reads, adds, ticks off and prints; changing and removing an
 * entry is for admins and whoever added it (ShoppingVoter). Switched-off lists are gone for everybody.
 */
#[TeamTabletAllowed]
#[IsGranted(Permission::LIST_USE)]
final class ShoppingController extends AbstractController
{
    public function __construct(
        private readonly ViewerProvider $viewerProvider,
        private readonly ShoppingItems $items,
        private readonly ShoppingItemRepository $itemRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/listen', name: 'app_list_index', methods: ['GET'])]
    public function index(ShoppingListRepository $listRepository): Response
    {
        return $this->render('list/index.html.twig', [
            'lists' => $listRepository->findActive(),
            'open' => $this->itemRepository->countOpenPerList(),
        ]);
    }

    #[Route('/listen/{id}', name: 'app_list_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(ShoppingList $list, Request $request, ShoppingFavoriteRepository $favoriteRepository, HouseholdContext $household): Response
    {
        $this->assertActive($list);
        $data = new ShoppingItemData();
        $form = $this->createForm(ShoppingItemType::class, $data, ['action' => $this->generateUrl('app_list_show', ['id' => $list->getId()])]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->add($list, $data);

            return $this->toList($list);
        }

        return $this->renderWithForm('list/show.html.twig', $form, [
            'list' => $list,
            'items' => $this->itemRepository->findOpen($list),
            'checked' => $this->itemRepository->findCheckedSince($list, $household->today()),
            'favorites' => $favoriteRepository->findForList($list),
        ]);
    }

    #[Route('/listen/{id}/lieblingsartikel/{favorite}', name: 'app_list_favorite_add', requirements: ['id' => '\d+', 'favorite' => '\d+'], methods: ['POST'])]
    public function addFavorite(#[MapEntity(id: 'id')] ShoppingList $list, #[MapEntity(id: 'favorite')] ShoppingFavorite $favorite, Request $request): Response
    {
        $this->assertActive($list);
        if (!\in_array($favorite->getList()?->getId(), [null, $list->getId()], true)) {
            throw $this->createNotFoundException('The favourite belongs to another list.');
        }
        $this->checkToken($request);
        if ($this->items->isOnList($list, $favorite->getName())) {
            $this->addFlash('info', $this->translator->trans('„%name%“ steht schon auf der Liste.', ['%name%' => $favorite->getName()]));
        } else {
            $this->items->addFavorite($list, $favorite, $this->viewer());
            $this->addFlash('ok', $this->translator->trans('„%name%“ steht jetzt auf der Liste.', ['%name%' => $favorite->getName()]));
        }

        return $this->toList($list);
    }

    #[Route('/listen/eintraege/{id}/abhaken', name: 'app_list_item_check', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function check(ShoppingItem $item, Request $request): Response
    {
        $this->assertActive($item->getList());
        $this->checkToken($request);
        $this->items->check($item);

        return $this->toList($item->getList());
    }

    #[Route('/listen/eintraege/{id}/zurueckholen', name: 'app_list_item_uncheck', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uncheck(ShoppingItem $item, Request $request): Response
    {
        $this->assertActive($item->getList());
        $this->checkToken($request);
        $this->items->uncheck($item);

        return $this->toList($item->getList());
    }

    #[Route('/listen/eintraege/{id}/bearbeiten', name: 'app_list_item_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(Permission::LIST_ITEM_EDIT, 'item')]
    public function edit(ShoppingItem $item, Request $request, IssueReportRepository $reportRepository): Response
    {
        $this->assertActive($item->getList());
        $data = ShoppingItemData::fromItem($item);
        $form = $this->createForm(ShoppingItemType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->items->update($item, $data);
            $this->addFlash('ok', $this->translator->trans('„%name%“ ist gespeichert.', ['%name%' => $item->getName()]));

            return $this->toList($item->getList());
        }

        $reports = array_filter($reportRepository->findByFollowUpItem($item), static fn (IssueReport $report): bool => $report->isOpen());

        return $this->renderWithForm('list/edit.html.twig', $form, ['item' => $item, 'waiting_report' => [] !== $reports]);
    }

    #[Route('/listen/eintraege/{id}/entfernen', name: 'app_list_item_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(Permission::LIST_ITEM_EDIT, 'item')]
    public function remove(ShoppingItem $item, Request $request): Response
    {
        $list = $item->getList();
        $this->assertActive($list);
        $this->checkToken($request);
        $name = $item->getName();
        $this->items->remove($item);
        $this->addFlash('ok', $this->translator->trans('„%name%“ ist von der Liste entfernt.', ['%name%' => $name]));

        return $this->toList($list);
    }

    /**
     * Print view (konzept-2 §10): the open entries with boxes to tick off, A4 black and white.
     */
    #[Route('/listen/{id}/drucken', name: 'app_list_print', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function print(ShoppingList $list): Response
    {
        $this->assertActive($list);

        return $this->render('list/print.html.twig', [
            'list' => $list,
            'items' => $this->itemRepository->findOpen($list),
        ]);
    }

    private function add(ShoppingList $list, ShoppingItemData $data): void
    {
        $name = trim((string) $data->name);
        if ($this->items->isOnList($list, $name)) {
            $this->addFlash('info', $this->translator->trans('„%name%“ steht schon auf der Liste.', ['%name%' => $name]));

            return;
        }
        $this->items->add($list, $data, $this->viewer());
        $this->addFlash('ok', $this->translator->trans('„%name%“ steht jetzt auf der Liste.', ['%name%' => $name]));
    }

    private function assertActive(ShoppingList $list): void
    {
        if (!$list->isActive()) {
            throw $this->createNotFoundException('The list is switched off.');
        }
    }

    private function toList(ShoppingList $list): Response
    {
        return $this->redirectToRoute('app_list_show', ['id' => $list->getId()], Response::HTTP_SEE_OTHER);
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
    private function renderWithForm(string $template, FormInterface $form, array $parameters = []): Response
    {
        return $this->render($template, ['form' => $form, ...$parameters], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
