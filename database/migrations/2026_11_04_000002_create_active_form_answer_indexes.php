<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract form_answers uniqueness after the active marker has been audited.
 *
 * The migration never repairs duplicate data. Its read-only preflight and the
 * database unique indexes both abort safely so an operator can resolve the
 * conflict deliberately before retrying.
 */
return new class () extends Migration {
    private const USER_INDEX = 'form_answers_active_user_form_unique';

    private const GUEST_INDEX = 'form_answers_active_guest_email_form_unique';

    public function up(): void
    {
        $this->assertActiveMarkersBackfilled();
        $this->assertNoActiveDuplicates();

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
        $this->dropIndexIfExists(self::USER_INDEX);
        $this->dropIndexIfExists(self::GUEST_INDEX);

        if (! Schema::hasColumn('form_answers', 'is_active')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE form_answers DROP COLUMN `is_active`');

            return;
        }

        Schema::table('form_answers', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }

    private function assertNoActiveDuplicates(): void
    {
        $userDuplicate = DB::table('form_answers')
            ->select(['user_id', 'form_id'])
            ->where('is_active', 1)
            ->whereNotNull('user_id')
            ->groupBy(['user_id', 'form_id'])
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($userDuplicate !== null) {
            throw new RuntimeException(
                "Cannot create form_answers active user index: duplicate user_id {$userDuplicate->user_id} "
                ."for form_id {$userDuplicate->form_id}. No rows were deleted."
            );
        }

        $guestDuplicate = DB::table('form_answers')
            ->select(['form_id', 'invited_email'])
            ->where('is_active', 1)
            ->whereNull('user_id')
            ->whereNotNull('invited_email')
            ->where('invited_email', '!=', '')
            ->groupBy(['form_id', 'invited_email'])
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($guestDuplicate !== null) {
            throw new RuntimeException(
                "Cannot create form_answers active guest index: duplicate invited_email for form_id "
                ."{$guestDuplicate->form_id}. No rows were deleted."
            );
        }
    }

    private function assertActiveMarkersBackfilled(): void
    {
        $unmarkedActive = DB::table('form_answers')
            ->select('id')
            ->whereNull('is_active')
            ->where(function (QueryBuilder $query): void {
                $query
                    ->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'rejected');
            })
            ->where(function (QueryBuilder $query): void {
                $query
                    ->whereNull('registration_role')
                    ->orWhere('registration_role', '!=', 'member')
                    ->orWhereNull('member_confirmation_status')
                    ->orWhereNotIn('member_confirmation_status', ['rejected', 'expired']);
            })
            ->first();

        if ($unmarkedActive !== null) {
            throw new RuntimeException(
                'Cannot create form_answers active indexes: active marker backfill is incomplete. '
                .'Run form-answers:backfill-active-marker before rerunning this migration. '
                .'No rows were deleted.'
            );
        }
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
     * Use online index DDL where the engine supports it; other drivers use the
     * same composite unique schema without engine-specific generated columns.
     *
     * @param  list<string>  $columns
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
