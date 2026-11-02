<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * "Verwaltung" (konzept-11): only for admins, with second factor when ADMIN_2FA_REQUIRED=1 (.env.test).
 */
final class AdminAreaTest extends WebTestCase
{
    use PeopleTrait;

    protected function tearDown(): void
    {
        $this->restoreTwoFactorSwitch();
        parent::tearDown();
    }

    public function testAnonymousVisitorsAreSentToTheLogin(): void
    {
        $client = $this->startClient();

        foreach (['/verwaltung', '/verwaltung/einstellungen/allgemein/1/edit', '/verwaltung/protokoll'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('http://localhost/anmelden', 302, $path);
        }
    }

    public function testPersonsWithoutAdminRightAreForbidden(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        foreach (['/verwaltung', '/verwaltung/einstellungen/allgemein/1/edit', '/verwaltung/protokoll'] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path);
        }
        $client->request('GET', '/');
        self::assertSelectorNotExists('a[href="/verwaltung"]');
    }

    public function testAssistantsAreForbidden(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));

        $client->request('GET', '/verwaltung');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminWithoutSecondFactorIsSentToTheSetup(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'schwester', admin: true));

        $client->request('GET', '/verwaltung');
        self::assertResponseRedirects('/konto/zwei-faktor', 303);
    }

    public function testAdminWithoutSecondFactorIsAllowedWhileTheSwitchIsOff(): void
    {
        $this->requireTwoFactorForAdmins(false);
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'schwester', admin: true));

        $client->request('GET', '/verwaltung');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/');
        self::assertSelectorExists('a.hc-topbar__link[href="/verwaltung"][data-turbo="false"]');
    }

    public function testDashboardShowsOnlyExistingMasks(): void
    {
        $client = $this->loggedInAdmin();

        $crawler = $client->request('GET', '/verwaltung');

        self::assertResponseIsSuccessful();
        $menu = $crawler->filter('#main-menu')->text();
        foreach (['Allgemein', 'Löschfristen', 'Protokoll'] as $entry) {
            self::assertStringContainsString($entry, $menu);
        }
        foreach (['Personen', 'Geräte', 'Erweiterungen'] as $notYet) {
            self::assertStringNotContainsString($notYet, $menu);
        }
        // Strict CSP also in the administration: EasyAdmin's scripts carry the nonce
        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringNotContainsString('unsafe-inline', $csp);
        $crawler->filter('script')->each(static function ($script) use ($csp): void {
            self::assertStringContainsString("'nonce-".$script->attr('nonce')."'", $csp);
        });
    }

    public function testSavedGeneralSettingsAreLoggedWithOldAndNewValue(): void
    {
        $client = $this->loggedInAdmin();

        $crawler = $client->request('GET', '/verwaltung/einstellungen/allgemein/1/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Einstellungen: Allgemein');
        $form = $crawler->selectButton('Speichern')->form();
        $form['AppSetting[householdName]'] = 'Haus am See';
        $form['AppSetting[tabletLockMinutes]'] = '5';
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.alert', 'Die Einstellungen sind gespeichert.');

        $settings = $this->entityManager()->getConnection()->fetchAssociative('SELECT household_name, tablet_lock_seconds FROM app_setting');
        self::assertSame(['household_name' => 'Haus am See', 'tablet_lock_seconds' => 300], array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int) $v : $v, (array) $settings));

        $details = json_decode((string) $this->entityManager()->getConnection()->fetchOne('SELECT details FROM audit_log WHERE action = ?', ['einstellungen.aendern']), true);
        self::assertSame([
            'household_name' => ['alt' => 'Mein Haushalt', 'neu' => 'Haus am See'],
            'tablet_lock_seconds' => ['alt' => 180, 'neu' => 300],
        ], $details['felder']);

        // The new name is used at once
        $client->request('GET', '/');
        self::assertSelectorTextSame('title', 'Heute – Haus am See');
    }

    public function testInvalidSettingsAreRejectedWithAMessage(): void
    {
        $client = $this->loggedInAdmin();

        $crawler = $client->request('GET', '/verwaltung/einstellungen/allgemein/1/edit');
        $form = $crawler->selectButton('Speichern')->form();
        $form['AppSetting[timeViewMonths]'] = '30';
        $form['AppSetting[householdName]'] = '';
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Bitte wähle zwischen 1 und 24 Monaten.');
        self::assertSelectorTextContains('body', 'Bitte gib einen Namen für den Haushalt ein.');
        self::assertSame(3, (int) $this->entityManager()->getConnection()->fetchOne('SELECT time_view_months FROM app_setting'));
    }

    public function testRetentionMaskShowsOnlyStageOnePeriods(): void
    {
        $client = $this->loggedInAdmin();

        $crawler = $client->request('GET', '/verwaltung/einstellungen/loeschfristen/1/edit');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="AppSetting[retentionAuditDays]"]'));
        self::assertCount(0, $crawler->filter('input[name="AppSetting[retentionChatDays]"]'));
        self::assertCount(0, $crawler->filter('input[name="AppSetting[retentionAiDays]"]'));

        $form = $crawler->selectButton('Speichern')->form();
        $form['AppSetting[retentionAuditDays]'] = '10';
        $client->submit($form);
        self::assertSelectorTextContains('body', 'Bitte wähle zwischen 30 und 3650 Tagen.');
    }

    public function testSettingsCannotBeListedCreatedOrDeleted(): void
    {
        $client = $this->loggedInAdmin();

        $this->entityManager()->getConnection()->insert('audit_log', ['via' => 'system', 'action' => 'einrichtung.manager']);
        $entryId = (int) $this->entityManager()->getConnection()->lastInsertId();

        foreach (['/verwaltung/einstellungen/allgemein', '/verwaltung/einstellungen/allgemein/new', '/verwaltung/protokoll/new', '/verwaltung/protokoll/'.$entryId.'/edit'] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path);
        }
    }

    public function testLogListsEntriesNewestFirstWithReadableTexts(): void
    {
        $client = $this->loggedInAdmin();
        $connection = $this->entityManager()->getConnection();
        $connection->insert('audit_log', ['via' => 'system', 'action' => 'einrichtung.manager', 'created_at' => '2026-10-01 08:00:00']);
        $connection->insert('audit_log', ['via' => 'app', 'action' => 'passwort.aendern', 'actor_user_id' => $this->admin()->getId(), 'created_at' => '2026-10-02 08:00:00']);

        $crawler = $client->request('GET', '/verwaltung/protokoll');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('table tbody tr')->each(static fn ($row): string => $row->text());
        self::assertStringContainsString('Passwort geändert', $rows[0]);
        self::assertStringContainsString('Max', $rows[0]);
        self::assertStringContainsString('02.10.2026, 10:00', $rows[0]);
        self::assertStringContainsString('Manager eingerichtet', $rows[1]);
        self::assertStringContainsString('System (Server-Befehl)', $rows[1]);
    }

    public function testLogFiltersOfferPersonsByDisplayName(): void
    {
        $client = $this->loggedInAdmin();

        $client->request('GET', '/verwaltung/protokoll/render-filters');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Max');
    }

    public function testLogShowsNamesAndValuesAsTextNotAsHtml(): void
    {
        $client = $this->loggedInAdmin();
        $this->entityManager()->getConnection()->insert('audit_log', ['via' => 'app', 'action' => 'einstellungen.aendern', 'details' => json_encode(['felder' => ['household_name' => ['alt' => 'a', 'neu' => '<b id="boese">x</b>']]])]);

        $crawler = $client->request('GET', '/verwaltung/protokoll');

        self::assertCount(0, $crawler->filter('#boese'));
        self::assertStringContainsString('Name des Haushalts: a → <b id="boese">x</b>', $crawler->filter('table tbody')->text());
    }

    private ?AppUser $admin = null;

    private function loggedInAdmin(): KernelBrowser
    {
        $client = $this->startClient();
        $this->admin = $this->createPerson(UserRole::Manager, 'max');
        $this->enableAuthenticatorApp($this->admin);
        $this->giveRecoveryCodes($this->admin);
        $client->loginUser($this->admin);

        return $client;
    }

    private function admin(): AppUser
    {
        return $this->admin ?? throw new \LogicException('Log in first.');
    }
}
