<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AppSetting;
use App\Security\Permission;
use App\Service\Household\HouseholdContext;
use App\Service\Tablet\TabletContext;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Theme;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Verwaltung" (konzept-11): only the masks that exist so far – no empty menu entries.
 * Access: access_control (^/verwaltung) and this attribute, both decided by App\Security\Voter\AdminVoter.
 */
#[AdminDashboard(routePath: '/verwaltung', routeName: 'admin')]
#[IsGranted(Permission::ADMIN)]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly HouseholdContext $household,
        private readonly TabletContext $tabletContext,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            // EasyAdmin prints the title as HTML
            ->setTitle(htmlspecialchars($this->household->name()).' – Verwaltung')
            ->setLocales(['de'])
            ->disableDarkMode()
            ->setTheme(Theme::new()->primaryColor('#1e4d8c')->radius('6px'));
    }

    public function configureAssets(): Assets
    {
        $assets = Assets::new()
            ->addCssFile('styles/fonts.css')
            ->addCssFile('styles/tokens.css')
            ->addCssFile('styles/admin.css');

        // At a paired tablet the Verwaltung ends after 5 minutes without touch (konzept-12 §5) – the page goes back too
        $tabletSeconds = $this->tabletContext->personIdleSeconds();
        if (null !== $tabletSeconds) {
            $assets->addJsFile(Asset::new('admin/tablet_session.js')->defer()->htmlAttr('data-hc-tablet-logout', (string) $tabletSeconds));
        }

        return $assets;
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setTimezone($this->household->timezone()->getName())
            ->setDateTimeFormat('dd.MM.yyyy, HH:mm');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Zurück zu „Heute“', 'fa fa-arrow-left', 'app_home');
        yield MenuItem::linkToDashboard('Übersicht', 'fa fa-home');

        yield MenuItem::section('Personen');
        yield MenuItem::linkTo(PersonCrudController::class, 'Personen', 'fa fa-users');
        yield MenuItem::linkTo(DeviceCrudController::class, 'Geräte', 'fa fa-tablet-screen-button');

        yield MenuItem::section('Einstellungen');
        yield MenuItem::linkTo(GeneralSettingsCrudController::class, 'Allgemein', 'fa fa-sliders')
            ->setAction(Action::EDIT)->setEntityId(AppSetting::ID);
        yield MenuItem::linkTo(RetentionSettingsCrudController::class, 'Löschfristen', 'fa fa-calendar-xmark')
            ->setAction(Action::EDIT)->setEntityId(AppSetting::ID);

        yield MenuItem::section('Wissen');
        yield MenuItem::linkTo(EmergencyContactCrudController::class, 'Notfall-Infos', 'fa fa-kit-medical');

        yield MenuItem::section('System');
        yield MenuItem::linkTo(AuditLogCrudController::class, 'Protokoll', 'fa fa-list');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // EasyAdmin pages have no HomeCrew top bar: logging out works via "Zurück zu „Heute“" (top bar there).
        // EasyAdmin's own logout link would be a GET request; HomeCrew logs out by POST with CSRF token only.
        return parent::configureUserMenu($user)->displayUserAvatar(false)->setMenuItems([]);
    }
}
