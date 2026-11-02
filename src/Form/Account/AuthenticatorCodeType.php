<?php

declare(strict_types=1);

namespace App\Form\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The 6-digit code from the authenticator app that confirms the setup.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class AuthenticatorCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'Code aus der App',
            'help' => '6 Ziffern, ändert sich alle 30 Sekunden.',
            'attr' => ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'maxlength' => 7, 'class' => 'hc-input--code'],
            'constraints' => [new NotBlank(message: 'Bitte gib den Code aus der App ein.')],
        ]);
    }
}
