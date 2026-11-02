<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Passkey\PasskeySupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PasskeySupportTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function addresses(): iterable
    {
        yield 'localhost' => ['https://localhost:8000/anmelden', true];
        yield 'Domain des Haushalts' => ['https://homecrew.example.org/anmelden', true];
        yield 'IPv4 lokal' => ['https://127.0.0.1:8000/anmelden', false];
        yield 'IPv4 im WLAN' => ['https://192.168.1.20/anmelden', false];
        yield 'IPv6' => ['https://[::1]:8000/anmelden', false];
    }

    #[DataProvider('addresses')]
    public function testPasskeysNeedANameInsteadOfAnIpAddress(string $url, bool $possible): void
    {
        self::assertSame($possible, $this->support($url, 'https://localhost:8000')->isPossible());
    }

    public function testSuggestedAddressHasAName(): void
    {
        // Loopback: localhost is the same computer, the port stays
        self::assertSame('https://localhost:8000', $this->support('https://127.0.0.1:8000/anmelden', 'https://127.0.0.1:8000')->address());
        self::assertSame('https://localhost', $this->support('https://[::1]/anmelden', '')->address());
        // Other IP addresses: the configured address, if it has a name
        self::assertSame('https://homecrew.example.org', $this->support('https://192.168.1.20/', 'https://homecrew.example.org')->address());
        self::assertNull($this->support('https://192.168.1.20/', 'https://192.168.1.20')->address());
        self::assertNull($this->support('https://192.168.1.20/', '')->address());
    }

    private function support(string $url, string $defaultUri): PasskeySupport
    {
        $requests = new RequestStack();
        $requests->push(Request::create($url));

        return new PasskeySupport($requests, $defaultUri);
    }
}
