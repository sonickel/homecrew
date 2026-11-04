<?php

declare(strict_types=1);

namespace App\Form\Report;

use App\Entity\IssueReport;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Service\Task\Assignment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The report dialog (konzept-13 §2): what is it – optional note, "dringend", "nur für Admins" – and what should happen.
 * Mode "melden" and "nicht_moeglich" ask for all of it, "weitergeben" (report log) only for the follow-up.
 * The fields of a follow-up show only while it is chosen (reveal_controller.js, "data-reveal-for").
 *
 * @extends AbstractType<ReportData>
 */
final class ReportType extends AbstractType
{
    public const string MODE_REPORT = 'melden';
    public const string MODE_UNABLE = 'nicht_moeglich';
    public const string MODE_FORWARD = 'weitergeben';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $for = static fn (string $followUp): array => ['data-reveal-target' => 'field', 'data-reveal-for' => $followUp];

        if (self::MODE_FORWARD !== $options['mode']) {
            $builder
                ->add('title', TextType::class, [
                    'label' => self::MODE_UNABLE === $options['mode'] ? 'Was ist los?' : 'Was genau?',
                    'help' => self::MODE_UNABLE === $options['mode'] ? 'Kurz, z. B. „Seife leer“. Bitte keine Gesundheitsangaben.' : 'Kurz, z. B. „Glühbirne Bad“. Bitte keine Gesundheitsangaben.',
                    'attr' => [
                        'maxlength' => IssueReport::TITLE_MAX,
                        'autocomplete' => 'off',
                        'list' => 'meldung-vorschlaege',
                        'data-action' => 'input->similar-reports#search',
                    ],
                ])
                ->add('note', TextareaType::class, [
                    'label' => 'Notiz',
                    'required' => false,
                    'attr' => ['rows' => 2, 'maxlength' => IssueReport::NOTE_MAX],
                ])
                ->add('urgent', CheckboxType::class, [
                    'label' => 'Dringend',
                    'required' => false,
                    'help' => 'Im Notfall bitte anrufen oder 112 wählen.',
                ])
                ->add('adminsOnly', CheckboxType::class, [
                    'label' => 'Nur für Admins sichtbar',
                    'required' => false,
                ]);
        }

        $followUps = ['Auf eine Liste' => ReportData::FOLLOW_LIST, 'Aufgabe für …' => ReportData::FOLLOW_TASK];
        if (self::MODE_FORWARD !== $options['mode']) {
            $followUps = ['Nur melden' => ReportData::FOLLOW_NONE, ...$followUps];
        }

        $builder
            ->add('followUp', ChoiceType::class, [
                'label' => 'Was soll passieren?',
                'expanded' => true,
                'row_attr' => ['class' => 'hc-option--inline'],
                'choices' => $followUps,
            ])
            ->add('list', EntityType::class, [
                'class' => ShoppingList::class,
                'choices' => $options['lists'],
                'choice_label' => static fn (ShoppingList $list): string => $list->getName(),
                'label' => 'Auf welche Liste?',
                'placeholder' => 'Bitte wählen',
                'required' => false,
                'row_attr' => $for(ReportData::FOLLOW_LIST),
            ])
            ->add('itemName', TextType::class, [
                'label' => 'Was soll auf die Liste?',
                'help' => self::MODE_FORWARD === $options['mode'] ? 'Leer lassen: der Titel der Meldung. Den Eintrag sehen alle.' : 'Leer lassen: wie oben. Den Eintrag sehen alle.',
                'required' => false,
                'attr' => ['maxlength' => ShoppingItem::NAME_MAX, 'autocomplete' => 'off'],
                'row_attr' => $for(ReportData::FOLLOW_LIST),
            ])
            ->add('assignee', ChoiceType::class, [
                'label' => 'Für wen?',
                'choices' => $options['assignees'],
                'choice_value' => static fn (?Assignment $assignment): ?string => $assignment?->key(),
                'choice_label' => static fn (Assignment $assignment): string => $assignment->label(),
                'placeholder' => 'Bitte wählen',
                'required' => false,
                'row_attr' => $for(ReportData::FOLLOW_TASK),
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Bis wann?',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'row_attr' => $for(ReportData::FOLLOW_TASK),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReportData::class,
            'mode' => self::MODE_REPORT,
        ]);
        $resolver->setRequired(['lists', 'assignees']);
        $resolver->setAllowedTypes('lists', 'array');
        $resolver->setAllowedTypes('assignees', 'array');
        $resolver->setAllowedValues('mode', [self::MODE_REPORT, self::MODE_UNABLE, self::MODE_FORWARD]);
    }
}
