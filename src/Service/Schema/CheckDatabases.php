<?php

declare(strict_types=1);

namespace App\Service\Schema;

/**
 * The two scratch databases of app:schema:check, derived from the app database in DATABASE_URL:
 * "<app>_smoke" holds the reference schema, "<app>_check" is migrated from zero.
 * Both are dropped on every run – no other database name can come out of this class.
 */
final readonly class CheckDatabases
{
    private const string URL_PATTERN = '~^([a-z0-9+]+://[^/?#]*/)([^?#]*)~i';

    public string $reference;
    public string $check;

    private function __construct(
        private string $databaseUrl,
        string $appDatabase,
    ) {
        $this->reference = $appDatabase.'_smoke';
        $this->check = $appDatabase.'_check';
    }

    public static function fromDatabaseUrl(string $databaseUrl): self
    {
        if (1 !== preg_match(self::URL_PATTERN, $databaseUrl, $match) || 1 !== preg_match('/^[a-z0-9_]+$/', $match[2])) {
            throw new \InvalidArgumentException('DATABASE_URL must name the app database (only a–z, 0–9 and _).');
        }

        return new self($databaseUrl, $match[2]);
    }

    /**
     * DATABASE_URL with the database replaced – only for the two scratch databases.
     */
    public function urlFor(string $database): string
    {
        if ($database !== $this->reference && $database !== $this->check) {
            throw new \InvalidArgumentException(\sprintf('"%s" is no scratch database of the schema check.', $database));
        }

        return (string) preg_replace(self::URL_PATTERN, '${1}'.$database, $this->databaseUrl, 1);
    }
}
