<?php

declare(strict_types=1);

namespace App\Form\Task;

use App\Enum\TaskCategory;
use App\Enum\Visibility;
use App\Service\Task\Assignment;
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
 * "Neue Aufgabe" and "Bearbeiten" (konzept-2 §5.1): what, when, details, who is responsible and who sees it.
 * "Privat" is only offered to persons who may keep private content (App\Service\Visibility\VisibilityRules).
 *
 * @extends AbstractType<TaskData>
 */
final class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Assignment> $assignees */
        $assignees = $options['assignees'];
        $visibilities = array_filter(
            Visibility::cases(),
            static fn (Visibility $visibility): bool => Visibility::Privat !== $visibility || true === $options['allow_private'],
        );

        $builder
            ->add('title', TextType::class, [
                'label' => 'Was ist zu tun?',
                'attr' => ['maxlength' => 150, 'autocomplete' => 'off'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'help' => 'Wenn nötig: wie, womit, wo. Bitte keine Gesundheitsangaben.',
                'attr' => ['rows' => 3],
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Fällig am',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueTime', TimeType::class, [
                'label' => 'Uhrzeit',
                'help' => 'Nur, wenn es zu einer bestimmten Zeit sein muss.',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('showFrom', ChoiceType::class, [
                'label' => 'Ab wann zeigen?',
                'expanded' => true,
                'choices' => [
                    'Ab sofort – unter „Wenn Zeit ist“' => TaskData::SHOW_NOW,
                    'Erst am Fälligkeitstag' => TaskData::SHOW_ON_DUE_DATE,
                    'Ab einem bestimmten Tag – dann täglich unter „Heute möglich“' => TaskData::SHOW_FROM,
                ],
            ])
            ->add('showFromDate', DateType::class, [
                'label' => 'Ab diesem Tag',
                'help' => 'Nur bei „Ab einem bestimmten Tag“.',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
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
                'label' => 'Wer sieht die Aufgabe?',
                'expanded' => true,
                'choice_label' => static fn (Visibility $visibility): string => $visibility->label(),
                'help' => 'Personen ohne eigenen Zugang sehen Aufgaben „Nur wer zuständig ist“ auch am Team-Tablet nicht.',
            ])
            ->add('category', EnumType::class, [
                'class' => TaskCategory::class,
                'label' => 'Kategorie',
                'choice_label' => static fn (TaskCategory $category): string => $category->label(),
            ])
            ->add('estimatedMinutes', IntegerType::class, [
                'label' => 'Dauer in Minuten (ungefähr)',
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskData::class,
            'allow_private' => false,
            'manager_name' => 'dem Manager',
        ]);
        $resolver->setRequired('assignees');
        $resolver->setAllowedTypes('assignees', 'array');
        $resolver->setAllowedTypes('allow_private', 'bool');
        $resolver->setAllowedTypes('manager_name', 'string');
    }
}
