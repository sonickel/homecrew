<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Enum\FontSize;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Font size and high contrast of a person ("Mein Konto → Darstellung").
 *
 * @extends AbstractType<array{fontSize: FontSize, highContrast: bool}>
 */
final class DisplaySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fontSize', EnumType::class, [
                'class' => FontSize::class,
                'label' => 'Schriftgröße',
                'expanded' => true,
                'choice_label' => static fn (FontSize $size): string => match ($size) {
                    FontSize::Normal => 'Normal',
                    FontSize::Gross => 'Groß',
                    FontSize::SehrGross => 'Sehr groß',
                },
            ])
            ->add('highContrast', CheckboxType::class, [
                'label' => 'Hoher Kontrast (schwarz auf weiß)',
                'required' => false,
            ]);
    }
}
