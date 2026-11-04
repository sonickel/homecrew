<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\DueRule;
use App\Enum\TaskCategory;
use App\Enum\TaskStatus;
use App\Enum\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rules of task, task_completion, task_template, the checklists (task_template_item, task_item, task_item_result),
 * guide, asset, asset_component and shopping_list from docs/datenbank/smoke_test.sql
 * (B07, B08, B25, B27, B29, B53, B57, B94, C4, C5b, C11) and the remaining constraints and delete rules of schema.sql.
 */
final class TaskRulesTest extends DatabaseTestCase
{
    private int $annaId;
    private int $templateId;
    private int $taskId;

    protected function setUp(): void
    {
        $this->annaId = $this->insertUser('assistenz', 'anna');
        $this->connection()->executeStatement(
            "INSERT INTO task_template (title, category, recurrence_type, interval_days, starts_on) VALUES ('Reifendruck prüfen', 'hilfsmittel', 'intervall', 14, '2026-11-01')",
        );
        $this->templateId = (int) $this->connection()->lastInsertId();
        $this->connection()->executeStatement(
            "INSERT INTO task (template_id, occurrence_date, title, category, due_date) VALUES (:template, '2026-11-05', 'Reifendruck prüfen', 'hilfsmittel', '2026-11-05')",
            ['template' => $this->templateId],
        );
        $this->taskId = (int) $this->connection()->lastInsertId();
    }

