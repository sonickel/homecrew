<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Enum\CreatedVia;
use App\Enum\HandoverNoteType;
use App\Enum\NotificationKind;
use App\Enum\ReportFollowUp;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Enum\ReportVisibility;
use App\Enum\ShoppingSource;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

/**
 * Rules of shopping_item, shopping_favorite, issue_report, notification and handover_note from
 * docs/datenbank/smoke_test.sql (B22, B42, B61–B69, B95, B96, C5c, C8) and the remaining constraints of schema.sql.
 */
final class ListReportRulesTest extends DatabaseTestCase
{
    private int $annaId;
    private int $listId;
    private int $taskId;

    protected function setUp(): void
    {
        $this->annaId = $this->insertUser('assistenz', 'anna');
        $this->connection()->insert('shopping_list', ['name' => 'Besorgt die Familie']);
        $this->listId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('task', ['title' => 'Seifenspender auffüllen', 'category' => 'haushalt', 'due_date' => '2026-11-05', 'status' => 'nicht_moeglich']);
        $this->taskId = (int) $this->connection()->lastInsertId();
        $this->insertReport(['kind' => 'nicht_moeglich', 'title' => 'Seife leer', 'task_id' => $this->taskId, 'created_by_user_id' => $this->annaId]);
    }

    public function testValidRowsGetTheirDefaults(): void
    {
        $this->connection()->insert('shopping_item', ['list_id' => $this->listId, 'name' => 'Flüssigseife', 'link' => 'https://shop.example/seife']);
        $item = $this->connection()->fetchAssociative('SELECT * FROM shopping_item WHERE id = ?', [(int) $this->connection()->lastInsertId()]);
        self::assertIsArray($item);
        self::assertSame(['manuell', 0], [$item['source'], (int) $item['is_done']]);

        $report = $this->connection()->fetchAssociative('SELECT * FROM issue_report WHERE task_id = ?', [$this->taskId]);
        self::assertIsArray($report);
        self::assertSame(['alle', 'app', 'offen', 0, 0], [$report['visibility'], $report['created_via'], $report['status'], (int) $report['confirm_count'], (int) $report['is_urgent']]);
        self::assertNull($report['follow_up']);
        self::assertNull($report['delete_after']);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function rejectedChanges(): iterable
    {
        $report = "INSERT INTO issue_report (public_id, kind, title%s) VALUES (UNHEX(REPLACE(UUID(),'-','')), %s)";
        yield 'B22 Einkauf erledigt ohne Zeitpunkt' => ["INSERT INTO shopping_item (list_id, name, is_done) VALUES (:list, 'Milch', 1)", self::CHECK_VIOLATED, 'ck_shopping_done'];
        yield 'B42 Benachrichtigung mit unbekannter Art' => ["INSERT INTO notification (user_id, kind, title, delete_after) VALUES (:anna, 'werbung', 'x', '2026-12-01')", self::CHECK_VIOLATED, 'ck_notification_kind'];
        yield 'B61 Meldung mit unbekannter Art' => [\sprintf($report, '', "'dreckig', 'x'"), self::CHECK_VIOLATED, 'ck_report_kind'];
        yield 'B62 Meldung mit unbekanntem Status' => [\sprintf($report, ', status', "'leer', 'x', 'wartend'"), self::CHECK_VIOLATED, 'ck_report_status'];
        yield 'B63 Erledigte Meldung ohne Abschlusszeit' => [\sprintf($report, ', status', "'leer', 'x', 'erledigt'"), self::CHECK_VIOLATED, 'ck_report_closed'];
        yield 'B64 Offene Meldung mit Abschlusszeit' => [\sprintf($report, ', status, closed_at, delete_after', "'leer', 'x', 'offen', NOW(), '2027-11-01'"), self::CHECK_VIOLATED, 'ck_report_closed'];
        yield 'B65 Verworfene Meldung ohne Löschdatum' => [\sprintf($report, ', status, closed_at', "'leer', 'x', 'verworfen', NOW()"), self::CHECK_VIOLATED, 'ck_report_closed'];
        yield 'B66 Meldung mit unbekannter Folgeaktion' => [\sprintf($report, ', follow_up', "'leer', 'x', 'mail'"), self::CHECK_VIOLATED, 'ck_report_follow'];
        yield 'B67 Zweite Meldung zur selben Aufgabe' => [\sprintf($report, ', task_id', "'nicht_moeglich', 'x', :task"), self::DUPLICATE, 'uq_report_task'];
        yield 'B68 „Auch bemerkt“ ohne Zeitpunkt' => [\sprintf($report, ', confirm_count', "'leer', 'x', 2"), self::CHECK_VIOLATED, 'ck_report_confirm'];
        yield 'B69 Meldung mit unbekannter Sichtbarkeit' => [\sprintf($report, ', visibility', "'leer', 'x', 'privat'"), self::CHECK_VIOLATED, 'ck_report_visible'];
        yield 'B95 Listeneintrag mit Link ohne https' => ["INSERT INTO shopping_item (list_id, name, link) VALUES (:list, 'x', 'http://shop.example/x')", self::CHECK_VIOLATED, 'ck_shopping_link'];
        yield 'B96 Lieblingsartikel mit Link ohne https' => ["INSERT INTO shopping_favorite (name, link) VALUES ('x', 'javascript:alert(1)')", self::CHECK_VIOLATED, 'ck_favorite_link'];
        // Further rules of schema.sql that the smoke test does not cover
        yield 'Listeneintrag: Zeitpunkt ohne Haken' => ["INSERT INTO shopping_item (list_id, name, done_at) VALUES (:list, 'x', NOW())", self::CHECK_VIOLATED, 'ck_shopping_done'];
        yield 'Listeneintrag: unbekannte Herkunft' => ["INSERT INTO shopping_item (list_id, name, source) VALUES (:list, 'x', 'mail')", self::CHECK_VIOLATED, 'ck_shopping_source'];
        yield 'Meldung: unbekannte Herkunft' => [\sprintf($report, ', created_via', "'leer', 'x', 'mail'"), self::CHECK_VIOLATED, 'ck_report_via'];
        yield 'Meldung: „auch bemerkt“-Zeitpunkt ohne Zähler' => [\sprintf($report, ', last_confirmed_at', "'leer', 'x', NOW()"), self::CHECK_VIOLATED, 'ck_report_confirm'];
        yield 'Übergabe: unbekannte Art' => ["INSERT INTO handover_note (body, note_type, delete_after) VALUES ('x', 'notiz', '2026-12-01')", self::CHECK_VIOLATED, 'ck_handover_type'];
        yield 'Übergabe: unbekannte Herkunft' => ["INSERT INTO handover_note (body, created_via, delete_after) VALUES ('x', 'mail', '2026-12-01')", self::CHECK_VIOLATED, 'ck_handover_via'];
    }

    #[DataProvider('rejectedChanges')]
    public function testRuleIsEnforcedByTheDatabase(string $sql, int $expectedErrorNumber, string $constraint): void
    {
        $params = array_filter(
            ['list' => $this->listId, 'task' => $this->taskId, 'anna' => $this->annaId],
            static fn (string $name): bool => str_contains($sql, ':'.$name),
            \ARRAY_FILTER_USE_KEY,
        );

        $this->assertRejected($sql, $expectedErrorNumber, $constraint, $params);
    }

    /**
     * Lesson from M4: every value of a value list must fit into its column.
     */
    public function testEveryValueFitsIntoItsColumn(): void
    {
        foreach (ShoppingSource::cases() as $source) {
            $this->connection()->insert('shopping_item', ['list_id' => $this->listId, 'name' => 'x', 'source' => $source->value]);
        }
        $columns = [
            'kind' => ReportKind::cases(),
            'visibility' => ReportVisibility::cases(),
            'created_via' => CreatedVia::cases(),
            'follow_up' => ReportFollowUp::cases(),
        ];
        foreach ($columns as $column => $cases) {
            foreach ($cases as $case) {
                $this->insertReport([$column => $case->value]);
            }
        }
        foreach (ReportStatus::cases() as $status) {
            $closed = $status->isOpen() ? [] : ['closed_at' => '2026-11-05 10:00:00', 'delete_after' => '2027-11-05'];
            $this->insertReport(['status' => $status->value, ...$closed]);
        }
        foreach (NotificationKind::cases() as $kind) {
            $this->connection()->insert('notification', ['user_id' => $this->annaId, 'kind' => $kind->value, 'title' => 'x', 'delete_after' => '2026-12-05']);
        }
        foreach (HandoverNoteType::cases() as $type) {
            $this->connection()->insert('handover_note', ['body' => 'x', 'note_type' => $type->value, 'delete_after' => '2026-12-05']);
        }

        self::assertSame(\count(NotificationKind::cases()), (int) $this->connection()->fetchOne('SELECT COUNT(DISTINCT kind) FROM notification'));
    }

    public function testC5cReportStaysWhenItsPersonIsDeleted(): void
    {
        $this->connection()->delete('app_user', ['id' => $this->annaId]);

        self::assertNull($this->connection()->fetchOne('SELECT created_by_user_id FROM issue_report WHERE task_id = ?', [$this->taskId]));
    }

    public function testC8ReportAndListEntryStayWhenTheirTaskIsDeleted(): void
    {
        $this->connection()->insert('shopping_item', ['list_id' => $this->listId, 'name' => 'Flüssigseife', 'source' => 'meldung', 'source_task_id' => $this->taskId]);
        $itemId = (int) $this->connection()->lastInsertId();
        $reportId = (int) $this->connection()->fetchOne('SELECT id FROM issue_report WHERE task_id = ?', [$this->taskId]);

        $this->connection()->delete('task', ['id' => $this->taskId]);

        self::assertNull($this->connection()->fetchOne('SELECT task_id FROM issue_report WHERE id = ?', [$reportId]));
        self::assertNull($this->connection()->fetchOne('SELECT source_task_id FROM shopping_item WHERE id = ?', [$itemId]));
    }

    public function testDeletingAListDeletesItsEntriesButKeepsReportsAndFavourites(): void
    {
        $this->connection()->insert('shopping_item', ['list_id' => $this->listId, 'name' => 'Flüssigseife']);
        $itemId = (int) $this->connection()->lastInsertId();
        $this->connection()->update('issue_report', ['follow_up' => 'liste', 'follow_up_shopping_item_id' => $itemId, 'status' => 'in_arbeit'], ['task_id' => $this->taskId]);
        $this->connection()->insert('shopping_favorite', ['list_id' => $this->listId, 'name' => 'Flüssigseife']);

        $this->connection()->delete('shopping_list', ['id' => $this->listId]);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM shopping_item'));
        self::assertNull($this->connection()->fetchOne('SELECT follow_up_shopping_item_id FROM issue_report WHERE task_id = ?', [$this->taskId]));
        self::assertNull($this->connection()->fetchOne('SELECT list_id FROM shopping_favorite'));
    }

    public function testDeletingAPersonDeletesTheirNotifications(): void
    {
        $this->connection()->insert('notification', ['user_id' => $this->annaId, 'kind' => 'meldung', 'title' => 'Neue Meldung', 'delete_after' => '2026-12-05']);

        $this->connection()->delete('app_user', ['id' => $this->annaId]);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM notification'));
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function insertReport(array $columns): void
    {
        $this->connection()->insert('issue_report', ['public_id' => Uuid::v7()->toBinary(), 'kind' => 'leer', 'title' => 'x', ...$columns]);
    }
}
