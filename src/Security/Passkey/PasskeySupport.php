<?php

declare(strict_types=1);

namespace App\Security\Passkey;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Passkeys are bound to a name (relying party id such as "localhost" or the household's domain).
 * Browsers and password managers refuse them on an IP address like 127.0.0.1 or 192.168.1.20 –
 * there HomeCrew hides the passkey buttons and points to the address with a name instead of failing silently.
 */
final readonly class PasskeySupport
{
    public function __construct(
        private RequestStack $requestStack,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $defaultUri,
    ) {
    }

    #[AsTwigFunction('passkeys_possible')]
    public function isPossible(): bool
    {
        return !self::isIpAddress($this->requestStack->getMainRequest()?->getHost());
    }

    /**
     * Where HomeCrew can be opened with a name: on the loopback address simply "localhost" (same computer,
     * same port – the Symfony CLI announces https://127.0.0.1:8000 and sets DEFAULT_URI to it), otherwise
     * DEFAULT_URI if it has a name. Null if no such address is known.
     */
    #[AsTwigFunction('passkey_address')]
    public function address(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        if (null !== $request && self::isLoopback($request->getHost())) {
            return self::withHost($request, 'localhost');
        }

        $host = parse_url($this->defaultUri, \PHP_URL_HOST);

        return \is_string($host) && !self::isIpAddress($host) ? $this->defaultUri : null;
    }

    /**
     * Base address for QR codes and access cards (invitation, pairing): on 127.0.0.1 "localhost",
     * otherwise the address of this request.
     */
    public function linkBase(Request $request): string
    {
        $base = $this->isPossible() ? null : $this->address();

        return rtrim($base ?? $request->getSchemeAndHttpHost(), '/');
    }

    private static function isIpAddress(?string $host): bool
    {
        return null === $host || '' === $host || false !== filter_var(trim($host, '[]'), \FILTER_VALIDATE_IP);
    }

    private static function isLoopback(string $host): bool
    {
        $ip = trim($host, '[]');

        return '::1' === $ip || str_starts_with($ip, '127.') && false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4);
    }

    private static function withHost(Request $request, string $host): string
    {
        $port = $request->getPort();
        $defaultPort = 'https' === $request->getScheme() ? 443 : 80;

        return $request->getScheme().'://'.$host.(null === $port || (int) $port === $defaultPort ? '' : ':'.$port);
    }
}
