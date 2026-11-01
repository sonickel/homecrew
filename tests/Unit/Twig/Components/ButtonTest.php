<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components;

use App\Twig\Components\Button;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ButtonTest extends TestCase
{
    /**
     * @return iterable<array{string}>
     */
    public static function knownVariants(): iterable
    {
        yield ['primary'];
        yield ['secondary'];
        yield ['danger'];
        yield ['signal'];
    }

    #[DataProvider('knownVariants')]
    public function testKnownVariantIsAccepted(string $variant): void
    {
        $button = new Button();
        $button->mount($variant);

        self::assertSame($variant, $button->variant);
        self::assertSame('button', $button->type);
    }

    public function testUnknownVariantIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Button())->mount('rainbow');
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Button())->mount('primary', 'link');
    }
}
