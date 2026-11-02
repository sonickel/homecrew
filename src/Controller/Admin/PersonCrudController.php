<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Repository\WebauthnCredentialRepository;
use App\Security\Permission;
use App\Service\Account\AccessOverview;
use App\Service\Audit\AuditLogger;
use App\Service\Household\HouseholdContext;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → Personen (konzept-11 §2): master data and rights. The access (start password, PIN, invitation,
 * reset, lock, leaving) has its own page, its actions live in App\Controller\Admin\PersonAccessController.
 * Persons are never deleted here – they stay as anchor for working times.
 *
 * Rights rules: the manager's role and admin right never change, nobody changes their own role or admin right
 * (fields disabled, so submitted values are ignored); the rest is App\Security\Voter\PersonVoter.
 *
 * @extends AbstractCrudController<AppUser>
 */
#[AdminRoute(path: '/personen', name: 'person')]
#[IsGranted(Permission::ADMIN)]
final class PersonCrudController extends AbstractCrudController
{
    /** Virtual columns need an explicit template, otherwise EasyAdmin shows them as "Nicht zugreifbar". */
    private const string TEXT_TEMPLATE = '@EasyAdmin/crud/field/text.html.twig';

    public function __construct(
        private readonly AccessOverview $accessOverview,
        private readonly HouseholdContext $household,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AppUser::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Person')
            ->setEntityLabelInPlural('Personen')
            ->setPageTitle(Crud::PAGE_INDEX, 'Personen')
            ->setPageTitle(Crud::PAGE_NEW, 'Person anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Person bearbeiten')
            ->setHelp(Crud::PAGE_INDEX, 'Alle, die im Haushalt mitarbeiten oder mitverwalten. Externe Dienste wie Physio oder Caritas sind keine Personen.')
            ->setHelp(Crud::PAGE_NEW, 'Danach wählst du, wie sich die Person anmeldet – oder ob sie nur am Team-Tablet arbeitet.')
            ->setDefaultSort(['displayName' => 'ASC'])
            ->setSearchFields(['displayName', 'username'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        $access = Action::new('access', 'Zugang', 'fa fa-key')
            ->linkToRoute('admin_person_access', static fn (AppUser $person): array => ['entityId' => $person->getId()]);

        return $actions
            ->disable(Action::DELETE, Action::BATCH_DELETE, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $access)
            ->add(Crud::PAGE_EDIT, $access)
            ->setPermission(Action::EDIT, Permission::PERSON_EDIT)
            ->setPermission('access', Permission::PERSON_ACCESS)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Person anlegen'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action): Action => $action->setLabel('Bearbeiten'))
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('Anlegen'))
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('Speichern'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        $roles = [];
        foreach (UserRole::cases() as $role) {
            $roles[$role->label()] = $role->value;
        }

        return $filters
            ->add(ChoiceFilter::new('role', 'Rolle')->setChoices($roles))
            ->add(NullFilter::new('activeUntil', 'Ausgetreten')->setChoiceLabels('nein', 'ja'))
            ->add(NullFilter::new('disabledAt', 'Gesperrt')->setChoiceLabels('nein', 'ja'));
    }

    /**
     * EasyAdmin prints formatted values as HTML (|raw), so every formatter escapes its text.
     */
    public function configureFields(string $pageName): iterable
    {
        $person = $this->getContext()?->getEntity()->getInstance();
        // The manager's role and admin right never change; nobody changes their own (no lock-out by mistake)
        $user = $this->getUser();
        $rightsFixed = $person instanceof AppUser && ($person->isManager() || ($user instanceof AppUser && null !== $person->getId() && $user->getId() === $person->getId()));

        yield FormField::addFieldset('Person');
        yield TextField::new('displayName', 'Name')
            ->setHelp('Vorname oder Spitzname – so sieht ihn das ganze Team. Kein Nachname nötig.');
        yield TextField::new('username', 'Benutzername')
            ->setHelp('Zum Anmelden am Handy oder PC, z. B. „anna". Kleinbuchstaben, Ziffern, Punkt oder Bindestrich.');
        yield $this->textColumn('roleLabel', 'Rolle', static fn (AppUser $person): string => $person->getRole()->label());
        yield Field::new('role', 'Rolle')
            ->onlyOnForms()
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => UserRole::class,
                // A manager only comes from app:setup – never a choice here
                'choices' => $rightsFixed && $person->isManager() ? [UserRole::Manager] : [UserRole::Assistenz, UserRole::Angehoerige],
                'choice_label' => static fn (UserRole $role): string => $role->label(),
            ])
            ->setDisabled($rightsFixed)
            ->setHelp($rightsFixed ? 'Die eigene Rolle und die des Managers lassen sich nicht ändern.' : 'Angehörige: Familie und Vertrauenspersonen. Assistenz: Assistenzkräfte.');
        yield BooleanField::new('admin', 'Admin-Recht')
            ->renderAsSwitch(false)
            ->setDisabled($rightsFixed)
            ->setHelp('Darf die Verwaltung öffnen: Personen, Einstellungen, Protokoll. Assistenzkräfte nie.');
        yield ColorField::new('color', 'Farbe')
            ->onlyOnForms()
            ->setHelp('Farbe der Namenskachel am Tablet.');
        yield DateField::new('activeFrom', 'Eintritt')
            ->onlyOnForms()
            ->setFormTypeOption('input', 'datetime_immutable');
        yield $this->textColumn('access', 'Zugang', $this->accessOverview->summary(...));
        yield $this->textColumn('status', 'Status', $this->accessOverview->status(...));
    }

