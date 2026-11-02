<?php

declare(strict_types=1);

namespace App\Service\Tablet;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * Whether a request comes from the home network (konzept-12 §5: the team tablet works only there, never via the internet).
 *
 * Home network = the direct peer (REMOTE_ADDR) and every address named in forwarding headers lie in HOME_NETWORKS.
 * Forwarding headers can only make the check stricter: an address outside the list, or one that is no IP address,
 * fails it. Requests marked by a CDN or tunnel (Cloudflare) never count, whatever addresses they name.
 * Request::getClientIp() is not used on purpose: with trusted proxies it would believe a forged X-Forwarded-For.
 * Locally the Symfony CLI sends "X-Forwarded-For: 127.0.0.1", which passes with the loopback networks.
 */
final readonly class HomeNetwork
{
    /** Set by Cloudflare (tunnel) and other CDNs: such a request came from the internet. */
    private const array TUNNEL_HEADERS = ['cf-connecting-ip', 'cf-ray', 'cf-ipcountry', 'cdn-loop', 'true-client-ip'];

    /** @var list<string> */
    private array $networks;

    /**
     * @param string $networks comma-separated list such as "192.168.1.0/24, fd00::/8"; empty = no home network.
     *                         Invalid entries match nothing (the tablet then stays locked rather than open).
     */
    public function __construct(
        #[Autowire('%env(HOME_NETWORKS)%')]
        string $networks,
    ) {
        $this->networks = array_values(array_filter(array_map('trim', explode(',', $networks)), static fn (string $network): bool => '' !== $network));
    }

    public function contains(Request $request): bool
    {
        if ([] === $this->networks) {
            return false;
        }
        foreach (self::TUNNEL_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        foreach ($this->addresses($request) as $address) {
            $ip = self::normalize($address);
            if (null === $ip || !IpUtils::checkIp($ip, $this->networks)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The direct peer and every address a proxy wrote into the request.
     *
     * @return list<string>
     */
    private function addresses(Request $request): array
    {
        $addresses = [(string) $request->server->get('REMOTE_ADDR', '')];
        foreach ($request->headers->all('x-forwarded-for') as $value) {
            array_push($addresses, ...explode(',', (string) $value));
        }
        foreach ($request->headers->all('x-real-ip') as $value) {
            $addresses[] = (string) $value;
        }
        foreach ($request->headers->all('forwarded') as $value) {
            // Forwarded: for=192.0.2.60;proto=http, for="[2001:db8::1]:4711"
            preg_match_all('/for=("[^"]*"|[^;,\s]*)/i', (string) $value, $matches);
            array_push($addresses, ...$matches[1]);
        }

        return $addresses;
    }

    /**
     * Plain IP address without quotes, brackets or port; IPv4 inside IPv6 (::ffff:192.168.1.5) as IPv4.
     * Null for anything else (e.g. "unknown" or an obfuscated name in a Forwarded header).
     */
    private static function normalize(string $address): ?string
    {
        $address = trim($address, " \t\"");
        if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $address, $match)) {
            $address = $match[1];
        } elseif (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $address, $match)) {
            $address = $match[1];
        }

        if (false === filter_var($address, \FILTER_VALIDATE_IP)) {
            return null;
        }

        $binary = (string) inet_pton($address);
        if (16 === \strlen($binary) && str_starts_with($binary, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($binary, 12));
        }

        return $address;
    }
}
