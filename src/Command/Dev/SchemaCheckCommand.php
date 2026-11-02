<?php

declare(strict_types=1);

namespace App\Command\Dev;

use App\Service\Schema\CheckDatabases;
use App\Service\Schema\SchemaComparator;
use App\Service\Schema\SchemaSnapshotReader;
use App\Service\Schema\SqlScriptSplitter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Process\Process;

/**
 * Migration strategy check (docs/konzept/konzept-6-datenbank.md §6): loads docs/datenbank/schema.sql into
 * an empty "<app>_smoke", migrates an empty "<app>_check" from zero and compares every migrated table.
 * Both databases are dropped and created again (see CheckDatabases – no other database can be hit).
 */
#[AsCommand(name: 'app:schema:check', description: 'Migriert eine leere Datenbank von Null und vergleicht sie mit dem Referenzschema')]
#[When(env: 'dev')]
final readonly class SchemaCheckCommand
{
    /** Tables outside the reference (konzept-6 §6). */
    private const array TECHNICAL_TABLES = ['doctrine_migration_versions', 'messenger_messages'];

    public function __construct(
        private Connection $connection,
        private SqlScriptSplitter $splitter,
        private SchemaSnapshotReader $snapshotReader,
        private SchemaComparator $comparator,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire(env: 'DATABASE_URL')]
        private string $databaseUrl,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $databases = CheckDatabases::fromDatabaseUrl($this->databaseUrl);
        } catch (\InvalidArgumentException) {
            $io->error('DATABASE_URL nennt keine gültige App-Datenbank (nur a–z, 0–9 und _).');

            return Command::INVALID;
        }

        $io->title('Abgleich mit dem Referenzschema');

        $io->text(\sprintf('1. Referenzschema in „%s“ laden', $databases->reference));
        $this->recreateDatabase($databases->reference);
        $this->loadReferenceSchema($databases->urlFor($databases->reference));

        $io->text(\sprintf('2. „%s“ von Null migrieren', $databases->check));
        $this->recreateDatabase($databases->check);
        $migration = new Process(
            [\PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--env=dev'],
            $this->projectDir,
            ['APP_ENV' => 'dev', 'DATABASE_URL' => $databases->urlFor($databases->check)],
            null,
            300,
        );
        if (0 !== $migration->run()) {
            $io->error(['Die Migrationen laufen auf einer leeren Datenbank nicht durch:', trim($migration->getErrorOutput()."\n".$migration->getOutput())]);

            return Command::FAILURE;
        }

        $io->text('3. Tabellen vergleichen');
        $result = $this->comparator->compare(
            $this->snapshotReader->read($databases->reference),
            $this->snapshotReader->read($databases->check),
            self::TECHNICAL_TABLES,
        );

        $io->newLine();
        foreach ($result->identical as $table) {
            $io->writeln(\sprintf('  <info>✔</info> %s – identisch', $table));
        }
        foreach ($result->differences as $table => $differences) {
            $io->writeln(\sprintf('  <error>✘</error> %s – %d Abweichung(en)', $table, \count($differences)));
            foreach ($differences as $difference) {
                $io->writeln('      '.str_replace("\n", "\n      ", $difference));
            }
        }
        foreach ($result->unknown as $table) {
            $io->writeln(\sprintf('  <error>✘</error> %s – steht nicht im Referenzschema', $table));
        }
        $io->newLine();
        $io->text(\sprintf('%d Tabelle(n) der Referenz haben noch keine Migration.', \count($result->notYetMigrated)));

        if (!$result->isIdentical()) {
            $io->error('Die migrierte Datenbank weicht vom Referenzschema ab.');

            return Command::FAILURE;
        }
        if ([] === $result->identical) {
            // Nothing to compare means the migration went elsewhere or did nothing – never green
            $io->error(\sprintf('In „%s“ ist keine migrierte Tabelle angekommen.', $databases->check));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Alle %d migrierten Tabellen sind identisch mit dem Referenzschema.', \count($result->identical)));

        return Command::SUCCESS;
    }

    private function recreateDatabase(string $name): void
    {
        $quoted = $this->connection->quoteSingleIdentifier($name);
        $this->connection->executeStatement('DROP DATABASE IF EXISTS '.$quoted);
        $this->connection->executeStatement('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function loadReferenceSchema(string $url): void
    {
        $script = file_get_contents($this->projectDir.'/docs/datenbank/schema.sql');
        if (false === $script) {
            throw new \RuntimeException('docs/datenbank/schema.sql is not readable.');
        }

        $reference = DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse($url));
        try {
            foreach ($this->splitter->split($script) as $statement) {
                $reference->executeStatement($statement);
            }
        } finally {
            $reference->close();
        }
    }
}
