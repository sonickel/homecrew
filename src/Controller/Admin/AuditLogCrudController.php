<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Security\Permission;
use App\Service\Audit\AuditLogPresenter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → System → Protokoll: read only, newest first.
 *
 * @extends AbstractCrudController<AuditLog>
 */
#[AdminRoute(path: '/protokoll', name: 'audit_log')]
#[IsGranted(Permission::ADMIN)]
final class AuditLogCrudController extends AbstractCrudController
{
    /** Virtual columns need an explicit template, otherwise EasyAdmin shows them as "Nicht zugreifbar". */
    private const string TEXT_TEMPLATE = '@EasyAdmin/crud/field/text.html.twig';

    public function __construct(
        private readonly AuditLogPresenter $presenter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AuditLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Eintrag')
            ->setEntityLabelInPlural('Einträge')
            ->setPageTitle(Crud::PAGE_INDEX, 'Protokoll')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Protokolleintrag')
            ->setHelp(Crud::PAGE_INDEX, 'Anmeldungen und Änderungen an Einstellungen. Ohne IP-Adressen, Passwörter oder Codes.')
            ->setDefaultSort(['createdAt' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(null)
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $actions = [];
        foreach (AuditAction::cases() as $action) {
            $actions[$action->label()] = $action->value;
        }

        return $filters
            ->add(DateTimeFilter::new('createdAt', 'Zeit'))
            ->add(EntityFilter::new('actor', 'Person')->setFormTypeOption('value_type_options.choice_label', 'displayName'))
            ->add(ChoiceFilter::new('action', 'Was')->setChoices($actions));
    }

    /**
     * EasyAdmin prints formatted values as HTML (|raw), so every formatter escapes its text.
     */
    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Zeit');
        yield $this->textColumn('who', 'Wer', $this->presenter->who(...));
        yield TextField::new('action', 'Was')
            ->formatValue(fn (mixed $value, AuditLog $entry): string => self::escape($this->presenter->action($entry)));
        yield $this->textColumn('subject', 'Betrifft', $this->presenter->subject(...));
        yield $this->textColumn('summary', 'Einzelheiten', $this->presenter->details(...));
    }

    /**
     * @param \Closure(AuditLog): string $text
     */
    private function textColumn(string $name, string $label, \Closure $text): TextField
    {
        return TextField::new($name, $label)
            ->setVirtual(true)
            ->setTemplatePath(self::TEXT_TEMPLATE)
            ->setSortable(false)
            ->formatValue(static fn (mixed $value, AuditLog $entry): string => self::escape($text($entry)));
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
