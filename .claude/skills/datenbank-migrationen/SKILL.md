---
name: datenbank-migrationen
description: Wie Doctrine-Entities und Migrationen in HomeCrew aus dem Referenzschema docs/datenbank/schema.sql entstehen (MariaDB, CHECK-Constraints, generierte Spalten, Serien). Verwenden bei jeder Änderung an Entities, Tabellen oder Migrationen.
---

# Datenbank & Migrationen

## Referenz
- `docs/datenbank/schema.sql` ist die **fachliche Referenz** (78 Tabellen, Namen, Typen, Fremdschlüssel, CHECKs). `docs/datenbank/smoke_test.sql` prüft die Regeln (93 Negativtests + Positiv- und Löschfälle).
- Smoke-Test (Befehl in Konzept 6 §8) läuft auf MariaDB 11.8.6 grün. Fehler zuerst im Referenzschema beheben (und in `docs/konzept/konzept-6-datenbank.md` vermerken).
- **Strategie und Mapping-Tabelle: Konzept 6 §6** – vor jeder neuen Tabelle lesen.

## Vorgehen je Meilenstein
1. Nur die Tabellen anlegen, die der Meilenstein braucht (plus transitiv referenzierte Tabellen), immer vollständig.
2. Entities exakt nach Referenz mappen (Konzept 6 §6.2): `name:` an jeder Spalte, Reihenfolge wie im Schema, Längen, `nullable`, Defaults, Enum-Werte, Index-Namen (`#[ORM\Index]`, `#[ORM\UniqueConstraint]`), `onDelete` an den Joins; `TINYINT` ≠ `TINYINT(1)` → Typ `tinyint`; generierte Spalten und DB-Zeitstempel **nur lesend mappen, nie weglassen**.
3. `php bin/console doctrine:migrations:diff` als Hilfe erzeugen, dann die Migration schreiben: `CREATE TABLE` **wörtlich aus `schema.sql`** (ohne `--`-Kommentare), Startdaten dazu, `isTransactional(): false`.
   - Bei FK-Indizes mit generierter Spalte: erst ein normaler Index auf die FK-Spalte, dann der Unique-Index (MariaDB-Einschränkung).
   - **Nie** `DROP TABLE`/`DROP COLUMN` für noch nicht gemappte Tabellen/Spalten.
4. `doctrine:migrations:migrate`, dann Gegenprobe: `doctrine:schema:validate` „in sync" und ein zweites `doctrine:migrations:diff` → „No changes detected". Scheinänderungen beheben (Mapping angleichen), nicht ignorieren. Wird eine schon ausgeführte, noch nicht committete Migration geändert: in dev und test erst `doctrine:migrations:migrate prev -n [--env=test]`, dann neu migrieren – `composer qa` migriert die Test-DB nur vorwärts.
5. `composer schema:check` → alle migrierten Tabellen identisch zur Referenz (migriert `homecrew_check` von Null, lädt die Referenz neu in `homecrew_smoke`).
6. Passende Regeln aus `smoke_test.sql` als PHPUnit-Tests nachbilden (`tests/Database/`, Basisklasse `DatabaseTestCase`: Fehlercode 4025 = CHECK, 1062 = doppelt).

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
- `homecrew` (Entwicklung), `homecrew_test` (PHPUnit, `composer qa` migriert sie), `homecrew_smoke` (Referenz/Smoke-Test), `homecrew_check` (von Null migriert für `schema:check`) – App-Benutzer `homecrew` mit Rechten auf `homecrew%`; der WSL-Benutzer kommt per `unix_socket` an dieselben Datenbanken (`mariadb` ohne Passwort).
- `DATABASE_URL` in `.env.local` **und** `.env.test.local` (die Testumgebung liest `.env.local` nicht) mit `serverVersion=11.8.6-MariaDB`, `charset=utf8mb4`.
