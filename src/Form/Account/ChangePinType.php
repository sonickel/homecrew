<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Security\Pin\PinRules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * New PIN for the tablet (twice); the current PIN only when it is no start PIN.
 * The rules (6 digits, nothing simple, not the old one) are checked by App\Service\Tablet\PinLogin.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ChangePinType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $pinField = [
            'autocomplete' => 'off',
            'inputmode' => 'numeric',
            'maxlength' => PinRules::LENGTH,
            'class' => 'hc-input hc-input--pin',
        ];

        if ($options['ask_current']) {
            $builder->add('current', PasswordType::class, [
                'label' => 'Bisherige PIN',
                'attr' => $pinField,
            ]);
        }
        $builder->add('pin', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Die beiden PINs sind nicht gleich.',
            'first_options' => [
                'label' => 'Neue PIN',
                'help' => \sprintf('Genau %d Ziffern. Nicht 123456 oder lauter gleiche Ziffern.', PinRules::LENGTH),
                'attr' => $pinField,
            ],
            'second_options' => [
                'label' => 'Neue PIN wiederholen',
                'attr' => $pinField,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('ask_current', false);
        $resolver->setAllowedTypes('ask_current', 'bool');
    }
}
