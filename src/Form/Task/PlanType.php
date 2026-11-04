<?php

declare(strict_types=1);

namespace App\Form\Task;

use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Enum\TaskCategory;
use App\Enum\Visibility;
use App\Service\Series\Rhythm;
use App\Service\Task\Assignment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * "Neuer Plan" and "Bearbeiten" (konzept-2 §5, konzept-11 §2): what, how often, when, who, the checklist and –
 * when changing – a pause. The fields of a rhythm show only while it is chosen (reveal_controller.js,
 * "data-reveal-for"); without JavaScript all of them stay visible.
 *
 * @extends AbstractType<PlanData>
 */
final class PlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Assignment> $assignees */
        $assignees = $options['assignees'];
        $visibilities = array_filter(
            Visibility::cases(),
            static fn (Visibility $visibility): bool => Visibility::Privat !== $visibility || true === $options['allow_private'],
        );
        $for = static fn (string ...$rhythms): array => ['data-reveal-target' => 'field', 'data-reveal-for' => implode(' ', $rhythms)];
        $calendar = [Rhythm::DAILY, Rhythm::WEEKLY, Rhythm::MONTHLY, Rhythm::YEARLY];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Was ist zu tun?',
                'attr' => ['maxlength' => 150, 'autocomplete' => 'off'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'help' => 'Wenn nötig: wie, womit, wo. Bitte keine Gesundheitsangaben.',
                'attr' => ['rows' => 2],
            ])
            ->add('rhythm', ChoiceType::class, [
                'label' => 'Rhythmus',
                'expanded' => true,
                'row_attr' => ['class' => 'hc-option--inline'],
                'choices' => [
                    'Täglich' => Rhythm::DAILY,
                    'Wöchentlich' => Rhythm::WEEKLY,
                    'Monatlich' => Rhythm::MONTHLY,
                    'Jährlich' => Rhythm::YEARLY,
                    'Nach Erledigung' => PlanData::AFTER_COMPLETION,
                ],
            ])
            ->add('everyDays', IntegerType::class, [
                'label' => 'Alle … Tage',
                'help' => '1 = jeden Tag',
                'row_attr' => $for(Rhythm::DAILY),
                'attr' => ['min' => 1, 'max' => 365, 'inputmode' => 'numeric'],
            ])
            ->add('weekdays', ChoiceType::class, [
                'label' => 'An diesen Tagen',
                'expanded' => true,
                'multiple' => true,
                'row_attr' => ['class' => 'hc-option--inline', ...$for(Rhythm::WEEKLY)],
                'choices' => ['Mo' => 'MO', 'Di' => 'TU', 'Mi' => 'WE', 'Do' => 'TH', 'Fr' => 'FR', 'Sa' => 'SA', 'So' => 'SU'],
            ])
            ->add('everyWeeks', IntegerType::class, [
                'label' => 'Alle … Wochen',
                'help' => '1 = jede Woche',
                'row_attr' => $for(Rhythm::WEEKLY),
                'attr' => ['min' => 1, 'max' => 52, 'inputmode' => 'numeric'],
            ])
            ->add('monthDay', IntegerType::class, [
                'label' => 'Am Tag',
                'help' => 'In kürzeren Monaten am letzten Tag.',
                'row_attr' => $for(Rhythm::MONTHLY),
                'attr' => ['min' => 1, 'max' => 31, 'inputmode' => 'numeric'],
            ])
            ->add('everyMonths', IntegerType::class, [
                'label' => 'Alle … Monate',
                'help' => '1 = jeden Monat',
                'row_attr' => $for(Rhythm::MONTHLY),
                'attr' => ['min' => 1, 'max' => 12, 'inputmode' => 'numeric'],
            ])
            ->add('afterDays', IntegerType::class, [
                'label' => 'Tage nach der letzten Erledigung',
                'required' => false,
                'row_attr' => $for(PlanData::AFTER_COMPLETION),
                'attr' => ['min' => 1, 'max' => 365, 'inputmode' => 'numeric'],
            ])
            ->add('earliestAfterDays', IntegerType::class, [
                'label' => 'Schon möglich nach … Tagen',
                'help' => 'Leer: erst am Fälligkeitstag.',
                'required' => false,
                'row_attr' => $for(PlanData::AFTER_COMPLETION),
                'attr' => ['min' => 0, 'max' => 365, 'inputmode' => 'numeric'],
            ])
            ->add('startsOn', DateType::class, [
                'label' => 'Beginn',
                'help' => 'Jährlich: jedes Jahr an diesem Tag.',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueTime', TimeType::class, [
                'label' => 'Uhrzeit',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endsOn', DateType::class, [
                'label' => 'Endet am',
                'help' => 'Leer: läuft weiter.',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('windowDays', IntegerType::class, [
                'label' => 'Schon … Tage vorher möglich',
                'help' => '0 = nur am Tag selbst',
                'row_attr' => $for(...$calendar),
                'attr' => ['min' => 0, 'max' => 60, 'inputmode' => 'numeric'],
            ])
            ->add('assignee', ChoiceType::class, [
                'label' => 'Zuständig',
                'choices' => $assignees,
                'choice_value' => static fn (?Assignment $assignment): ?string => $assignment?->key(),
                'choice_label' => static fn (Assignment $assignment): string => $assignment->label(),
                'help' => true === $options['allow_private'] ? 'Bei „Nur ich (privat)“ bist immer du zuständig.' : null,
            ])
            ->add('visibility', EnumType::class, [
                'class' => Visibility::class,
                'choices' => $visibilities,
                'label' => 'Wer sieht den Plan?',
                'choice_label' => static fn (Visibility $visibility): string => $visibility->label(),
                'help' => 'Gilt auch für jede Aufgabe daraus.',
            ])
            ->add('checklist', TextareaType::class, [
                'label' => 'Checkliste',
                'required' => false,
                'help' => 'Ein Punkt pro Zeile, z. B. „Reifen vorne links“ – höchstens 20. Bitte keine Gesundheitsangaben.',
                'attr' => ['rows' => 3],
            ])
            ->add('unableList', EntityType::class, [
                'class' => ShoppingList::class,
                'choices' => $options['lists'],
                'choice_label' => static fn (ShoppingList $list): string => $list->getName(),
                'label' => 'Auf welche Liste?',
                'placeholder' => 'Kein Vorschlag',
                'required' => false,
            ])
            ->add('unableItemName', TextType::class, [
                'label' => 'Was soll auf die Liste?',
                'help' => 'Z. B. „Flüssigseife“ – wer „Nicht möglich“ tippt, bestätigt es mit einem Tipp.',
                'required' => false,
                'attr' => ['maxlength' => ShoppingItem::NAME_MAX, 'autocomplete' => 'off'],
            ])
            ->add('category', EnumType::class, [
                'class' => TaskCategory::class,
                'label' => 'Kategorie',
                'choice_label' => static fn (TaskCategory $category): string => $category->label(),
            ])
            ->add('estimatedMinutes', IntegerType::class, [
                'label' => 'Dauer (Minuten, ungefähr)',
                'required' => false,
                'attr' => ['min' => 1, 'max' => 600, 'inputmode' => 'numeric'],
            ])
            ->add('important', CheckboxType::class, [
                'label' => 'Wichtig',
                'required' => false,
            ])
            ->add('requiresManager', CheckboxType::class, [
                'label' => 'Nur gemeinsam mit %name%',
                'label_translation_parameters' => ['%name%' => $options['manager_name']],
                'required' => false,
            ]);

        if (true === $options['with_pause']) {
            $builder
                ->add('pausedFrom', DateType::class, [
                    'label' => 'Pausieren ab',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                ])
                ->add('pausedUntil', DateType::class, [
                    'label' => 'bis',
                    'help' => 'Leer: bis auf Weiteres.',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanData::class,
            'allow_private' => false,
            'with_pause' => false,
            'manager_name' => 'dem Manager',
            'lists' => [],
        ]);
        $resolver->setAllowedTypes('lists', 'array');
        $resolver->setRequired('assignees');
        $resolver->setAllowedTypes('assignees', 'array');
        $resolver->setAllowedTypes('allow_private', 'bool');
        $resolver->setAllowedTypes('with_pause', 'bool');
        $resolver->setAllowedTypes('manager_name', 'string');
    }
}
