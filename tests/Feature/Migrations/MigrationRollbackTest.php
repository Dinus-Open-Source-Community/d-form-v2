<?php

namespace Tests\Feature\Migrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the rollback path of every migration.
 *
 * The project ships destructive migrations (dropped columns, swapped indexes,
 * deleted rows) and production runs `migrate --force` on boot. A `down()` that
 * cannot execute turns an incident rollback into manual database surgery, so the
 * whole migration set must survive migrate -> rollback -> migrate on a throwaway
 * schema. Tests never touch the application connection: each round-trip uses a
 * disposable SQLite file (always) or a MySQL scratch database (opt-in).
 */
final class MigrationRollbackTest extends TestCase
{
    private const SQLITE_CONNECTION = 'migration_check_sqlite';

    private const MYSQL_CONNECTION = 'migration_check';

    private const MYSQL_ADMIN_CONNECTION = 'migration_admin';

    private const SAMPLE_TABLE = 'form_answers';

    private const MYSQL_SCRATCH_ENV = 'MIGRATION_ROLLBACK_DATABASE';

    public function test_complete_migration_set_rolls_back_and_reapplies_on_sqlite(): void
    {
        $this->useSqliteScratchDatabase();

        try {
            $this->assertSame(0, $this->artisanOn(self::SQLITE_CONNECTION, 'migrate'), $this->artisanOutput());
            $this->assertTrue($this->schemaOf(self::SQLITE_CONNECTION)->hasTable(self::SAMPLE_TABLE));

            $rollbackExit = $this->artisanOn(self::SQLITE_CONNECTION, 'migrate:rollback');
            $this->assertSame(0, $rollbackExit, 'Full rollback failed: '.$this->artisanOutput());
            $this->assertFalse(
                $this->schemaOf(self::SQLITE_CONNECTION)->hasTable(self::SAMPLE_TABLE),
                'Rollback left the schema behind, so the round-trip assertion would be meaningless.'
            );

            $this->assertSame(0, $this->artisanOn(self::SQLITE_CONNECTION, 'migrate'), 'Re-apply failed: '.$this->artisanOutput());
            $this->assertTrue($this->schemaOf(self::SQLITE_CONNECTION)->hasTable(self::SAMPLE_TABLE));
            $this->assertTrue($this->schemaOf(self::SQLITE_CONNECTION)->hasColumn(self::SAMPLE_TABLE, 'is_active'));
        } finally {
            DB::purge(self::SQLITE_CONNECTION);
        }
    }

    /**
     * The contract migration owns only the unique indexes. Rolling it back must
     * leave the expand marker in place, otherwise a one-step rollback breaks the
     * running application while every migration still recorded as applied
     * assumes the column exists.
     */
    public function test_contract_rollback_keeps_the_expand_marker_on_sqlite(): void
    {
        $this->useSqliteScratchDatabase();

        try {
            $this->assertSame(0, $this->artisanOn(self::SQLITE_CONNECTION, 'migrate'), $this->artisanOutput());

            $steps = $this->stepsBackTo('2026_11_04_000002');
            $this->assertGreaterThan(0, $steps, 'The contract migration is not applied in the scratch database.');

            $rollbackExit = $this->artisanOn(self::SQLITE_CONNECTION, 'migrate:rollback', ['--step' => $steps]);
            $this->assertSame(0, $rollbackExit, 'Contract rollback failed: '.$this->artisanOutput());

            $schema = $this->schemaOf(self::SQLITE_CONNECTION);

            $this->assertTrue(
                $schema->hasColumn(self::SAMPLE_TABLE, 'is_active'),
                'The contract rollback removed the expand marker it does not own.'
            );
            $this->assertFalse($schema->hasIndex(self::SAMPLE_TABLE, 'form_answers_active_user_form_unique'));
            $this->assertFalse($schema->hasIndex(self::SAMPLE_TABLE, 'form_answers_active_guest_email_form_unique'));
        } finally {
            DB::purge(self::SQLITE_CONNECTION);
        }
    }

