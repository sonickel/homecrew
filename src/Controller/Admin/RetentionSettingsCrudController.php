<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → Einstellungen → Löschfristen: only the periods of stage 1 (decision 01.11.);
 * chat, AI and HR data appear together with their features.
 */
#[AdminRoute(path: '/einstellungen/loeschfristen', name: 'settings_retention')]
#[IsGranted(Permission::ADMIN)]
final class RetentionSettingsCrudController extends AbstractSettingsCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Löschfristen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Einstellungen: Löschfristen')
            ->setHelp(Crud::PAGE_EDIT, 'Nach diesen Fristen löscht HomeCrew alte Daten jede Nacht von selbst.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('retentionHandoverDays', 'Übergaben löschen nach (Tagen)');
        yield IntegerField::new('retentionCompletionDays', 'Bei Erledigungen den Namen entfernen nach (Tagen)')
            ->setHelp('Danach steht bei einer erledigten Aufgabe nur noch das Datum.');
        yield IntegerField::new('retentionDoneTaskDays', 'Erledigte Aufgaben löschen nach (Tagen)');
        yield IntegerField::new('completionKeepLast', 'Je Wartungsplan immer behalten (letzte Erledigungen)');
        yield IntegerField::new('retentionReportDays', 'Abgeschlossene Meldungen löschen nach (Tagen)');
        yield IntegerField::new('retentionNotificationDays', 'Benachrichtigungen löschen nach (Tagen)');
        yield IntegerField::new('retentionAuditDays', 'Protokoll löschen nach (Tagen)')
            ->setHelp('Mindestens 30 Tage; empfohlen sind 365 Tage.');
    }
}
