<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\IssueReport;
use App\Entity\Notification;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Enum\DeviceMode;
use App\Enum\NotificationKind;
use App\Enum\ReportKind;
use App\Enum\ReportVisibility;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The bell and "Heute" (konzept-9 §5, konzept-2 §2): unread notifications for persons, read only when opened or
 * marked; "Neu seit gestern" at the team tablet, which has no bell; "Meldungen: n offen" only for admins.
 */
final class NotificationPagesTest extends WebTestCase
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

    public function testTheBellCountsAndOpeningMarksAsRead(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $first = $this->notification($admin, 'Neue Meldung: Seife leer', '/meldungen');
        $this->notification($admin, 'Dringende Meldung: Ladegerät', '/meldungen', urgent: true);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/');
        self::assertSelectorTextContains('.hc-topbar .hc-badge', '2 neu');
        self::assertCount(2, $crawler->filter('#neu + .hc-notifications li'));
        self::assertSelectorTextContains('#neu + .hc-notifications', 'Dringend');

        // Loading the list does not mark anything as read (Turbo loads links ahead)
        $crawler = $client->request('GET', '/benachrichtigungen');
        self::assertCount(2, $crawler->filter('.hc-notification--new'));

        $client->submit($crawler->filter('form[action="/benachrichtigungen/'.$first->getId().'/oeffnen"]')->form());
        self::assertResponseRedirects('/meldungen', 303);
        $client->request('GET', '/');
        self::assertSelectorTextContains('.hc-topbar .hc-badge', '1 neu');

        $crawler = $client->request('GET', '/');
        $client->submit($crawler->selectButton('Als gelesen markieren')->form());
        self::assertResponseRedirects('/', 303);
        $client->followRedirect();
        self::assertSelectorNotExists('.hc-topbar .hc-badge');
        self::assertSelectorNotExists('#neu');
    }

    /**
     * A PIN never gives admin rights (konzept-1 §5): an admin logged in by PIN at the tablet sees no notifications
     * about reports – they may belong to reports for admins only. Other notifications stay.
     */
    public function testAnAdminLoggedInByPinSeesNoReportNotifications(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $this->pairTablet($client, DeviceMode::Beides);
        $sister = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true);
        $this->givePin($sister, '582914');
        $report = $this->notification($sister, 'Dringende Meldung (nur für Admins)', '/meldungen', urgent: true);
        $this->notification($sister, 'Aufgabe für dich: Blumen gießen', '/aufgaben', kind: NotificationKind::Aufgabe);
        $this->loginWithPin($client, $sister, '582914');

        $crawler = $client->request('GET', '/benachrichtigungen');
        self::assertSame(['Aufgabe für dich: Blumen gießen'], $crawler->filter('.hc-notification__title')->each(static fn ($title): string => $title->text()));
        self::assertSelectorTextContains('.hc-topbar .hc-badge', '1 neu');
        $client->request('GET', '/');
        self::assertSelectorTextNotContains('#neu + .hc-notifications', 'Dringende Meldung');

        $token = $crawler->filter('form[action$="/oeffnen"] input[name="_token"]')->attr('value');
        $client->request('POST', '/benachrichtigungen/'.$report->getId().'/oeffnen', ['_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        // "Alle als gelesen markieren" leaves what it did not show
        $client->request('POST', '/benachrichtigungen/gelesen', ['_token' => $token]);
        self::assertNull($this->entityManager()->getConnection()->fetchOne('SELECT read_at FROM notification WHERE id = ?', [$report->getId()]));
    }

    public function testOthersNotificationsAndForeignLinksLeadNowhere(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $foreign = $this->notification($admin, 'Neue Meldung: Seife leer', '/meldungen');
        $outside = $this->notification($anna, 'Aufgabe für dich', '//evil.example/x');

        $client->loginUser($anna);
        $crawler = $client->request('GET', '/benachrichtigungen');
        self::assertCount(1, $crawler->filter('.hc-notification'));
        $token = $crawler->filter('form[action$="/oeffnen"] input[name="_token"]')->attr('value');

        $client->request('POST', '/benachrichtigungen/'.$foreign->getId().'/oeffnen', ['_token' => $token]);
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', '/benachrichtigungen/'.$outside->getId().'/oeffnen', ['_token' => $token]);
        self::assertResponseRedirects('/benachrichtigungen', 303);
    }

    public function testTheTeamTabletHasNoBellButSeesWhatIsNewSinceYesterday(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $this->report('Glühbirne Flur', $anna);
        $this->report('Ladegerät lädt nicht', $anna, ReportVisibility::Admins);
        $list = new ShoppingList('Einkauf');
        $this->entityManager()->persist($list);
        $this->entityManager()->persist(new ShoppingItem($list, 'Milch', $anna));
        $this->entityManager()->flush();
        $this->pairTablet($client);

        $client->request('GET', '/');
        self::assertSelectorNotExists('a[href="/benachrichtigungen"]');
        self::assertSelectorTextContains('#neu', 'Neu seit gestern');
        $news = $client->getCrawler()->filter('#neu + .hc-history')->text();
        self::assertStringContainsString('Gemeldet: Glühbirne Flur (Kaputt)', $news);
        self::assertStringContainsString('Einkauf: Milch', $news);
        self::assertStringNotContainsString('Ladegerät', $news);
        self::assertSelectorNotExists('.hc-today__reports');
        self::assertSelectorExists('a.hc-button--signal[href="/melden"]');
        self::assertSelectorExists('.hc-tile[href="/listen"]');
        self::assertSelectorExists('.hc-tile[href="/meldungen"]');
    }

    public function testOnlyAdminsSeeHowManyReportsAreOpen(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->report('Seife leer', $papa);
        $this->report('Ladegerät lädt nicht', $papa, ReportVisibility::Admins, urgent: true);

        $client->loginUser($papa);
        $client->request('GET', '/');
        self::assertSelectorNotExists('.hc-today__reports');

        $client->loginUser($admin);
        $client->request('GET', '/');
        self::assertSelectorTextContains('.hc-today__reports', 'Meldungen: 2 offen');
        self::assertSelectorTextContains('.hc-today__reports .hc-status--late', 'davon 1 dringend');
    }

    private function notification(AppUser $person, string $title, string $link, bool $urgent = false, NotificationKind $kind = NotificationKind::Meldung): Notification
    {
        $notification = new Notification($person, $kind, $title, $link, $urgent, new \DateTimeImmutable('2026-12-04'));
        $this->entityManager()->persist($notification);
        $this->entityManager()->flush();

        return $notification;
    }

    private function report(string $title, AppUser $by, ReportVisibility $visibility = ReportVisibility::Alle, bool $urgent = false): void
    {
        $report = new IssueReport(ReportKind::Kaputt, $title, $by);
        $report->describe(null, $urgent, $visibility);
        $this->entityManager()->persist($report);
        $this->entityManager()->flush();
    }
}
