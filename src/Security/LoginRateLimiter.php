<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Device\DeviceAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * The login throttling of the firewall (5 tries per name and address, 25 per address in 15 minutes) – without the
 * team tablet's own login by cookie: it is no guess, so it neither counts nor gets blocked (an address with many
 * wrong passwords must not unpair the tablet) and does not reset the counters of the address either.
 */
#[AsDecorator('security.login_throttling.main.limiter')]
final readonly class LoginRateLimiter implements PeekableRequestRateLimiterInterface
{
    public function __construct(
        #[AutowireDecorated]
        private PeekableRequestRateLimiterInterface $inner,
    ) {
    }

    public function consume(Request $request): RateLimit
    {
        return self::isTablet($request) ? self::unlimited() : $this->inner->consume($request);
    }

    public function peek(Request $request): RateLimit
    {
        return self::isTablet($request) ? self::unlimited() : $this->inner->peek($request);
    }

    public function reset(Request $request): void
    {
        if (!self::isTablet($request)) {
            $this->inner->reset($request);
        }
    }

    private static function isTablet(Request $request): bool
    {
        return $request->attributes->getBoolean(DeviceAuthenticator::IN_PROGRESS);
    }

    /**
     * All tokens left: the throttling listener then also skips the reset after the login.
     */
    private static function unlimited(): RateLimit
    {
        return new RateLimit(1, new \DateTimeImmutable(), true, 1);
    }
}
