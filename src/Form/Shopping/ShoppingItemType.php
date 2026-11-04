<?php

declare(strict_types=1);

namespace App\Form\Shopping;

use App\Entity\ShoppingItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A list entry: what, how much – and if needed a note or a link to the product (https only, opens another site).
 *
 * @extends AbstractType<ShoppingItemData>
 */
final class ShoppingItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Was?',
                'attr' => ['maxlength' => ShoppingItem::NAME_MAX, 'autocomplete' => 'off'],
            ])
            ->add('quantity', TextType::class, [
                'label' => 'Menge',
                'required' => false,
                'attr' => ['maxlength' => ShoppingItem::QUANTITY_MAX, 'autocomplete' => 'off'],
            ])
            ->add('note', TextType::class, [
                'label' => 'Notiz',
                'required' => false,
                'help' => 'Z. B. Marke oder Größe.',
                'attr' => ['maxlength' => ShoppingItem::NOTE_MAX],
            ])
            ->add('link', UrlType::class, [
                'label' => 'Link',
                'required' => false,
                'default_protocol' => null,
                'help' => 'Z. B. zum Produkt im Shop, beginnt mit https://',
                'attr' => ['maxlength' => ShoppingItem::LINK_MAX, 'placeholder' => 'https://'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ShoppingItemData::class]);
    }
}
