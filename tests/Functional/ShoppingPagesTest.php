<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\ShoppingFavorite;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Enum\AssigneeRole;
use App\Enum\UserRole;
use App\Service\Tablet\DeviceCookie;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Lists for every role and the team tablet (konzept-2 §9, konzept-1 §3): read, add, tick off and print; entries are
 * changed by admins and whoever added them; Verwaltung → Listen only for admins – Wednesday 4 November 2026.
 */
final class ShoppingPagesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    private const string NOW = '2026-11-04 08:00:00 UTC';

    protected function tearDown(): void
    {
        $this->restoreTwoFactorSwitch();
        parent::tearDown();
    }

    public function testEverybodyReadsAddsAndTicksOff(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $list = $this->list('Einkauf', AssigneeRole::Assistenz);

        foreach ([[$admin, 'Kaffee'], [$papa, 'Zucker'], [$anna, 'Milch'], [null, 'Brot']] as [$person, $item]) {
            $this->switchTo($client, $person);
            $client->request('GET', '/listen');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.hc-task-list', 'Einkauf');

            $crawler = $client->request('GET', '/listen/'.$list->getId());
            $client->submit($crawler->selectButton('Auf die Liste')->form(), ['shopping_item[name]' => $item, 'shopping_item[quantity]' => '1 Packung']);
            self::assertResponseRedirects('/listen/'.$list->getId(), 303);
            $crawler = $client->followRedirect();
            self::assertSelectorTextContains('.hc-alert', '„'.$item.'“ steht jetzt auf der Liste.');

            $client->submit($crawler->filter('button:contains("Abhaken: '.$item.'")')->form());
            $client->followRedirect();
            self::assertSelectorTextContains('#abgehakt + .hc-task-list', $item);
        }

        $rows = $this->entityManager()->getConnection()->fetchAllAssociativeIndexed('SELECT name, created_by_user_id, created_by_device_id, is_done FROM shopping_item');
        self::assertSame([null, 1], [$rows['Brot']['created_by_user_id'], (int) $rows['Brot']['is_done']]);
        self::assertNotNull($rows['Brot']['created_by_device_id']);
        self::assertSame($anna->getId(), (int) $rows['Milch']['created_by_user_id']);
    }

    public function testTakingBackAndNoSecondEntryOfTheSameName(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $list = $this->list('Einkauf');
        $client->loginUser($anna);

        $crawler = $client->request('GET', '/listen/'.$list->getId());
        $client->submit($crawler->selectButton('Auf die Liste')->form(), ['shopping_item[name]' => 'Milch']);
        $crawler = $client->request('GET', '/listen/'.$list->getId());
        $client->submit($crawler->selectButton('Auf die Liste')->form(), ['shopping_item[name]' => 'milch']);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', '„milch“ steht schon auf der Liste.');
        self::assertSame(1, (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM shopping_item'));

        $crawler = $client->request('GET', '/listen/'.$list->getId());
        $client->submit($crawler->filter('button:contains("Abhaken: Milch")')->form());
        $crawler = $client->followRedirect();
        $client->submit($crawler->filter('button:contains("Zurückholen: Milch")')->form());
        $client->followRedirect();
        self::assertSelectorTextContains('#auf-der-liste + .hc-task-list', 'Milch');
        self::assertSelectorNotExists('#abgehakt');
    }

    public function testEntriesAreChangedByAdminsAndWhoeverAddedThem(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $ben = $this->createPerson(UserRole::Assistenz, 'ben');
        $list = $this->list('Einkauf');
        $tablet = $this->pairTablet($client);
        $annas = $this->item($list, 'Milch', $anna);
        $tablets = $this->item($list, 'Brot', $tablet);

        foreach ([[$anna, 200, 403], [$ben, 403, 403], [$admin, 200, 200], [null, 403, 200]] as [$person, $annasStatus, $tabletsStatus]) {
            $this->switchTo($client, $person);
            $client->request('GET', '/listen/eintraege/'.$annas->getId().'/bearbeiten');
            self::assertResponseStatusCodeSame($annasStatus, $person?->getUsername() ?? 'tablet');
            $client->request('GET', '/listen/eintraege/'.$tablets->getId().'/bearbeiten');
            self::assertResponseStatusCodeSame($tabletsStatus, $person?->getUsername() ?? 'tablet');
        }

        $this->switchTo($client, $anna);
        $crawler = $client->request('GET', '/listen/eintraege/'.$annas->getId().'/bearbeiten');
        $client->submit($crawler->selectButton('Speichern')->form(), ['shopping_item[link]' => 'http://shop.example/milch']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field--error', 'Bitte gib eine Adresse ein, die mit https:// beginnt.');
        $client->submit($crawler->selectButton('Speichern')->form(), ['shopping_item[link]' => 'https://shop.example/milch', 'shopping_item[quantity]' => '2 Liter']);
        $crawler = $client->followRedirect();
        self::assertSelectorExists('a.hc-external-link[href="https://shop.example/milch"][rel~="noopener"][target="_blank"]');

        $crawler = $client->request('GET', '/listen/eintraege/'.$annas->getId().'/bearbeiten');
        $client->submit($crawler->selectButton('Entfernen')->form());
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', '„Milch“ ist von der Liste entfernt.');
        self::assertSame(['Brot'], $this->entityManager()->getConnection()->fetchFirstColumn('SELECT name FROM shopping_item'));
    }

    public function testFavouritesGoOnTheListWithOneTapAndBringTheirLink(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $list = $this->list('Besorgt die Familie');
        $other = $this->list('Drogerie');
        $favorite = $this->favorite('Glühbirne E27', $list, 'https://shop.example/gluehbirne');
        $this->favorite('Brot', null);
        $foreign = $this->favorite('Seife', $other);
        $this->pairTablet($client);

        $crawler = $client->request('GET', '/listen/'.$list->getId());
        // Same order number: by name
        self::assertSame(['Brot', 'Glühbirne E27 (2 Stück)'], $crawler->filter('.hc-favorites button')->each(static fn ($button): string => trim($button->text())));
        $client->submit($crawler->filter('.hc-favorites form[action$="/lieblingsartikel/'.$favorite->getId().'"]')->form());
        $client->followRedirect();

        self::assertSelectorTextContains('#auf-der-liste + .hc-task-list', 'Glühbirne E27 2 Stück');
        self::assertSelectorExists('a.hc-external-link[href="https://shop.example/gluehbirne"]');
        self::assertSame(['Brot'], $client->getCrawler()->filter('.hc-favorites button')->each(static fn ($button): string => trim($button->text())));

        // A favourite of another list is not for this one
        $client->request('POST', '/listen/'.$list->getId().'/lieblingsartikel/'.$foreign->getId(), ['_token' => 'irrelevant']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testWhoeverTakesCareOfTheListIsTold(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $sister = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true);
        $list = $this->list('Besorgt die Familie', $sister);
        $this->pairTablet($client);

        foreach (['Batterien', 'Glühbirne'] as $item) {
            $crawler = $client->request('GET', '/listen/'.$list->getId());
            $client->submit($crawler->selectButton('Auf die Liste')->form(), ['shopping_item[name]' => $item]);
        }

        $rows = $this->entityManager()->getConnection()->fetchAllAssociative('SELECT user_id, title, body, link FROM notification');
        self::assertSame([['user_id' => $sister->getId(), 'title' => 'Neu auf deiner Liste „Besorgt die Familie“', 'body' => 'Batterien, Glühbirne', 'link' => '/listen/'.$list->getId()]], array_map(static fn (array $row): array => [...$row, 'user_id' => (int) $row['user_id']], $rows));
    }

    public function testPrintViewShowsTheOpenEntriesWithBoxes(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $list = $this->list('Einkauf');
        $this->item($list, 'Milch', $anna, '2 Liter');
        $this->item($list, 'Brot', $anna)->check(new \DateTimeImmutable(self::NOW));
        $this->entityManager()->flush();
        $this->pairTablet($client);

        $crawler = $client->request('GET', '/listen/'.$list->getId().'/drucken');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Einkauf');
        self::assertSelectorTextContains('.hc-print-sheet__head', 'Stand: Mittwoch, 4. November 2026');
        self::assertSame(['Milch – 2 Liter'], $crawler->filter('.hc-print-checklist li')->each(static fn ($row): string => trim($row->text())));
        self::assertCount(1, $crawler->filter('.hc-print-checklist__box'));
        self::assertSelectorExists('.hc-no-print [data-action="print#print"]');
    }

    public function testSwitchedOffListsAreGone(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $list = $this->list('Baumarkt');
        $list->setActive(false);
        $this->entityManager()->flush();
        $this->pairTablet($client);

        $client->request('GET', '/listen');
        self::assertSelectorNotExists('.hc-task-list');
        $client->request('GET', '/listen/'.$list->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testListsAreKeptInTheAdministrationByAdminsOnly(): void
    {
        $client = $this->startClient();
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');

        $client->loginUser($papa);
        $client->request('GET', '/verwaltung/listen');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/verwaltung/listen/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Anlegen')->form(), [
            'ShoppingList[name]' => 'Besorgt die Familie',
            'ShoppingList[assigneeUser]' => (string) $papa->getId(),
            'ShoppingList[assigneeRole]' => 'assistenz',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Wähle eine Person oder eine Rolle, nicht beides.');

        $client->submit($crawler->selectButton('Anlegen')->form(), ['ShoppingList[name]' => 'Besorgt die Familie', 'ShoppingList[assigneeUser]' => (string) $papa->getId()]);
        self::assertResponseRedirects();
        self::assertSame($papa->getId(), (int) $this->entityManager()->getConnection()->fetchOne('SELECT assignee_user_id FROM shopping_list'));
        $client->request('GET', '/verwaltung/listen');
        self::assertSelectorNotExists('.action-delete');
    }

    /**
     * A person – or with null the team tablet (paired once per test).
     */
    private function switchTo(KernelBrowser $client, ?AppUser $person): void
    {
        $client->getCookieJar()->clear();
        if (null !== $person) {
            $client->loginUser($person);

            return;
        }
        $device = $this->entityManager()->getRepository(Device::class)->findOneBy(['tokenHash' => DeviceCookie::hash(self::TABLET_TOKEN)]);
        if (null === $device) {
            $this->pairTablet($client);

            return;
        }
        $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, self::TABLET_TOKEN, null, '/', 'localhost'));
    }

    private function list(string $name, AppUser|AssigneeRole|null $assignee = null): ShoppingList
    {
        $list = new ShoppingList($name);
        $list->setAssigneeUser($assignee instanceof AppUser ? $assignee : null);
        $list->setAssigneeRole($assignee instanceof AssigneeRole ? $assignee : null);
        $this->entityManager()->persist($list);
        $this->entityManager()->flush();

        return $list;
    }

    private function item(ShoppingList $list, string $name, AppUser|Device $by, ?string $quantity = null): ShoppingItem
    {
        $item = new ShoppingItem($list, $name, $by);
        $item->describe($name, $quantity, null, null);
        $this->entityManager()->persist($item);
        $this->entityManager()->flush();

        return $item;
    }

    private function favorite(string $name, ?ShoppingList $list, ?string $link = null): ShoppingFavorite
    {
        $favorite = new ShoppingFavorite($name);
        $favorite->setList($list);
        $favorite->setLink($link);
        $favorite->setDefaultQuantity(null === $link ? null : '2 Stück');
        $this->entityManager()->persist($favorite);
        $this->entityManager()->flush();

        return $favorite;
    }
}