    /**
     * The entry date defaults to today as a plain date (DATE columns and the date field carry no time zone).
     */
    public function createEntity(string $entityFqcn): AppUser
    {
        return new AppUser(UserRole::Assistenz, '', '', new \DateTimeImmutable($this->household->today()->format('Y-m-d')));
    }

    /**
     * @param AppUser $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $admin = $this->getUser();
        // The log entry needs the new id, so two flushes – in one transaction: both or neither
        $entityManager->wrapInTransaction(function () use ($entityManager, $entityInstance, $admin): void {
            parent::persistEntity($entityManager, $entityInstance);
            $this->auditLogger->log(AuditAction::PersonAnlegen, $admin instanceof AppUser ? $admin : null, $entityInstance, [
                'rolle' => $entityInstance->getRole()->value,
                'admin' => $entityInstance->isAdmin(),
            ]);
        });
    }

    /**
     * A new person goes on to the access page; changes go back to the list.
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $person = $context->getEntity()->getInstance();
        if (Action::NEW === $action && $person instanceof AppUser) {
            // EasyAdmin prints flash messages as HTML (|raw)
            $this->addFlash('success', \sprintf('%s ist angelegt. Wähle jetzt den Zugang.', htmlspecialchars($person->getDisplayName(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')));

            return $this->redirectToRoute('admin_person_access', ['entityId' => $person->getId()]);
        }

        $this->addFlash('success', 'Gespeichert.');

        return $this->redirectToRoute('admin_person_index');
    }

    /**
     * Verwaltung → Personen → Zugang: how the person logs in, with the actions of PersonAccessController.
     */
    #[AdminRoute(path: '/{entityId}/zugang', name: 'access', options: ['methods' => ['GET'], 'requirements' => ['entityId' => '\d+']])]
    public function access(
        #[MapEntity(id: 'entityId')] AppUser $person,
        WebauthnCredentialRepository $credentialRepository,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::PERSON_ACCESS, $person);

        return $this->render('admin/person/access.html.twig', [
            'person' => $person,
            'status' => $this->accessOverview->status($person),
            'invitation' => $this->accessOverview->openInvitation($person),
            'passkeys' => \count($credentialRepository->findForUser($person)),
            'today' => $this->household->today(),
        ]);
    }

    /**
     * @param \Closure(AppUser): string $text
     */
    private function textColumn(string $name, string $label, \Closure $text): TextField
    {
        return TextField::new($name, $label)
            ->onlyOnIndex()
            ->setVirtual(true)
            ->setTemplatePath(self::TEXT_TEMPLATE)
            ->setSortable(false)
            ->formatValue(static fn (mixed $value, AppUser $person): string => htmlspecialchars($text($person), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }
}
