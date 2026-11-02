<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Household\HouseholdContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Dates in templates are shown in the household's time zone (app_setting.timezone);
 * config/packages/twig.yaml only holds the fallback.
 */
final readonly class TwigTimezoneSubscriber
{
    public function __construct(
        private Environment $twig,
        private HouseholdContext $household,
    ) {
    }

    #[AsEventListener]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->twig->getExtension(CoreExtension::class)->setTimezone($this->household->timezone());
        }
    }
}
