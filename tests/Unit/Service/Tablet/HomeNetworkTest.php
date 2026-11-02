<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Tablet;

use App\Service\Tablet\HomeNetwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HomeNetworkTest extends TestCase
{
    private const string NETWORKS = '192.168.1.0/24, fd00::/8, 127.0.0.0/8, ::1';

    /**
     * @param array<string, string> $headers
     */
    private static function request(string $remoteAddress, array $headers = []): Request
    {
        $server = ['REMOTE_ADDR' => $remoteAddress];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request(server: $server);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function homeRequests(): iterable
    {
        yield 'IPv4 im WLAN' => ['192.168.1.23', []];
        yield 'IPv6 (ULA) im WLAN' => ['fd12:3456::7', []];
        yield 'IPv4 in IPv6 verpackt' => ['::ffff:192.168.1.23', []];
        yield 'Loopback' => ['127.0.0.1', []];
        yield 'IPv6-Loopback' => ['::1', []];
        yield 'lokaler Webserver mit X-Forwarded-For' => ['127.0.0.1', ['X-Forwarded-For' => '127.0.0.1']];
        yield 'Proxy im Heimnetz' => ['192.168.1.2', ['X-Forwarded-For' => '192.168.1.23, 192.168.1.2', 'X-Real-IP' => '192.168.1.23']];
        yield 'Forwarded mit Port und IPv6' => ['192.168.1.2', ['Forwarded' => 'for=192.168.1.23:4711;proto=https, for="[fd00::5]:80"']];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('homeRequests')]
    public function testRequestFromTheHomeNetwork(string $remoteAddress, array $headers): void
    {
        self::assertTrue((new HomeNetwork(self::NETWORKS))->contains(self::request($remoteAddress, $headers)));
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function foreignRequests(): iterable
    {
        yield 'fremde IPv4' => ['203.0.113.5', []];
        yield 'fremde IPv6' => ['2001:db8::1', []];
        yield 'fremde IPv4 in IPv6 verpackt' => ['::ffff:203.0.113.5', []];
        yield 'Nachbarnetz' => ['192.168.2.23', []];
        yield 'keine Adresse' => ['', []];
        yield 'Weiterleitung von außen' => ['127.0.0.1', ['X-Forwarded-For' => '203.0.113.5, 127.0.0.1']];
        yield 'X-Real-IP von außen' => ['192.168.1.2', ['X-Real-IP' => '203.0.113.5']];
        yield 'Forwarded von außen' => ['192.168.1.2', ['Forwarded' => 'for="[2001:db8::1]:4711"']];
        yield 'Forwarded ohne Adresse' => ['192.168.1.2', ['Forwarded' => 'for=unknown']];
        yield 'X-Forwarded-For ohne Adresse' => ['192.168.1.2', ['X-Forwarded-For' => 'irgendwas']];
        yield 'Tunnel mit gefälschter Heimadresse' => ['127.0.0.1', ['CF-Connecting-IP' => '192.168.1.23', 'X-Forwarded-For' => '192.168.1.23']];
        yield 'Tunnel-Kennung CF-Ray' => ['192.168.1.2', ['CF-Ray' => '8a1b2c3d4e5f-FRA']];
        yield 'CDN-Loop' => ['192.168.1.2', ['CDN-Loop' => 'cloudflare']];
        yield 'True-Client-IP' => ['192.168.1.2', ['True-Client-IP' => '192.168.1.23']];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('foreignRequests')]
    public function testRequestFromOutside(string $remoteAddress, array $headers): void
    {
        self::assertFalse((new HomeNetwork(self::NETWORKS))->contains(self::request($remoteAddress, $headers)));
    }

    public function testWithoutConfiguredNetworksNothingIsHome(): void
    {
        self::assertFalse((new HomeNetwork(' , '))->contains(self::request('127.0.0.1')));
    }

    public function testInvalidEntriesMatchNothing(): void
    {
        $homeNetwork = new HomeNetwork('kein-netz, 192.168.1.0/99, 10.0.0.0/8');

        self::assertFalse($homeNetwork->contains(self::request('192.168.1.23')));
        self::assertTrue($homeNetwork->contains(self::request('10.1.2.3')));
    }
}
