<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EmergencyContact;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → Notfall-Infos (konzept-3 §2, konzept-11 §2): contacts and important numbers by category, with
 * "wann anrufen", visibility (everybody incl. team tablet, or admins only) and the lock screen switch.
 * The link to a place follows with the places pages (M12). Emergency plans are guides (M12).
 *
 * @extends AbstractCrudController<EmergencyContact>
 */
#[AdminRoute(path: '/notfall', name: 'emergency_contact')]
#[IsGranted(Permission::ADMIN)]
final class EmergencyContactCrudController extends AbstractCrudController
{
    /** Virtual columns need an explicit template, otherwise EasyAdmin shows them as "Nicht zugreifbar". */
    private const string TEXT_TEMPLATE = '@EasyAdmin/crud/field/text.html.twig';

    public static function getEntityFqcn(): string
    {
        return EmergencyContact::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Notfallkontakt')
            ->setEntityLabelInPlural('Notfallkontakte')
            ->setPageTitle(Crud::PAGE_INDEX, 'Notfall-Infos')
            ->setPageTitle(Crud::PAGE_NEW, 'Notfallkontakt anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Notfallkontakt bearbeiten')
            ->setHelp(Crud::PAGE_INDEX, 'Erscheinen unter „Notfall“ – der Knopf ist auf jeder Seite, am Tablet auch ohne Anmeldung. Keine Gesundheitsangaben eintragen.')
            ->setDefaultSort(['category' => 'ASC', 'sort' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'relation'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DETAIL, Action::BATCH_DELETE)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Kontakt anlegen'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action): Action => $action->setLabel('Bearbeiten'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action): Action => $action->setLabel('Löschen'))
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('Anlegen'))
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('Speichern'));
    }

    /**
     * EasyAdmin prints formatted values as HTML (|raw), so every formatter escapes its text.
     * Generic fields lose formatValue() on the index (EasyAdmin guesses a type), so the list has text columns.
     */
    public function configureFields(string $pageName): iterable
    {
        yield $this->textColumn('categoryLabel', 'Kategorie', static fn (EmergencyContact $contact): string => $contact->getCategory()->label());
        yield Field::new('category', 'Kategorie')
            ->onlyOnForms()
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => EmergencyCategory::class,
                'choice_label' => static fn (EmergencyCategory $category): string => $category->label(),
            ]);
        yield TextField::new('name', 'Name')
            ->setHelp('So, wie das Team die Person oder Stelle kennt, z. B. „Schwester“ oder „Hausarztpraxis“.');
        // Optional texts only in the form: the list would show EasyAdmin's "Null" badge for empty ones
        yield TextField::new('relation', 'Beziehung oder Art')
            ->onlyOnForms()
            ->setRequired(false)
            ->setHelp('z. B. „Familie“, „Sanitätshaus-Notdienst“.');
        yield TextField::new('phone', 'Telefon');
        yield TextField::new('phoneAlt', 'Zweite Nummer')
            ->onlyOnForms()
            ->setRequired(false);
        yield $this->textColumn('whenToCallText', 'Wann anrufen', static fn (EmergencyContact $contact): string => $contact->getWhenToCall() ?? '');
        yield TextField::new('whenToCall', 'Wann anrufen')
            ->onlyOnForms()
            ->setRequired(false)
            ->setHelp('z. B. „immer zuerst“, „werktags 8–18 Uhr“.');
        yield $this->textColumn('visibilityLabel', 'Wer sieht ihn', static fn (EmergencyContact $contact): string => $contact->isAdminOnly() ? 'Nur Admins' : 'Alle');
        yield Field::new('visibility', 'Wer sieht ihn')
            ->onlyOnForms()
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => EmergencyVisibility::class,
                'expanded' => true,
                'choice_label' => static fn (EmergencyVisibility $visibility): string => $visibility->label(),
            ]);
        yield $this->textColumn('lockScreenText', 'Sperrbildschirm', static fn (EmergencyContact $contact): string => $contact->isShowOnLockscreen() && !$contact->isAdminOnly() ? 'ja' : 'nein');
        yield BooleanField::new('showOnLockscreen', 'Auf dem Sperrbildschirm zeigen')
            ->onlyOnForms()
            ->renderAsSwitch(false)
            ->setHelp('Der Bildschirmschoner des Team-Tablets zeigt diesen Kontakt, ohne dass sich jemand anmeldet. Gilt nur für „Alle“.');
        yield IntegerField::new('sort', 'Reihenfolge')
            ->onlyOnForms()
            ->setHelp('Kleinere Zahlen stehen innerhalb der Kategorie weiter oben.');
    }

    /**
     * @param \Closure(EmergencyContact): string $text
     */
    private function textColumn(string $name, string $label, \Closure $text): TextField
    {
        return TextField::new($name, $label)
            ->onlyOnIndex()
            ->setVirtual(true)
            ->setTemplatePath(self::TEXT_TEMPLATE)
            ->setSortable(false)
            ->formatValue(static fn (mixed $value, EmergencyContact $contact): string => htmlspecialchars($text($contact), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }
}
