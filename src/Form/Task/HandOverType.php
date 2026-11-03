<?php

declare(strict_types=1);

namespace App\Form\Task;

use App\Service\Task\Assignment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * "Weitergeben": a new responsible person or role.
 *
 * @extends AbstractType<array{assignee: Assignment}>
 */
final class HandOverType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('assignee', ChoiceType::class, [
            'label' => 'An wen?',
            'choices' => $options['assignees'],
            'expanded' => true,
            'choice_value' => static fn (?Assignment $assignment): ?string => $assignment?->key(),
            'choice_label' => static fn (Assignment $assignment): string => $assignment->label(),
            'constraints' => [new NotNull(message: 'Bitte wähle, wer die Aufgabe übernimmt.')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('assignees');
        $resolver->setAllowedTypes('assignees', 'array');
    }
}
