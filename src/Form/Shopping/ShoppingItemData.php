<?php

declare(strict_types=1);

namespace App\Form\Shopping;

use App\Entity\ShoppingItem;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the list forms edit ("Auf die Liste", "Bearbeiten"); App\Service\Shopping\ShoppingItems applies it.
 */
final class ShoppingItemData
{
    #[Assert\NotBlank(message: 'Bitte schreib, was auf die Liste soll.')]
    #[Assert\Length(max: ShoppingItem::NAME_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $name = null;

    #[Assert\Length(max: ShoppingItem::QUANTITY_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $quantity = null;

    #[Assert\Length(max: ShoppingItem::NOTE_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $note = null;

    #[Assert\Length(max: ShoppingItem::LINK_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    #[Assert\Url(message: 'Bitte gib eine Adresse ein, die mit https:// beginnt.', protocols: ['https'], requireTld: true)]
    public ?string $link = null;

    public static function fromItem(ShoppingItem $item): self
    {
        $data = new self();
        $data->name = $item->getName();
        $data->quantity = $item->getQuantity();
        $data->note = $item->getNote();
        $data->link = $item->getLink();

        return $data;
    }
}
