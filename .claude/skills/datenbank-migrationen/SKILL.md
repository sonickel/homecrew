---
name: datenbank-migrationen
description: Wie Doctrine-Entities und Migrationen in HomeCrew aus dem Referenzschema docs/datenbank/schema.sql entstehen (MariaDB, CHECK-Constraints, generierte Spalten, Serien). Verwenden bei jeder Änderung an Entities, Tabellen oder Migrationen.
---

# Datenbank & Migrationen

## Referenz
- `docs/datenbank/schema.sql` ist die **fachliche Referenz** (78 Tabellen, Namen, Typen, Fremdschlüssel, CHECKs). `docs/datenbank/smoke_test.sql` prüft die Regeln (92 Negativtests + Positiv- und Löschfälle).
- Erster Schritt im Projekt: Referenzschema + Smoke-Test gegen eine **leere** lokale Datenbank laufen lassen (`homecrew_smoke`), Ergebnis melden. Fehler zuerst im Referenzschema beheben (und in `docs/konzept/konzept-6-datenbank.md` vermerken).

## Vorgehen je Meilenstein
1. Nur die Tabellen anlegen, die der Meilenstein braucht (plus referenzierte Tabellen).
2. Entities exakt nach Referenz mappen: Tabellen-/Spaltennamen, Längen, `nullable`, Defaults, Enum-Werte, Indizes (`#[ORM\Index]`, `#[ORM\UniqueConstraint]`), `onDelete` an den Joins.
3. `php bin/console doctrine:migrations:diff` erzeugen und **von Hand prüfen**:
   - CHECK-Constraints aus der Referenz ergänzen (Doctrine erzeugt sie nicht).
   - Generierte Spalten (z. B. `app_user.manager_marker`, `time_entry.open_marker`) aus der Referenz übernehmen; in der Entity nur lesend (`insertable: false, updatable: false`) bzw. gar nicht mappen.
   - Bei FK-Indizes mit generierter Spalte: erst ein normaler Index auf die FK-Spalte, dann der Unique-Index (MariaDB-Einschränkung).
   - **Nie** `DROP TABLE`/`DROP COLUMN` für noch nicht gemappte Tabellen/Spalten akzeptieren – entfernen.
   - Wiederkehrende Scheinänderungen (Diff will dieselbe Spalte immer wieder ändern) beheben statt ignorieren; notfalls `schema_filter` bzw. Spaltendefinition angleichen.
4. Migration auf der Entwicklungs-DB ausführen und auf einer leeren DB von Null an (`doctrine:migrations:migrate` komplett) testen.
5. Abgleich mit der Referenz für die betroffenen Tabellen (Spalten, Typen, Constraints) – z. B. mit einem kleinen Vergleichsskript über `information_schema`, das im Projekt unter `bin/` liegt.
6. Passende Regeln aus `smoke_test.sql` als PHPUnit-Tests nachbilden, sobald die Tabelle per Doctrine existiert (Regel verletzt → Exception).

## Regeln
- Niemals `doctrine:schema:update --force`.
- Zeitpunkte als `DATETIME` in UTC (Verbindung mit Zeitzone `+00:00`), Geplantes (Dienstplan, Termine, Fälligkeiten) als Ortszeit.
- Keine CHECKs auf Spalten mit `ON DELETE SET NULL` (Konsistenz dort in der App prüfen).
- Optionale Spalten in CHECKs immer mit `IS [NOT] NULL` formulieren (NULL gilt sonst als „bestanden").
- Wertelisten als `VARCHAR` + CHECK, in PHP als Backed Enum mit identischen Werten.
- Serien (Wartungspläne, Dienstplan, Termin-/Besuchsserien): Vorkommen mit Ursprungstag, eindeutig je Vorlage und Tag, `is_modified` schützt einzeln geänderte Vorkommen.
- `*_enc`-Spalten: libsodium-Chiffrat; Hauptschlüssel als Symfony-Secret, nie in der DB oder im Repository.
- Änderung am fachlichen Schema → `schema.sql` und `smoke_test.sql` mitziehen (Referenz bleibt aktuell).

## Lokale Datenbanken
- `homecrew` (Entwicklung), `homecrew_test` (PHPUnit), `homecrew_smoke` (Referenztest) – ein DB-Benutzer mit Rechten auf `homecrew%`.
- `DATABASE_URL` in `.env.local` mit korrekter `serverVersion` (z. B. `11.8.3-MariaDB`), `charset=utf8mb4`.
