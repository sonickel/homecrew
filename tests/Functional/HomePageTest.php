<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * "Heute" – placeholder until M5 – with the person's display settings.
 */
final class HomePageTest extends WebTestCase
{
    use PeopleTrait;

    public function testTodayGreetsThePerson(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'Heute – Mein Haushalt');
        self::assertSelectorTextSame('h1', 'Heute');
        self::assertSelectorTextSame('.hc-page-header__intro', 'Hallo Papa');
        self::assertSelectorTextContains('.hc-empty-state', 'Bald siehst du hier, was heute zu tun ist.');
        self::assertSelectorExists('.hc-topbar__nav form[action="/abmelden"] button');
    }

    public function testFontSizeAndContrastOfThePersonAreUsed(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->entityManager()->getConnection()->update('app_user', ['font_size' => 'sehr_gross', 'high_contrast' => 1], ['id' => $papa->getId()]);
        $this->entityManager()->refresh($papa);
        $client->loginUser($papa);

        $client->request('GET', '/');

        self::assertSelectorExists('html[data-font="sehr_gross"][data-contrast="hoch"]');
    }
}
