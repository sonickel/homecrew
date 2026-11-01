<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErrorPageTest extends WebTestCase
{
    public function testNotFoundPageIsGerman(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/gibt-es-nicht');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Seite nicht gefunden');
        self::assertSelectorExists('a.hc-button[href="/"]');
    }

    public function testStyleguideIsNotAvailableOutsideDev(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/_gestaltung');

        self::assertResponseStatusCodeSame(404);
    }
}
