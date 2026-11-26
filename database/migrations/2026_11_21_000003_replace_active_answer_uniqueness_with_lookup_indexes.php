<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const USER_UNIQUE_INDEX = 'form_answers_active_user_form_unique';
    private const USER_LOOKUP_INDEX = 'form_answers_active_user_form_lookup';

    public function up(): void
    {
        $this->createIndex(self::USER_LOOKUP_INDEX, ['user_id', 'form_id', 'is_active']);
        $this->dropIndex(self::USER_UNIQUE_INDEX);
    }

    public function down(): void
    {
        $this->dropIndex(self::USER_LOOKUP_INDEX);
    }

    /** @param list<string> $columns */
    private function createIndex(string $name, array $columns): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        $quotedColumns = implode(', ', array_map(static fn (string $column): string => '`'.$column.'`', $columns));
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE form_answers ADD INDEX `'.$name.'` ('.$quotedColumns.'), ALGORITHM=INPLACE, LOCK=NONE');
            return;
        }

        DB::statement('CREATE INDEX "'.$name.'" ON form_answers ('.implode(', ', $columns).')');
    }

    private function dropIndex(string $name): void
    {
        if (! $this->indexExists($name)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE form_answers DROP INDEX `'.$name.'`');
            return;
        }

        DB::statement('DROP INDEX IF EXISTS "'.$name.'"');
    }

    private function indexExists(string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            return DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                ['form_answers', $name],
            ) !== [];
        }

        if ($driver === 'sqlite') {
            return DB::select(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ? LIMIT 1",
                ['form_answers', $name],
            ) !== [];
        }

        if ($driver === 'pgsql') {
            return DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                ['form_answers', $name],
            ) !== [];
        }

        return false;
    }
};
