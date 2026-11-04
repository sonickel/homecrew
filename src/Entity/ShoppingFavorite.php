<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShoppingFavoriteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Favourite item for adding with one tap (konzept-2 §9) – for one list or, without a list, for all of them.
 * Admins keep them in Verwaltung → Lieblingsartikel; the link goes over to the list entry.
 */
#[ORM\Entity(repositoryClass: ShoppingFavoriteRepository::class)]
#[ORM\Table(name: 'shopping_favorite', options: ['comment' => 'Lieblingsartikel zum schnellen Hinzufügen'])]
#[ORM\Index(name: 'ix_favorite', columns: ['sort'])]
#[ORM\Index(name: 'ix_favorite_list', columns: ['list_id'])]
#[ORM\Index(name: 'fk_favorite_poi', columns: ['poi_id'])]
class ShoppingFavorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    /** Null = offered on every list. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'list_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ShoppingList $list = null;

    #[ORM\Column(name: 'name', length: 100)]
    #[Assert\NotBlank(message: 'Bitte gib einen Namen ein.')]
    #[Assert\Length(max: 100, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    private string $name;

    #[ORM\Column(name: 'default_quantity', length: 30, nullable: true)]
    #[Assert\Length(max: 30, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    private ?string $defaultQuantity = null;

    /** https only (ck_favorite_link). */
    #[ORM\Column(name: 'link', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    #[Assert\Url(message: 'Bitte gib eine Adresse ein, die mit https:// beginnt.', protocols: ['https'], requireTld: true)]
    private ?string $link = null;

    /** Shop – places follow in M12. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'poi_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Poi $poi = null;

    #[ORM\Column(name: 'category', length: 30, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(name: 'sort', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\Range(min: 0, max: 999, notInRangeMessage: 'Bitte eine Zahl von {{ min }} bis {{ max }}.')]
    private int $sort = 0;

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getList(): ?ShoppingList
    {
        return $this->list;
    }

    public function setList(?ShoppingList $list): void
    {
        $this->list = $list;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = trim((string) $name);
    }

    public function getDefaultQuantity(): ?string
    {
        return $this->defaultQuantity;
    }

    public function setDefaultQuantity(?string $defaultQuantity): void
    {
        $this->defaultQuantity = self::emptyToNull($defaultQuantity);
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = self::emptyToNull($link);
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(?int $sort): void
    {
        $this->sort = $sort ?? 0;
    }

    private static function emptyToNull(?string $text): ?string
    {
        $text = trim((string) $text);

        return '' === $text ? null : $text;
    }
}
