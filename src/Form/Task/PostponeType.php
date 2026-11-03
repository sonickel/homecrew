<?php

declare(strict_types=1);

namespace App\Form\Task;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * "Verschieben": a new due date, today or later and not the current one.
 *
 * @extends AbstractType<array{dueDate: \DateTimeImmutable}>
 */
final class PostponeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = (string) $options['today'];
        $current = (string) $options['current'];

        $builder->add('dueDate', DateType::class, [
            'label' => 'Neuer Tag',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['min' => $today],
            'constraints' => [
                new NotNull(message: 'Bitte wähle einen Tag.'),
                new Callback(static function (?\DateTimeImmutable $day, ExecutionContextInterface $context) use ($today, $current): void {
                    if (null === $day) {
                        return;
                    }
                    if ($day->format('Y-m-d') < $today) {
                        $context->addViolation('Der Tag liegt in der Vergangenheit. Bitte wähle heute oder später.');
                    } elseif ($day->format('Y-m-d') === $current) {
                        $context->addViolation('An diesem Tag ist die Aufgabe schon fällig. Bitte wähle einen anderen.');
                    }
                }),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['today', 'current']);
        $resolver->setAllowedTypes('today', 'string');
        $resolver->setAllowedTypes('current', 'string');
    }
}
