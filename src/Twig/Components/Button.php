<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Button or link styled as button; at least 48 px, label says what happens.
 */
#[AsTwigComponent]
final class Button
{
    private const array VARIANTS = ['primary', 'secondary', 'danger', 'signal'];
    private const array TYPES = ['button', 'submit', 'reset'];

    public string $variant = 'primary';
    public string $type = 'button';
    public ?string $href = null;
    public ?string $icon = null;

    public function mount(string $variant = 'primary', string $type = 'button'): void
    {
        if (!\in_array($variant, self::VARIANTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown button variant "%s", expected one of: %s.', $variant, implode(', ', self::VARIANTS)));
        }
        if (!\in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown button type "%s", expected one of: %s.', $type, implode(', ', self::TYPES)));
        }

        $this->variant = $variant;
        $this->type = $type;
    }
}
