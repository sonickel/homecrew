<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Schema;

use App\Service\Schema\CheckDatabases;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckDatabasesTest extends TestCase
{
    private const string URL = 'mysql://homecrew:p%40ss%2Fw@127.0.0.1:3306/homecrew?serverVersion=11.8.6-MariaDB&charset=utf8mb4';

    public function testScratchDatabasesAreDerivedFromTheAppDatabase(): void
    {
        $databases = CheckDatabases::fromDatabaseUrl(self::URL);

        self::assertSame('homecrew_smoke', $databases->reference);
        self::assertSame('homecrew_check', $databases->check);
    }

    public function testUrlKeepsCredentialsHostAndOptions(): void
    {
        $databases = CheckDatabases::fromDatabaseUrl(self::URL);

        self::assertSame(
            'mysql://homecrew:p%40ss%2Fw@127.0.0.1:3306/homecrew_check?serverVersion=11.8.6-MariaDB&charset=utf8mb4',
            $databases->urlFor('homecrew_check'),
        );
        self::assertSame('mysql://u@db/homecrew_smoke', CheckDatabases::fromDatabaseUrl('mysql://u@db/homecrew')->urlFor('homecrew_smoke'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherDatabases(): iterable
    {
        yield 'App-Datenbank' => ['homecrew'];
        yield 'Test-Datenbank' => ['homecrew_test'];
        yield 'Systemdatenbank' => ['mysql'];
        yield 'anderer Haushalt' => ['nachbarn_smoke'];
    }

    #[DataProvider('otherDatabases')]
    public function testNoOtherDatabaseCanBeTargeted(string $database): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CheckDatabases::fromDatabaseUrl(self::URL)->urlFor($database);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function urlsWithoutUsableDatabase(): iterable
    {
        yield 'ohne Pfad' => ['mysql://homecrew:pw@127.0.0.1:3306'];
        yield 'leerer Name' => ['mysql://homecrew:pw@127.0.0.1:3306/?serverVersion=11.8.6-MariaDB'];
        yield 'Sonderzeichen' => ['mysql://homecrew:pw@127.0.0.1:3306/home`crew'];
        yield 'kein URL' => ['homecrew'];
    }

    #[DataProvider('urlsWithoutUsableDatabase')]
    public function testUrlMustNameTheAppDatabase(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CheckDatabases::fromDatabaseUrl($url);
    }
}
