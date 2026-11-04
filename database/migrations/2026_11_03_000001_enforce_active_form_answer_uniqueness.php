<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand form_answers with the portable nullable active-submission marker.
 *
 * This migration replaces the unsuccessful generated-column attempt from this
 * ticket. It deliberately creates no unique index; the contract migration does
 * that after the application has backfilled the marker and audited duplicates.
 */
return new class () extends Migration {
    private const USER_INDEX = 'form_answers_active_user_form_unique';

    private const GUEST_INDEX = 'form_answers_active_guest_email_form_unique';

    private const LEGACY_USER_INDEX = 'form_answers_user_form_unique';

    private const LEGACY_USER_KEY_COLUMN = 'active_user_form_key';

    private const LEGACY_GUEST_KEY_COLUMN = 'active_guest_email_form_key';

    public function up(): void
    {
        $this->dropIndexIfExists(self::USER_INDEX);
        $this->dropIndexIfExists(self::GUEST_INDEX);
        $this->dropIndexIfExists(self::LEGACY_USER_INDEX);
        $this->dropColumnIfExists(self::LEGACY_USER_KEY_COLUMN);
        $this->dropColumnIfExists(self::LEGACY_GUEST_KEY_COLUMN);

        if (Schema::hasColumn('form_answers', 'is_active')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE form_answers ADD COLUMN is_active TINYINT NULL, ALGORITHM=INSTANT'
            );

            return;
        }

        Schema::table('form_answers', function (Blueprint $table): void {
            $table->tinyInteger('is_active')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists(self::USER_INDEX);
        $this->dropIndexIfExists(self::GUEST_INDEX);
        $this->dropColumnIfExists('is_active');
        $this->dropColumnIfExists(self::LEGACY_USER_KEY_COLUMN);
        $this->dropColumnIfExists(self::LEGACY_GUEST_KEY_COLUMN);
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

    private function dropColumnIfExists(string $columnName): void
    {
        if (! Schema::hasColumn('form_answers', $columnName)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE form_answers DROP COLUMN `'.$columnName.'`');

            return;
        }

        Schema::table('form_answers', function (Blueprint $table) use ($columnName): void {
            $table->dropColumn($columnName);
        });
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
};
