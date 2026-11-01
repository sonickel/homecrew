<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Gives templates the current time from the (testable) clock instead of "now".
 */
final readonly class ClockExtension
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    #[AsTwigFunction('today')]
    public function today(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
