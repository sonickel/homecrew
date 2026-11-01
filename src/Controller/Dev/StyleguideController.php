<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use App\Enum\FontSize;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Style guide for checking tokens, font sizes and high contrast in the browser (dev environment only).
 * Colour values are read from assets/styles/tokens.css so the page never shows outdated values.
 */
final class StyleguideController extends AbstractController
{
    private const array PALETTE = ['ink', 'paper', 'surface', 'enamel', 'signal', 'line', 'border', 'ok', 'due', 'late', 'emergency'];

    /** @var list<array{label: string, foreground: string, background: string, minimum: float}> */
    private const array PAIRS = [
        ['label' => 'Text auf Papier', 'foreground' => 'ink', 'background' => 'paper', 'minimum' => 4.5],
        ['label' => 'Weiß auf Emaille-Blau', 'foreground' => 'on-enamel', 'background' => 'enamel', 'minimum' => 4.5],
        ['label' => 'Text auf Signal-Gelb', 'foreground' => 'on-signal', 'background' => 'signal', 'minimum' => 4.5],
        ['label' => 'Erledigt auf Papier', 'foreground' => 'ok', 'background' => 'paper', 'minimum' => 4.5],
        ['label' => 'Fällig auf Papier', 'foreground' => 'due', 'background' => 'paper', 'minimum' => 4.5],
        ['label' => 'Überfällig auf Papier', 'foreground' => 'late', 'background' => 'paper', 'minimum' => 4.5],
        ['label' => 'Weiß auf Notfall-Rot', 'foreground' => 'on-emergency', 'background' => 'emergency', 'minimum' => 4.5],
        ['label' => 'Rahmen auf Papier', 'foreground' => 'border', 'background' => 'paper', 'minimum' => 3.0],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/assets/styles/tokens.css')]
        private readonly string $tokensFile,
    ) {
    }

    #[Route('/_gestaltung', name: 'app_dev_styleguide', methods: ['GET'], env: 'dev')]
    public function index(Request $request): Response
    {
        $fontSize = FontSize::tryFrom($request->query->getString('schrift')) ?? FontSize::Normal;
        $highContrast = 'hoch' === $request->query->getString('kontrast');
        $tokens = $this->readDefaultColorTokens();

        $colors = array_map(
            static fn (string $token): array => ['token' => $token, 'hex' => $tokens[$token] ?? '?'],
            self::PALETTE,
        );

        $pairs = [];
        foreach (self::PAIRS as $pair) {
            $pairs[] = $pair + ['ratio' => self::contrastRatio($tokens[$pair['foreground']], $tokens[$pair['background']])];
        }

        return $this->render('dev/styleguide.html.twig', [
            'hc_font_size' => $fontSize->value,
            'hc_high_contrast' => $highContrast,
            'font_sizes' => FontSize::cases(),
            'colors' => $colors,
            'pairs' => $pairs,
        ]);
    }

    /**
     * Colour tokens (#rrggbb) of the first :root block, i.e. the default palette.
     *
     * @return array<string, string>
     */
    private function readDefaultColorTokens(): array
    {
        $css = (string) file_get_contents($this->tokensFile);
        $start = (int) strpos($css, ':root {');
        $block = substr($css, $start, (int) strpos($css, '}', $start) - $start);
        preg_match_all('/--hc-([a-z-]+):\s*(#[0-9a-f]{6});/i', $block, $matches, \PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = strtolower($match[2]);
        }

        return $tokens;
    }

    /**
     * WCAG 2.x contrast ratio of two #rrggbb colours.
     */
    private static function contrastRatio(string $foreground, string $background): float
    {
        $luminance = static function (string $hex): float {
            $channels = array_map(
                static function (string $channel): float {
                    $value = hexdec($channel) / 255;

                    return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
                },
                str_split(ltrim($hex, '#'), 2),
            );

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };

        $lighter = max($luminance($foreground), $luminance($background));
        $darker = min($luminance($foreground), $luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