    public function test_complete_migration_set_rolls_back_and_reapplies_on_mysql(): void
    {
        $scratchDatabase = getenv(self::MYSQL_SCRATCH_ENV);

        if (! is_string($scratchDatabase) || $scratchDatabase === '') {
            $this->markTestSkipped('Set '.self::MYSQL_SCRATCH_ENV.' to a disposable database name to run the MySQL round-trip.');
        }

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_]+_(check|test)$/',
            $scratchDatabase,
            'Refusing to run: the scratch database name must end with _check or _test.'
        );

        $this->assertFalse(app()->isProduction(), 'Refusing to run a rollback round-trip in production.');

        $baseConnection = config('database.connections.mysql');
        $this->assertIsArray($baseConnection, 'The mysql connection must be configured for this test.');

        // The test environment pins DB_DATABASE to :memory:, so the admin
        // connection is re-pointed to a system schema on the same server.
        config([
            'database.connections.'.self::MYSQL_ADMIN_CONNECTION => array_merge($baseConnection, ['database' => 'information_schema']),
            'database.connections.'.self::MYSQL_CONNECTION => array_merge($baseConnection, ['database' => $scratchDatabase]),
        ]);
        DB::purge(self::MYSQL_ADMIN_CONNECTION);
        DB::purge(self::MYSQL_CONNECTION);

        $this->createMysqlScratchDatabase($scratchDatabase);

        try {
            $this->assertSame(0, $this->artisanOn(self::MYSQL_CONNECTION, 'migrate'), 'Initial migrate failed: '.$this->artisanOutput());

            $rollbackExit = $this->artisanOn(self::MYSQL_CONNECTION, 'migrate:rollback');
            $this->assertSame(0, $rollbackExit, 'Full rollback failed: '.$this->artisanOutput());

            $remainingMigrations = DB::connection(self::MYSQL_CONNECTION)->table('migrations')->count();
            $this->assertSame(0, $remainingMigrations, 'Rollback left rows in the migrations table.');

            $this->assertSame(0, $this->artisanOn(self::MYSQL_CONNECTION, 'migrate'), 'Re-apply failed: '.$this->artisanOutput());

            $this->assertRecruitmentForeignKeysSurviveRollback();
        } finally {
            DB::purge(self::MYSQL_CONNECTION);
            DB::connection(self::MYSQL_ADMIN_CONNECTION)->statement('DROP DATABASE IF EXISTS `'.$scratchDatabase.'`');
            DB::purge(self::MYSQL_ADMIN_CONNECTION);
        }
    }

    /**
     * Every migration that can destroy data or previously created structure must
     * be classified in the operator runbook, so a rollback plan always exists
     * before the migration reaches production.
     */
    public function test_every_destructive_migration_is_listed_in_the_runbook(): void
    {
        $runbook = base_path('docs/runbook/database-migrations.md');

        $this->assertFileExists($runbook, 'The destructive-migration runbook is missing.');

        $documented = $this->documentedMigrationFiles((string) file_get_contents($runbook));
        $destructive = $this->destructiveMigrationFiles();

        $this->assertSame(
            [],
            array_values(array_diff($destructive, $documented)),
            'These migrations destroy data or earlier structure but have no rollback plan in '
            .'docs/runbook/database-migrations.md: '
        );

        $this->assertSame(
            [],
            array_values(array_diff($documented, $this->allMigrationFiles())),
            'The runbook lists migration files that no longer exist.'
        );
    }

    /**
     * The round-trip exit code alone cannot distinguish "rollback worked" from
     * "rollback silently dropped a foreign key". This pins the schema at a
     * boundary where `form_answers` still exists: both original FKs must be back
     * and no support/unique index introduced later may survive.
     */
    public function test_targeted_rollback_restores_form_answers_foreign_keys_on_sqlite(): void
    {
        $this->useSqliteScratchDatabase();

        try {
            $this->assertSame(0, $this->artisanOn(self::SQLITE_CONNECTION, 'migrate'), $this->artisanOutput());

            // Roll back everything newer than create_form_answers_table so that
            // migration itself stays applied.
            $steps = $this->stepsBackTo('2026_04_18_171225') - 1;
            $this->assertGreaterThan(0, $steps, 'Expected migrations newer than create_form_answers_table.');

            $rollbackExit = $this->artisanOn(self::SQLITE_CONNECTION, 'migrate:rollback', ['--step' => $steps]);
            $this->assertSame(0, $rollbackExit, 'Targeted rollback failed: '.$this->artisanOutput());

            $schema = $this->schemaOf(self::SQLITE_CONNECTION);

            $foreignKeyColumns = collect($schema->getForeignKeys(self::SAMPLE_TABLE))
                ->pluck('columns')
                ->flatten()
                ->sort()
                ->values()
                ->all();

            $this->assertSame(
                ['form_id', 'user_id'],
                $foreignKeyColumns,
                'Rollback lost a foreign key on form_answers.'
            );

            $indexNames = collect($schema->getIndexes(self::SAMPLE_TABLE))->pluck('name')->all();

            $this->assertNotContains(
                'form_answers_user_form_unique',
                $indexNames,
                'Rollback left the unique index that the migration was supposed to remove.'
            );
            $this->assertNotContains(
                'form_answers_form_id_group_token_index',
                $indexNames,
                'Rollback left an index created by a later migration.'
            );
        } finally {
            DB::purge(self::SQLITE_CONNECTION);
        }
    }

    /**
     * Restoring unique(application_id) is only legal when one interview exists
     * per application. The guard must fire before any DDL, otherwise a failed
     * rollback leaves the schema without `interview_kind` and without the FK.
     */
    public function test_secondary_interview_conflict_blocks_rollback_before_schema_changes(): void
    {
        $this->useSqliteScratchDatabase();

        try {
            $this->assertSame(0, $this->artisanOn(self::SQLITE_CONNECTION, 'migrate'), $this->artisanOutput());

            $connection = DB::connection(self::SQLITE_CONNECTION);
            $connection->statement('PRAGMA foreign_keys = OFF');
            $applicationId = (string) Str::uuid();
            $connection->table('recruitment_interviews')->insert([
                $this->interviewRow($applicationId, 'primary'),
                $this->interviewRow($applicationId, 'secondary'),
            ]);

            $steps = $this->stepsBackTo('2026_10_08_000001');

            try {
                $this->artisanOn(self::SQLITE_CONNECTION, 'migrate:rollback', ['--step' => $steps]);
                $this->fail('Rollback should fail closed when secondary interviews exist.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString(
                    'This migration applied no schema changes',
                    $exception->getMessage(),
                    'The failure must explain the data conflict instead of surfacing a raw constraint error.'
                );
            }

            $schema = $this->schemaOf(self::SQLITE_CONNECTION);

            $this->assertTrue(
                $schema->hasColumn('recruitment_interviews', 'interview_kind'),
                'The conflict guard ran after the discriminator column was already dropped.'
            );
            $this->assertTrue(
                $schema->hasColumn('recruitment_evaluations', 'save_count'),
                'The conflict guard ran after evaluation data was already dropped.'
            );
            // SQLite rebuilds the table instead of dropping FKs, so a FK check
            // here cannot fail even without the guard; the MySQL path is the one
            // that drops and restores FKs (covered by the fidelity assertions in
            // test_complete_migration_set_rolls_back_and_reapplies_on_mysql).
        } finally {
            DB::purge(self::SQLITE_CONNECTION);
        }
    }


    /**
     * The ticket's root cause was a FK dropped and never restored. `form_answers`
     * is asserted on SQLite; the MySQL path additionally pins the two recruitment
     * tables, which SQLite cannot prove because it rebuilds instead of dropping FKs.
     */
    private function assertRecruitmentForeignKeysSurviveRollback(): void
    {
        $steps = $this->stepsBackToOn(self::MYSQL_CONNECTION, '2026_09_07_000003') - 1;
        $this->assertGreaterThan(0, $steps, 'Expected migrations newer than the recruitment interview tables.');

        $rollbackExit = $this->artisanOn(self::MYSQL_CONNECTION, 'migrate:rollback', ['--step' => $steps]);
        $this->assertSame(0, $rollbackExit, 'Targeted recruitment rollback failed: '.$this->artisanOutput());

        $schema = Schema::connection(self::MYSQL_CONNECTION);

        $evaluationForeignKeyColumns = collect($schema->getForeignKeys('recruitment_evaluations'))
            ->pluck('columns')
            ->flatten()
            ->all();
        foreach (['recruitment_application_id', 'recruitment_interview_id', 'evaluated_by'] as $column) {
            $this->assertContains(
                $column,
                $evaluationForeignKeyColumns,
                'Rollback lost the recruitment_evaluations foreign key on '.$column.'.'
            );
        }

        $interviewForeignKeyColumns = collect($schema->getForeignKeys('recruitment_interviews'))
            ->pluck('columns')
            ->flatten()
            ->all();
        foreach (['recruitment_application_id', 'recruitment_interview_session_id', 'interviewer_id'] as $column) {
            $this->assertContains(
                $column,
                $interviewForeignKeyColumns,
                'Rollback lost the recruitment_interviews foreign key on '.$column.'.'
            );
        }

        $this->assertFalse(
            $schema->hasColumn('recruitment_interviews', 'interview_kind'),
            'Rollback kept the discriminator column it was supposed to remove.'
        );

        // The rollback recreates unique(application_id) before any destructive DDL;
        // losing it silently re-allows duplicate interviews per application.
        $this->assertTrue(
            $schema->hasIndex('recruitment_evaluations', 'recruitment_evaluations_recruitment_application_id_unique'),
            'Rollback did not restore unique(recruitment_application_id) on recruitment_evaluations.'
        );
        $this->assertTrue(
            $schema->hasIndex('recruitment_interviews', 'recruitment_interviews_recruitment_application_id_unique'),
            'Rollback did not restore unique(recruitment_application_id) on recruitment_interviews.'
        );
        $this->assertFalse(
            $schema->hasIndex('recruitment_interviews', 'rec_interviews_app_kind_uniq'),
            'Rollback kept the composite unique it was supposed to remove.'
        );
    }

    private function artisanOn(string $connection, string $command, array $parameters = []): int
    {
        return Artisan::call($command, array_merge($parameters, [
            '--database' => $connection,
            '--force' => true,
        ]));
    }

    private function artisanOutput(): string
    {
        return Artisan::output();
    }

    /**
     * @return array<string, string>
     */
    private function interviewRow(string $applicationId, string $interviewKind): array
    {
        return [
            'id' => (string) Str::uuid(),
            'recruitment_application_id' => $applicationId,
            'recruitment_interview_session_id' => (string) Str::uuid(),
            'scheduled_at' => now()->toDateTimeString(),
            'location' => 'conflict-probe',
            'room' => 'conflict-probe',
            'status' => 'waiting',
            'interview_kind' => $interviewKind,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    private function schemaOf(string $connection): \Illuminate\Database\Schema\Builder
    {
        return Schema::connection($connection);
    }

    /**
     * Number of rollback steps needed to undo the named migration and everything
     * newer than it, based on the applied order rather than file-name guessing.
     */
    private function stepsBackTo(string $migrationPrefix): int
    {
        return $this->stepsBackToOn(self::SQLITE_CONNECTION, $migrationPrefix);
    }

    private function stepsBackToOn(string $connection, string $migrationPrefix): int
    {
        $applied = DB::connection($connection)
            ->table('migrations')
            ->orderByDesc('id')
            ->pluck('migration')
            ->all();

        foreach ($applied as $index => $migration) {
            if (str_starts_with((string) $migration, $migrationPrefix)) {
                return $index + 1;
            }
        }

        return 0;
    }

    /**
     * A dedicated connection keeps the round-trip off the shared test connection;
     * `:memory:` gives it its own PDO, so it cannot leak schema into other tests.
     */
    private function useSqliteScratchDatabase(): void
    {
        config([
            'database.connections.'.self::SQLITE_CONNECTION => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge(self::SQLITE_CONNECTION);
    }

    private function createMysqlScratchDatabase(string $database): void
    {
        $quoted = '`'.$database.'`';

        DB::connection(self::MYSQL_ADMIN_CONNECTION)->statement('DROP DATABASE IF EXISTS '.$quoted);
        DB::connection(self::MYSQL_ADMIN_CONNECTION)->statement(
            'CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    /**
     * @return list<string> migration file names (without extension)
     */
    private function destructiveMigrationFiles(): array
    {
        $files = [];

        foreach ($this->allMigrationFiles() as $migration) {
            $contents = (string) file_get_contents(base_path('database/migrations/'.$migration.'.php'));

            if ($this->destroysEarlierStructure($contents)) {
                $files[] = $migration;
            }
        }

        return $files;
    }

    /**
     * Only migrations that can lose data need a documented rollback plan.
     * Index/FK-only reversals are covered by the round-trip test instead, so a
     * new index migration from another lane does not need a runbook entry.
     */
    private function destroysEarlierStructure(string $contents): bool
    {
        $contents = $this->stripPhpComments($contents);

        if (preg_match('/->(dropColumn|renameColumn)\(/', $contents) === 1) {
            return true;
        }

        if (preg_match('/->(delete|truncate)\(/', $contents) === 1) {
            return true;
        }

        // Driver-specific raw DDL hides drops from the fluent checks above.
        if (preg_match('/DB::statement\s*\((?:[^()]|\([^()]*\))*?DROP\s+(?:COLUMN|TABLE|INDEX)\b/is', $contents) === 1) {
            return true;
        }

        return $this->tablesDroppedOutsideTheirCreateMigration($contents) !== [];
    }

    /**
     * Raw `DB::statement()` drops are invisible to the fluent detector, so a
     * regression here would let an index/column drop reach production without a
     * runbook entry. The new generated-column migration is the canonical case;
     * comment-only mentions must not be flagged.
     */
    public function test_raw_ddl_drop_is_classified_as_destructive(): void
    {
        $destructive = $this->destructiveMigrationFiles();

        $this->assertContains('2026_11_10_000008_users_email_lookup_index', $destructive);
        $this->assertNotContains('2026_11_10_000004_drop_redundant_recruitment_indexes', $destructive);

        $documented = $this->documentedMigrationFiles((string) file_get_contents(
            base_path('docs/runbook/database-migrations.md')
        ));

        $this->assertContains('2026_11_10_000008_users_email_lookup_index', $documented);
    }

    /**
     * A comment is not executable, so a mention inside it must neither flag a
     * migration as destructive nor mask a real drop. `token_get_all` avoids
     * regex heuristics that cannot tell `// Schema::dropIfExists('x')` from code.
     */
    private function stripPhpComments(string $contents): string
    {
        $stripped = '';

        foreach (token_get_all($contents) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $stripped .= str_repeat("\n", substr_count($token[1], "\n"));

                    continue;
                }

                $stripped .= $token[1];

                continue;
            }

            $stripped .= $token;
        }

        return $stripped;
    }

    /**
     * Comment-only mentions must not change classification in either direction.
     */
    public function test_comment_only_mentions_do_not_affect_destructive_classification(): void
    {
        $method = new \ReflectionMethod($this, 'destroysEarlierStructure');
        $method->setAccessible(true);

        $commented = <<<'PHP'
        <?php
        // Schema::dropIfExists('legacy_table');
        /* ->dropColumn('notes') */
        return new class extends Migration {};
        PHP;

        $real = <<<'PHP'
        <?php
        return new class extends Migration {
            public function down(): void
            {
                Schema::dropIfExists('legacy_table');
            }
        };
        PHP;

        $this->assertFalse($method->invoke($this, $commented));
        $this->assertTrue($method->invoke($this, $real));
    }

    /**
     * A create_* migration that drops the table it created is a normal reversal.
     * A migration that drops a table it did not create needs an explicit plan.
     *
     * @return list<string>
     */
    private function tablesDroppedOutsideTheirCreateMigration(string $contents): array
    {
        $dropped = [];
        $created = [];

        if (preg_match_all("/Schema::dropIfExists\\(\\s*'([^']+)'/", $contents, $dropMatches) !== false) {
            $dropped = $dropMatches[1];
        }

        if (preg_match_all("/Schema::create\\(\\s*'([^']+)'/", $contents, $createMatches) !== false) {
            $created = $createMatches[1];
        }

        return array_values(array_diff($dropped, $created));
    }

    /**
     * @return list<string>
     */
    private function documentedMigrationFiles(string $runbook): array
    {
        if (preg_match_all('/`([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9_a-z]+)`/', $runbook, $matches) === false) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<string>
     */
    private function allMigrationFiles(): array
    {
        $paths = glob(database_path('migrations/*.php'));

        if ($paths === false) {
            return [];
        }

        $files = array_map(
            static fn (string $path): string => basename($path, '.php'),
            $paths
        );

        sort($files);

        return $files;
    }
}
