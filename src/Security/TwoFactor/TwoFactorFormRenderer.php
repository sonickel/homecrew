<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Second login step in the HomeCrew layout; the template words it after the person's method.
 */
final readonly class TwoFactorFormRenderer implements TwoFactorFormRendererInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $templateVars
     */
    public function renderForm(Request $request, array $templateVars): Response
    {
        return new Response($this->twig->render('security/two_factor.html.twig', $templateVars));
    }
}
