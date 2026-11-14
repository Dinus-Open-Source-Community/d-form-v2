<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Contract form_answers uniqueness after the active marker has been audited by form-answers:audit-active-uniqueness. */
return new class () extends Migration {
    private const USER_INDEX = 'form_answers_active_user_form_unique';

    private const GUEST_INDEX = 'form_answers_active_guest_email_form_unique';

    public function up(): void
    {
        try {
            $this->createUserIndex();
            $this->createGuestIndex();
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyViolation($exception)) {
                throw $exception;
            }

            throw new RuntimeException(
                'form_answers contains active duplicate submissions; resolve them before rerunning the contract migration. '
                .'No rows were deleted. Underlying error: '.$exception->getMessage(),
                previous: $exception
            );
        }
    }

    public function down(): void
    {
        // Only the indexes this migration created are removed. `is_active` is
        // owned by the expand migration (2026_11_03) and must survive a
        // contract rollback, otherwise a one-step rollback breaks the running
        // application while the column it reads is still expected to exist.
        $this->dropIndexIfExists(self::USER_INDEX);
        $this->dropIndexIfExists(self::GUEST_INDEX);
    }

    private function createUserIndex(): void
    {
        if ($this->indexExists(self::USER_INDEX)) {
            return;
        }

        $this->createUniqueIndex(self::USER_INDEX, ['user_id', 'form_id', 'is_active']);
    }

    private function createGuestIndex(): void
    {
        if ($this->indexExists(self::GUEST_INDEX)) {
            return;
        }

        $this->createUniqueIndex(self::GUEST_INDEX, ['form_id', 'invited_email', 'is_active']);
    }

    /**
     *
     * Use online index DDL where the engine supports it; other drivers use the same composite unique schema without engine-specific generated columns.
     * @param  list<string>  $columns
     *
     */
    private function createUniqueIndex(string $indexName, array $columns): void
    {
        $quotedColumns = implode(', ', array_map(
            static fn (string $column): string => '`'.$column.'`',
            $columns,
        ));

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE form_answers ADD UNIQUE INDEX `'.$indexName.'` ('
                .$quotedColumns.'), ALGORITHM=INPLACE, LOCK=NONE'
            );

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX "'.$indexName.'" ON form_answers ('
            .implode(', ', $columns).')'
        );
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! $this->indexExists($indexName)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE form_answers DROP INDEX `'.$indexName.'`');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
    }

    private function indexExists(string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            return DB::select(
                'SELECT 1 FROM information_schema.statistics '
                .'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                ['form_answers', $indexName]
            ) !== [];
        }

        if ($driver === 'sqlite') {
            return DB::select(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ? LIMIT 1",
                ['form_answers', $indexName]
            ) !== [];
        }

        if ($driver === 'pgsql') {
            return DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                ['form_answers', $indexName]
            ) !== [];
        }

        return false;
    }

    private function isDuplicateKeyViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? '';

        if ($sqlState === '23505' || (int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'unique constraint failed');
    }
};
