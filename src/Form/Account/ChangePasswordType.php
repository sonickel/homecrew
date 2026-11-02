<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Security\PasswordRules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * New password, typed twice (rules: App\Security\PasswordRules).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Die beiden Passwörter sind nicht gleich.',
            'first_options' => [
                'label' => 'Neues Passwort',
                'help' => 'Mindestens 10 Zeichen. Gut sind mehrere Wörter, die nicht zusammengehören.',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'Neues Passwort wiederholen',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'constraints' => PasswordRules::constraints(),
        ]);
    }
}
