<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Repository\EmergencyContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Emergency contact or important number (konzept-3 §2): shown on the emergency page by category,
 * on the lock screen of the team tablet when show_on_lockscreen is set and the contact is visible to everybody.
 */
#[ORM\Entity(repositoryClass: EmergencyContactRepository::class)]
#[ORM\Table(name: 'emergency_contact', options: ['comment' => 'Notfall-Infos: Kontakte und wichtige Nummern (Sperrbildschirm ohne PIN, wenn show_on_lockscreen)'])]
#[ORM\Index(name: 'ix_emergency', columns: ['category', 'sort'])]
#[ORM\Index(name: 'ix_emergency_poi', columns: ['poi_id'])]
class EmergencyContact
{
    /** Digits, spaces and + / - ( ) – what people write on a note next to the phone. */
    private const string PHONE_PATTERN = '/^\+?[0-9][0-9 \/()-]*[0-9]$/';
    private const string PHONE_MESSAGE = 'Bitte gib eine Telefonnummer mit Ziffern ein, z. B. 0931 123456 oder +49 931 123456.';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'category', length: 15, enumType: EmergencyCategory::class, options: ['default' => 'angehoerige'])]
    private EmergencyCategory $category = EmergencyCategory::Angehoerige;

    #[ORM\Column(name: 'name', length: 100)]
    #[Assert\NotBlank(message: 'Bitte gib einen Namen ein.')]
    #[Assert\Length(max: 100, maxMessage: 'Der Name darf höchstens {{ limit }} Zeichen haben.')]
    private string $name = '';

    #[ORM\Column(name: 'relation', length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    private ?string $relation = null;

    #[ORM\Column(name: 'phone', length: 30)]
    #[Assert\NotBlank(message: 'Bitte gib eine Telefonnummer ein.')]
    #[Assert\Length(max: 30, maxMessage: 'Die Nummer darf höchstens {{ limit }} Zeichen haben.')]
    #[Assert\Regex(pattern: self::PHONE_PATTERN, message: self::PHONE_MESSAGE)]
    private string $phone = '';

    #[ORM\Column(name: 'phone_alt', length: 30, nullable: true)]
    #[Assert\Length(max: 30, maxMessage: 'Die Nummer darf höchstens {{ limit }} Zeichen haben.')]
    #[Assert\Regex(pattern: self::PHONE_PATTERN, message: self::PHONE_MESSAGE)]
    private ?string $phoneAlt = null;

    #[ORM\Column(name: 'when_to_call', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    private ?string $whenToCall = null;

    /** Link to a place – chosen in the form once the places exist (M12). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'poi_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Poi $poi = null;

    #[ORM\Column(name: 'visibility', length: 10, enumType: EmergencyVisibility::class, options: ['default' => 'alle'])]
    private EmergencyVisibility $visibility = EmergencyVisibility::Alle;

    #[ORM\Column(name: 'show_on_lockscreen', options: ['default' => true])]
    private bool $showOnLockscreen = true;

    #[ORM\Column(name: 'sort', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\Range(min: 0, max: 999, notInRangeMessage: 'Bitte eine Zahl von {{ min }} bis {{ max }}.')]
    private int $sort = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): EmergencyCategory
    {
        return $this->category;
    }

    public function setCategory(EmergencyCategory $category): void
    {
        $this->category = $category;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = trim((string) $name);
    }

    public function getRelation(): ?string
    {
        return $this->relation;
    }

    public function setRelation(?string $relation): void
    {
        $this->relation = self::optional($relation);
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = trim((string) $phone);
    }

    public function getPhoneAlt(): ?string
    {
        return $this->phoneAlt;
    }

    public function setPhoneAlt(?string $phoneAlt): void
    {
        $this->phoneAlt = self::optional($phoneAlt);
    }

    public function getWhenToCall(): ?string
    {
        return $this->whenToCall;
    }

    public function setWhenToCall(?string $whenToCall): void
    {
        $this->whenToCall = self::optional($whenToCall);
    }

    public function getPoi(): ?Poi
    {
        return $this->poi;
    }

    public function getVisibility(): EmergencyVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(EmergencyVisibility $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function isAdminOnly(): bool
    {
        return EmergencyVisibility::Admins === $this->visibility;
    }

    public function isShowOnLockscreen(): bool
    {
        return $this->showOnLockscreen;
    }

    public function setShowOnLockscreen(bool $showOnLockscreen): void
    {
        $this->showOnLockscreen = $showOnLockscreen;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(?int $sort): void
    {
        $this->sort = $sort ?? 0;
    }

    /**
     * The phone number for a tel: link.
     */
    public function getPhoneDial(): string
    {
        return self::dialable($this->phone);
    }

    public function getPhoneAltDial(): ?string
    {
        return null === $this->phoneAlt ? null : self::dialable($this->phoneAlt);
    }

    /**
     * Number for a tel: link: "+49 (931) 12-34" → "+499311234", "0931 / 1234" → "09311234".
     */
    public static function dialable(string $phone): string
    {
        return (str_starts_with(trim($phone), '+') ? '+' : '').preg_replace('/\D/', '', $phone);
    }

    private static function optional(?string $text): ?string
    {
        $text = trim((string) $text);

        return '' === $text ? null : $text;
    }
}
