<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Enum\EmergencyCategory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rules of emergency_contact and poi from docs/datenbank/smoke_test.sql (B19, B55)
 * and the remaining constraints and delete rules of schema.sql.
 */
final class EmergencyPoiRulesTest extends DatabaseTestCase
{
    private int $poiId;

    protected function setUp(): void
    {
        $this->connection()->executeStatement(
            "INSERT INTO poi (public_id, name, category, city, latitude, longitude, a11y_step_free) VALUES (UNHEX(REPLACE(UUID(),'-','')), 'Hausarztpraxis', 'arzt', 'Würzburg', 49.79, 9.95, 'ja')",
        );
        $this->poiId = (int) $this->connection()->lastInsertId();
    }

    public function testValidRowsGetTheirDefaults(): void
    {
        $this->connection()->insert('emergency_contact', ['name' => 'Schwester', 'phone' => '+49 931 0000000']);

        $contact = $this->connection()->fetchAssociative('SELECT category, visibility, show_on_lockscreen, sort FROM emergency_contact');
        self::assertIsArray($contact);
        self::assertSame('angehoerige', $contact['category']);
        self::assertSame('alle', $contact['visibility']);
        self::assertSame(1, (int) $contact['show_on_lockscreen']);
        self::assertSame(0, (int) $contact['sort']);
        self::assertNotNull($this->connection()->fetchOne('SELECT updated_at FROM poi WHERE id = ?', [$this->poiId]));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function rejectedChanges(): iterable
    {
        yield 'B19 Ort: Breitengrad ohne Längengrad' => ["INSERT INTO poi (public_id, name, category, latitude) VALUES (UNHEX(REPLACE(UUID(),'-','')), 'x', 'arzt', 49.79)", self::CHECK_VIOLATED, 'ck_poi_coords'];
        yield 'B55 Notfallkontakt mit unbekannter Kategorie' => ["INSERT INTO emergency_contact (category, name, phone) VALUES ('nachbar', 'x', '1')", self::CHECK_VIOLATED, 'ck_emergency_category'];
        // Further rules of schema.sql that the smoke test does not cover
        yield 'Ort: Breitengrad außerhalb der Erde' => ['UPDATE poi SET latitude = 91, longitude = 9.95 WHERE id = :poi', self::CHECK_VIOLATED, 'ck_poi_coords'];
        yield 'Ort: Längengrad außerhalb der Erde' => ['UPDATE poi SET latitude = 49.79, longitude = -181 WHERE id = :poi', self::CHECK_VIOLATED, 'ck_poi_coords'];
        yield 'Ort: unbekannte Angabe zu stufenlos' => ["UPDATE poi SET a11y_step_free = 'vielleicht' WHERE id = :poi", self::CHECK_VIOLATED, 'ck_poi_stepfree'];
        yield 'Notfallkontakt mit unbekannter Sichtbarkeit' => ["INSERT INTO emergency_contact (name, phone, visibility) VALUES ('x', '1', 'privat')", self::CHECK_VIOLATED, 'ck_emergency_visible'];
    }

    #[DataProvider('rejectedChanges')]
    public function testRuleIsEnforcedByTheDatabase(string $sql, int $expectedErrorNumber, string $constraint): void
    {
        $this->assertRejected($sql, $expectedErrorNumber, $constraint, str_contains($sql, ':poi') ? ['poi' => $this->poiId] : []);
    }

    /**
     * The reference had VARCHAR(12) for a list containing "dienstleister" (13 characters) – fixed in M4.
     */
    public function testEveryCategoryFitsIntoTheColumn(): void
    {
        foreach (EmergencyCategory::cases() as $category) {
            $this->connection()->insert('emergency_contact', ['category' => $category->value, 'name' => $category->label(), 'phone' => '1']);
        }

        self::assertSame(\count(EmergencyCategory::cases()), (int) $this->connection()->fetchOne('SELECT COUNT(DISTINCT category) FROM emergency_contact'));
    }

    public function testContactStaysWhenItsPlaceIsDeleted(): void
    {
        $this->connection()->insert('emergency_contact', ['category' => 'arzt', 'name' => 'Hausarztpraxis', 'phone' => '0931 1111111', 'poi_id' => $this->poiId]);

        $this->connection()->delete('poi', ['id' => $this->poiId]);

        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM emergency_contact WHERE poi_id IS NULL'));
    }
}
