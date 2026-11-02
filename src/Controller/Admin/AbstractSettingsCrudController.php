<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AppSetting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The settings are one row (id 1) shown as several masks: only "edit" exists – no list, no new, no delete.
 * Changes are logged by App\EventListener\SettingsAuditListener.
 *
 * @extends AbstractCrudController<AppSetting>
 */
abstract class AbstractSettingsCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AppSetting::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::INDEX, Action::NEW, Action::DELETE, Action::DETAIL, Action::BATCH_DELETE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE, static fn (Action $action): Action => $action->setLabel('Speichern')->setIcon(null));
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $this->addFlash('success', 'Die Einstellungen sind gespeichert.');

        return $this->redirect($this->adminUrlGenerator->setController(static::class)->setAction(Action::EDIT)->setEntityId(AppSetting::ID)->generateUrl());
    }
}