    public function testValidRowsGetTheirDefaults(): void
    {
        $this->connection()->insert('task', ['title' => 'Blumen gießen', 'category' => 'haushalt', 'due_date' => '2026-11-05']);

        $task = $this->connection()->fetchAssociative('SELECT * FROM task WHERE id = ?', [(int) $this->connection()->lastInsertId()]);
        self::assertIsArray($task);
        self::assertSame(['normal', 'keiner', 'alle', 'datum', 'offen', 'app', 0, 0], [
            $task['priority'], $task['proof_type'], $task['visibility'], $task['due_rule'], $task['status'], $task['created_via'],
            (int) $task['requires_manager'], (int) $task['postponed_count'],
        ]);
        self::assertNull($task['assignee_user_id']);
        self::assertNull($task['assignee_role']);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function rejectedChanges(): iterable
    {
        yield 'B07 Kalender-Wiederholung ohne RRULE' => ["INSERT INTO task_template (title, category, recurrence_type, starts_on) VALUES ('x', 'haushalt', 'kalender', '2026-11-01')", self::CHECK_VIOLATED, 'ck_template_recur'];
        yield 'B08 Intervall-Wiederholung mit RRULE' => ["INSERT INTO task_template (title, category, recurrence_type, rrule, interval_days, starts_on) VALUES ('x', 'haushalt', 'intervall', 'FREQ=DAILY', 7, '2026-11-01')", self::CHECK_VIOLATED, 'ck_template_recur'];
        yield 'B25 Verschobene Aufgabe wird von der Vorlage nicht erneut erzeugt' => ["INSERT INTO task (template_id, occurrence_date, title, category, due_date) VALUES (:template, '2026-11-05', 'x', 'hilfsmittel', '2026-11-08')", self::DUPLICATE, 'uq_task_occurrence'];
        yield 'B27 Intervall-Wiederholung ohne Tageszahl' => ["INSERT INTO task_template (title, category, recurrence_type, starts_on) VALUES ('x', 'haushalt', 'intervall', '2026-11-01')", self::CHECK_VIOLATED, 'ck_template_recur'];
        yield 'B29 Unbekannte Rolle als Zuständigkeit' => ["INSERT INTO task (title, category, assignee_role, due_date) VALUES ('x', 'haushalt', 'chef', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_role'];
        yield 'B53 Aufgabe mit alter Sichtbarkeit „team“' => ["INSERT INTO task (title, category, visibility, due_date) VALUES ('x', 'haushalt', 'team', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_visible'];
        yield 'B57 Zweite Liste mit gleichem Namen' => ["INSERT INTO shopping_list (name) VALUES ('Einkauf'), ('Einkauf')", self::DUPLICATE, 'uq_shopping_list_name'];
        yield 'B94 Einmalige Aufgabe als „einzeln geändertes“ Vorkommen' => ["INSERT INTO task (title, category, due_date, is_modified) VALUES ('x', 'haushalt', '2026-11-05', 1)", self::CHECK_VIOLATED, 'ck_task_modified'];
        // Further rules of schema.sql that the smoke test does not cover
        yield 'Aufgabe: Zeitfenster beginnt nach dem Fälligkeitstag' => ["INSERT INTO task (title, category, window_start, due_date) VALUES ('x', 'haushalt', '2026-11-06', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_window'];
        yield 'Aufgabe: unbekannte Kategorie' => ["INSERT INTO task (title, category, due_date) VALUES ('x', 'garten', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_category'];
        yield 'Aufgabe: unbekannte Priorität' => ["INSERT INTO task (title, category, priority, due_date) VALUES ('x', 'haushalt', 'dringend', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_priority'];
        yield 'Aufgabe: unbekannter Nachweis' => ["INSERT INTO task (title, category, proof_type, due_date) VALUES ('x', 'haushalt', 'video', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_proof'];
        yield 'Aufgabe: unbekannte Fälligkeitsregel' => ["INSERT INTO task (title, category, due_rule, due_date) VALUES ('x', 'haushalt', 'irgendwann', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_duerule'];
        yield 'Aufgabe: Status „verschoben“ gibt es nicht' => ["INSERT INTO task (title, category, status, due_date) VALUES ('x', 'haushalt', 'verschoben', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_status'];
        yield 'Aufgabe: unbekannte Herkunft' => ["INSERT INTO task (title, category, created_via, due_date) VALUES ('x', 'haushalt', 'mail', '2026-11-05')", self::CHECK_VIOLATED, 'ck_task_via'];
        yield 'Erledigung: zweimal dieselbe Aufgabe' => ["INSERT INTO task_completion (task_id, action, done_at) VALUES (:task, 'erledigt', NOW()), (:task, 'uebersprungen', NOW())", self::DUPLICATE, 'uq_completion_task'];
        yield 'Erledigung: „verschoben“ ist kein Abschluss' => ["INSERT INTO task_completion (task_id, action, done_at) VALUES (:task, 'verschoben', NOW())", self::CHECK_VIOLATED, 'ck_completion_action'];
        yield 'Plan: frühestens nach mehr Tagen als das Intervall' => ["INSERT INTO task_template (title, category, recurrence_type, interval_days, earliest_after_days, starts_on) VALUES ('x', 'haushalt', 'intervall', 7, 10, '2026-11-01')", self::CHECK_VIOLATED, 'ck_template_earliest'];
        yield 'Plan: endet vor dem Beginn' => ["INSERT INTO task_template (title, category, recurrence_type, interval_days, starts_on, ends_on) VALUES ('x', 'haushalt', 'intervall', 7, '2026-11-01', '2026-10-01')", self::CHECK_VIOLATED, 'ck_template_dates'];
        yield 'Plan: Pause endet vor ihrem Beginn' => ["INSERT INTO task_template (title, category, recurrence_type, interval_days, starts_on, paused_from, paused_until) VALUES ('x', 'haushalt', 'intervall', 7, '2026-11-01', '2026-12-10', '2026-12-01')", self::CHECK_VIOLATED, 'ck_template_pause'];
        yield 'Plan: Pausenende ohne Beginn' => ["INSERT INTO task_template (title, category, recurrence_type, interval_days, starts_on, paused_until) VALUES ('x', 'haushalt', 'intervall', 7, '2026-11-01', '2026-12-01')", self::CHECK_VIOLATED, 'ck_template_pause'];
        yield 'Plan: unbekannte Sichtbarkeit' => ["INSERT INTO task_template (title, category, visibility, recurrence_type, interval_days, starts_on) VALUES ('x', 'haushalt', 'team', 'intervall', 7, '2026-11-01')", self::CHECK_VIOLATED, 'ck_template_visible'];
        yield 'Anleitung: unbekannter Typ' => ["INSERT INTO guide (public_id, guide_type) VALUES (UNHEX(REPLACE(UUID(),'-','')), 'video')", self::CHECK_VIOLATED, 'ck_guide_type'];
        yield 'Hilfsmittel: unbekannter Status' => ["INSERT INTO asset (public_id, name, status) VALUES (UNHEX(REPLACE(UUID(),'-','')), 'Rollstuhl', 'verloren')", self::CHECK_VIOLATED, 'ck_asset_status'];
        yield 'Liste: unbekannte Rolle' => ["INSERT INTO shopping_list (name, assignee_role) VALUES ('Einkauf', 'chef')", self::CHECK_VIOLATED, 'ck_shopping_list_role'];
    }

    #[DataProvider('rejectedChanges')]
    public function testRuleIsEnforcedByTheDatabase(string $sql, int $expectedErrorNumber, string $constraint): void
    {
        $params = array_filter(
            ['template' => $this->templateId, 'task' => $this->taskId],
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
        $columns = [
            'category' => TaskCategory::cases(),
            'assignee_role' => AssigneeRole::cases(),
            'visibility' => Visibility::cases(),
            'due_rule' => DueRule::cases(),
            'status' => TaskStatus::cases(),
        ];
        foreach ($columns as $column => $cases) {
            foreach ($cases as $case) {
                $this->connection()->insert('task', ['title' => 'x', 'category' => 'haushalt', 'due_date' => '2026-11-05', $column => $case->value]);
            }
        }
        foreach (CompletionAction::cases() as $action) {
            $this->connection()->insert('task', ['title' => 'x', 'category' => 'haushalt', 'due_date' => '2026-11-05']);
            $this->connection()->insert('task_completion', ['task_id' => (int) $this->connection()->lastInsertId(), 'action' => $action->value, 'done_at' => '2026-11-05 10:00:00']);
        }

        self::assertSame(\count(CompletionAction::cases()), (int) $this->connection()->fetchOne('SELECT COUNT(DISTINCT action) FROM task_completion'));
    }

    public function testDeletingATaskDeletesItsCompletion(): void
    {
        $this->connection()->insert('task_completion', ['task_id' => $this->taskId, 'action' => 'erledigt', 'user_id' => $this->annaId, 'done_at' => '2026-11-05 10:00:00']);

        $this->connection()->delete('task', ['id' => $this->taskId]);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM task_completion'));
    }

    public function testC4TaskAndPlanStayWhenTheirGuideIsDeleted(): void
    {
        $this->connection()->executeStatement("INSERT INTO guide (public_id, guide_type) VALUES (UNHEX(REPLACE(UUID(),'-','')), 'anleitung')");
        $guideId = (int) $this->connection()->lastInsertId();
        $this->connection()->update('task', ['guide_id' => $guideId], ['id' => $this->taskId]);
        $this->connection()->update('task_template', ['guide_id' => $guideId], ['id' => $this->templateId]);

        $this->connection()->delete('guide', ['id' => $guideId]);

        self::assertNull($this->connection()->fetchOne('SELECT guide_id FROM task WHERE id = ?', [$this->taskId]));
        self::assertNull($this->connection()->fetchOne('SELECT guide_id FROM task_template WHERE id = ?', [$this->templateId]));
    }

    public function testC5bTasksAndCompletionsStayWhenThePersonIsDeleted(): void
    {
        $this->connection()->update('task', ['assignee_user_id' => $this->annaId, 'assigned_by_user_id' => $this->annaId, 'created_by_user_id' => $this->annaId], ['id' => $this->taskId]);
        $this->connection()->insert('task_completion', ['task_id' => $this->taskId, 'action' => 'erledigt', 'user_id' => $this->annaId, 'done_at' => '2026-11-05 10:00:00']);

        $this->connection()->delete('app_user', ['id' => $this->annaId]);

        $task = $this->connection()->fetchAssociative('SELECT assignee_user_id, assigned_by_user_id, created_by_user_id FROM task WHERE id = ?', [$this->taskId]);
        self::assertSame(['assignee_user_id' => null, 'assigned_by_user_id' => null, 'created_by_user_id' => null], $task);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM task_completion WHERE user_id IS NULL'));
    }

    public function testC11CompletionAndHandOverStayWhenTheDeviceIsDeleted(): void
    {
        $this->connection()->insert('device', ['name' => 'Flur', 'token_hash' => str_repeat('a', 64)]);
        $deviceId = (int) $this->connection()->lastInsertId();
        $this->connection()->update('task', ['assignee_role' => 'admins', 'assigned_by_device_id' => $deviceId, 'assigned_at' => '2026-11-05 10:00:00'], ['id' => $this->taskId]);
        $this->connection()->insert('task_completion', ['task_id' => $this->taskId, 'action' => 'erledigt', 'device_id' => $deviceId, 'done_at' => '2026-11-05 11:00:00']);

        $this->connection()->delete('device', ['id' => $deviceId]);

        self::assertNull($this->connection()->fetchOne('SELECT assigned_by_device_id FROM task WHERE id = ?', [$this->taskId]));
        self::assertNotNull($this->connection()->fetchOne('SELECT assigned_at FROM task WHERE id = ?', [$this->taskId]));
        self::assertNull($this->connection()->fetchOne('SELECT device_id FROM task_completion WHERE task_id = ?', [$this->taskId]));
    }

    public function testTaskStaysWhenItsPlanIsDeleted(): void
    {
        $this->connection()->insert('task_template_item', ['template_id' => $this->templateId, 'label' => 'Reifen vorne links']);

        $this->connection()->delete('task_template', ['id' => $this->templateId]);

        self::assertNull($this->connection()->fetchOne('SELECT template_id FROM task WHERE id = ?', [$this->taskId]));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM task_template_item'));
    }

    public function testAnOccurrenceChangedOneByOneIsMarked(): void
    {
        $this->connection()->update('task', ['is_modified' => 1, 'due_date' => '2026-11-07'], ['id' => $this->taskId]);

        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT is_modified FROM task WHERE id = ?', [$this->taskId]));
    }

    public function testUndoingACompletionDeletesTheChecklistResultsButKeepsThePoints(): void
    {
        $this->connection()->insert('task_item', ['task_id' => $this->taskId, 'label' => 'Reifen vorne links']);
        $itemId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('task_completion', ['task_id' => $this->taskId, 'action' => 'erledigt', 'done_at' => '2026-11-05 10:00:00']);
        $completionId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('task_item_result', ['completion_id' => $completionId, 'task_item_id' => $itemId, 'is_checked' => 1]);

        $this->connection()->delete('task_completion', ['id' => $completionId]);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM task_item_result'));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM task_item'));

        $this->connection()->delete('task', ['id' => $this->taskId]);
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM task_item'));
    }

    public function testAPointHasOneResultPerCompletion(): void
    {
        $this->connection()->insert('task_item', ['task_id' => $this->taskId, 'label' => 'Reifen vorne links']);
        $itemId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('task_completion', ['task_id' => $this->taskId, 'action' => 'erledigt', 'done_at' => '2026-11-05 10:00:00']);

        $this->assertRejected(
            'INSERT INTO task_item_result (completion_id, task_item_id) VALUES (:completion, :item), (:completion, :item)',
            self::DUPLICATE,
            'uq_item_result',
            ['completion' => (int) $this->connection()->lastInsertId(), 'item' => $itemId],
        );
    }

    public function testChecklistPointsStayWhenThePartOfTheAidIsDeleted(): void
    {
        $this->connection()->executeStatement("INSERT INTO asset (public_id, name) VALUES (UNHEX(REPLACE(UUID(),'-','')), 'Rollstuhl')");
        $assetId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('asset_component', ['asset_id' => $assetId, 'name' => 'Reifen hinten links']);
        $componentId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('task_template_item', ['template_id' => $this->templateId, 'label' => 'Reifen hinten links', 'asset_component_id' => $componentId]);
        $this->connection()->insert('task_item', ['task_id' => $this->taskId, 'label' => 'Reifen hinten links', 'asset_component_id' => $componentId]);

        $this->connection()->delete('asset', ['id' => $assetId]);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM asset_component'));
        self::assertNull($this->connection()->fetchOne('SELECT asset_component_id FROM task_template_item'));
        self::assertNull($this->connection()->fetchOne('SELECT asset_component_id FROM task_item'));
    }
}
