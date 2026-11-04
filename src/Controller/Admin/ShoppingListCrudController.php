<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AppUser;
use App\Entity\ShoppingList;
use App\Enum\AssigneeRole;
use App\Security\Permission;
use App\Service\Report\ReportClosing;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → Listen (konzept-11 §2): name and who takes care of the list – a person or a role, who are told about
 * new entries. Lists are not deleted (plans and reports point at them), only switched off.
 *
 * @extends AbstractCrudController<ShoppingList>
 */
#[AdminRoute(path: '/listen', name: 'shopping_list')]
#[IsGranted(Permission::ADMIN)]
final class ShoppingListCrudController extends AbstractCrudController
{
    /** Virtual columns need an explicit template, otherwise EasyAdmin shows them as "Nicht zugreifbar". */
    private const string TEXT_TEMPLATE = '@EasyAdmin/crud/field/text.html.twig';

    public static function getEntityFqcn(): string
    {
        return ShoppingList::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Liste')
            ->setEntityLabelInPlural('Listen')
            ->setPageTitle(Crud::PAGE_INDEX, 'Listen')
            ->setPageTitle(Crud::PAGE_NEW, 'Liste anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Liste bearbeiten')
            ->setHelp(Crud::PAGE_INDEX, 'Alle nutzen die aktiven Listen, auch am Team-Tablet. Wer zuständig ist, bekommt neue Einträge anderer als Benachrichtigung.')
            ->setDefaultSort(['isActive' => 'DESC', 'sort' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name'])
            ->setPaginatorPageSize(50);
    }

    public function __construct(private readonly ReportClosing $reportClosing)
    {
    }

    /**
     * Switched off: reports waiting for an entry on this list are open again for the admins.
     */
    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance->isActive()) {
            $this->reportClosing->listSwitchedOff($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DETAIL, Action::DELETE, Action::BATCH_DELETE)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Liste anlegen'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action): Action => $action->setLabel('Bearbeiten'))
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('Anlegen'))
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('Speichern'));
    }

    /**
     * EasyAdmin prints formatted values as HTML (|raw), so every formatter escapes its text.
     */
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Name')
            ->setHelp('z. B. „Einkauf“ oder „Besorgt die Familie“.');
        yield $this->textColumn('assigneeText', 'Zuständig', static fn (ShoppingList $list): string => $list->getAssigneeUser()?->getDisplayName() ?? $list->getAssigneeRole()?->label() ?? '–');
        yield AssociationField::new('assigneeUser', 'Zuständig: eine Person')
            ->onlyOnForms()
            ->setRequired(false)
            ->setFormTypeOptions([
                'choice_label' => static fn (AppUser $person): string => $person->getDisplayName(),
                'placeholder' => 'Keine bestimmte Person',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('u')
                    ->andWhere('u.disabledAt IS NULL')
                    ->andWhere('u.activeUntil IS NULL')
                    ->orderBy('u.displayName'),
            ]);
        yield Field::new('assigneeRole', 'Zuständig: oder eine Rolle')
            ->onlyOnForms()
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => AssigneeRole::class,
                'required' => false,
                'placeholder' => 'Keine Rolle',
                'choice_label' => static fn (AssigneeRole $role): string => $role->label(),
            ])
            ->setHelp('Person oder Rolle – nicht beides. Ohne Zuständigkeit bekommt niemand eine Benachrichtigung.');
        yield $this->textColumn('activeText', 'Aktiv', static fn (ShoppingList $list): string => $list->isActive() ? 'ja' : 'nein (ausgeblendet)');
        yield BooleanField::new('active', 'Aktiv')
            ->onlyOnForms()
            ->renderAsSwitch(false)
            ->setHelp('Ausgeschaltet verschwindet die Liste für alle; Einträge, Wartungspläne und Meldungen bleiben. Meldungen, die auf einen Eintrag dieser Liste warten, sind danach wieder offen.');
        yield IntegerField::new('sort', 'Reihenfolge')
            ->onlyOnForms()
            ->setHelp('Kleinere Zahlen stehen weiter oben.');
    }

    /**
     * @param \Closure(ShoppingList): string $text
     */
    private function textColumn(string $name, string $label, \Closure $text): TextField
    {
        return TextField::new($name, $label)
            ->onlyOnIndex()
            ->setVirtual(true)
            ->setTemplatePath(self::TEXT_TEMPLATE)
            ->setSortable(false)
            ->formatValue(static fn (mixed $value, ShoppingList $list): string => htmlspecialchars($text($list), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }
}
