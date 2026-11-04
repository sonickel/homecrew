<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ShoppingFavorite;
use App\Entity\ShoppingList;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → Lieblingsartikel (konzept-2 §9): items everybody puts on a list with one tap – for one list or all;
 * the link to the product goes over to the entry. Suggestions for "Melden" as well.
 *
 * @extends AbstractCrudController<ShoppingFavorite>
 */
#[AdminRoute(path: '/lieblingsartikel', name: 'shopping_favorite')]
#[IsGranted(Permission::ADMIN)]
final class ShoppingFavoriteCrudController extends AbstractCrudController
{
    /** Virtual columns need an explicit template, otherwise EasyAdmin shows them as "Nicht zugreifbar". */
    private const string TEXT_TEMPLATE = '@EasyAdmin/crud/field/text.html.twig';

    public static function getEntityFqcn(): string
    {
        return ShoppingFavorite::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Lieblingsartikel')
            ->setEntityLabelInPlural('Lieblingsartikel')
            ->setPageTitle(Crud::PAGE_INDEX, 'Lieblingsartikel')
            ->setPageTitle(Crud::PAGE_NEW, 'Lieblingsartikel anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Lieblingsartikel bearbeiten')
            ->setHelp(Crud::PAGE_INDEX, 'Stehen auf der Liste als Knöpfe – ein Tipp setzt sie darauf. Beim Melden erscheinen sie als Vorschläge.')
            ->setDefaultSort(['sort' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DETAIL, Action::BATCH_DELETE)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Lieblingsartikel anlegen'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action): Action => $action->setLabel('Bearbeiten'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action): Action => $action->setLabel('Löschen'))
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
        yield TextField::new('name', 'Name');
        yield $this->textColumn('listText', 'Liste', static fn (ShoppingFavorite $favorite): string => $favorite->getList()?->getName() ?? 'alle Listen');
        yield AssociationField::new('list', 'Liste')
            ->onlyOnForms()
            ->setRequired(false)
            ->setFormTypeOptions([
                'choice_label' => static fn (ShoppingList $list): string => $list->getName(),
                'placeholder' => 'Für alle Listen',
            ]);
        yield TextField::new('defaultQuantity', 'Menge')
            ->onlyOnForms()
            ->setRequired(false)
            ->setHelp('z. B. „2 Flaschen“.');
        yield $this->textColumn('linkText', 'Link', static fn (ShoppingFavorite $favorite): string => null === $favorite->getLink() ? '–' : 'ja');
        yield TextField::new('link', 'Link')
            ->onlyOnForms()
            ->setRequired(false)
            ->setHelp('z. B. zum Produkt im Shop, beginnt mit https:// – öffnet eine fremde Seite.');
        yield IntegerField::new('sort', 'Reihenfolge')
            ->onlyOnForms()
            ->setHelp('Kleinere Zahlen stehen weiter vorn.');
    }

    /**
     * @param \Closure(ShoppingFavorite): string $text
     */
    private function textColumn(string $name, string $label, \Closure $text): TextField
    {
        return TextField::new($name, $label)
            ->onlyOnIndex()
            ->setVirtual(true)
            ->setTemplatePath(self::TEXT_TEMPLATE)
            ->setSortable(false)
            ->formatValue(static fn (mixed $value, ShoppingFavorite $favorite): string => htmlspecialchars($text($favorite), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }
}
