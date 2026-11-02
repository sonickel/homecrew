<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\GuidePublishMode;
use App\Enum\HolidayRegion;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimezoneField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Verwaltung → Einstellungen → Allgemein (konzept-11 §2).
 */
#[AdminRoute(path: '/einstellungen/allgemein', name: 'settings_general')]
#[IsGranted(Permission::ADMIN)]
final class GeneralSettingsCrudController extends AbstractSettingsCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Einstellungen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Einstellungen: Allgemein');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Haushalt');
        yield TextField::new('householdName', 'Name des Haushalts')
            ->setHelp('Steht oben in der App und im Titel des Browsers.');
        yield TimezoneField::new('timezone', 'Zeitzone');
        yield Field::new('holidayRegion', 'Feiertage')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions(['class' => HolidayRegion::class, 'choice_label' => static fn (HolidayRegion $region): string => $region->label()])
            ->setHelp('Bestimmt, welche Feiertage im Kalender stehen.');

        yield FormField::addFieldset('Tablet');
        yield Field::new('tabletLockMinutes', 'Persönliche Anmeldung am Tablet endet nach')
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions(['choices' => ['1 Minute' => 1, '2 Minuten' => 2, '3 Minuten' => 3, '5 Minuten' => 5, '10 Minuten' => 10, '15 Minuten' => 15]])
            ->setHelp('Ohne Bedienung meldet das Tablet die Person ab und zeigt wieder die Team-Ansicht.');
        yield BooleanField::new('pinLanOnly', 'PIN-Anmeldung nur im Heimnetz')
            ->renderAsSwitch(false)
            ->setHelp('Empfohlen: Mit PIN kann man sich nur im eigenen WLAN anmelden.');

        yield FormField::addFieldset('Arbeitszeiten');
        yield IntegerField::new('timeViewMonths', 'Eigene Zeiten zurück (Monate)')
            ->setHelp('So weit sehen Assistenzkräfte ihre eigenen Arbeitszeiten (1 bis 24 Monate).');
        yield IntegerField::new('maxShiftHours', 'Hinweis „Dienst noch offen?" nach (Stunden)')
            ->setHelp('Ist ein Dienst länger offen, bekommen die Admins einen Hinweis.');

        yield FormField::addFieldset('Wissen');
        yield IntegerField::new('videoMaxSeconds', 'Längste Videos (Sekunden)')
            ->setHelp('Kurze Videos in Anleitungen, höchstens 90 Sekunden.');
        yield Field::new('guidePublishMode', 'Neue Anleitungen')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions(['class' => GuidePublishMode::class, 'choice_label' => static fn (GuidePublishMode $mode): string => match ($mode) {
                GuidePublishMode::Freigabe => 'Ein Admin gibt sie frei',
                GuidePublishMode::Direkt => 'Sofort für alle sichtbar',
            }]);
    }
}
