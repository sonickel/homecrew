<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Schema;

use App\Service\Schema\SqlScriptSplitter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlScriptSplitterTest extends TestCase
{
    public function testSplitsStatementsAndDropsComments(): void
    {
        $script = <<<'SQL'
            -- header; with a semicolon
            SET NAMES utf8mb4;

            CREATE TABLE a (
              id INT, -- trailing comment; still a comment
              /* block; comment */ name VARCHAR(10)
            );
            # hash comment;
            INSERT INTO a (id) VALUES (1)
            SQL;

        self::assertSame([
            'SET NAMES utf8mb4',
            "CREATE TABLE a (\n  id INT, \n    name VARCHAR(10)\n)",
            'INSERT INTO a (id) VALUES (1)',
        ], (new SqlScriptSplitter())->split($script));
    }

    public function testKeepsSemicolonsHashesAndDashesInsideQuotes(): void
    {
        $script = <<<'SQL'
            CREATE TABLE v (color CHAR(7), CONSTRAINT ck CHECK (color REGEXP '^#[0-9A-Fa-f]{6}$'));
            INSERT INTO v VALUES ('a;b'), ('it''s -- no comment'), ('back\'slash;'), ("dq;"), (`x;y`);
            SQL;

        self::assertSame([
            "CREATE TABLE v (color CHAR(7), CONSTRAINT ck CHECK (color REGEXP '^#[0-9A-Fa-f]{6}$'))",
            "INSERT INTO v VALUES ('a;b'), ('it''s -- no comment'), ('back\\'slash;'), (\"dq;\"), (`x;y`)",
        ], (new SqlScriptSplitter())->split($script));
    }

    public function testDoubleDashWithoutSpaceIsNoComment(): void
    {
        self::assertSame(['SELECT 1--1'], (new SqlScriptSplitter())->split('SELECT 1--1;'));
    }

    public function testBlockCommentSeparatesTokens(): void
    {
        self::assertSame(['SELECT 1'], (new SqlScriptSplitter())->split('SELECT/*x*/1;'));
    }

    public function testWindowsLineEndingsAndCommentAtEndOfFile(): void
    {
        self::assertSame(['SELECT 1', 'SELECT 2'], (new SqlScriptSplitter())->split("-- a\r\nSELECT 1;\r\nSELECT 2; -- b\r\n--"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedScripts(): iterable
    {
        yield 'String nicht geschlossen' => ["INSERT INTO a VALUES ('open;"];
        yield 'Blockkommentar nicht geschlossen' => ['SELECT 1 /* open;'];
        yield 'ausführbarer Kommentar' => ['/*!40101 SET NAMES utf8mb4 */;'];
        yield 'ausführbarer MariaDB-Kommentar' => ['/*M!100616 SET NOTE_VERBOSITY=0 */;'];
    }

    #[DataProvider('unsupportedScripts')]
    public function testUnsupportedScriptIsReported(string $script): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SqlScriptSplitter())->split($script);
    }
}
