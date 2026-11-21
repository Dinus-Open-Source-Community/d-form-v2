<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds the nullable guest actor hash and its active-submission lookup index. */
return new class () extends Migration {
    private const INDEX = 'form_answers_active_guest_token_form_index';

    public function up(): void
    {
        if (! Schema::hasColumn('form_answers', 'guest_token_hash')) {
            Schema::table('form_answers', function (Blueprint $table): void {
                $table->char('guest_token_hash', 64)->nullable();
            });
        }

        if ($this->indexExists()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE INDEX '.self::INDEX.' ON form_answers (form_id, guest_token_hash, is_active)');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE form_answers ADD INDEX `'.self::INDEX.'` (`form_id`, `guest_token_hash`, `is_active`), ALGORITHM=INPLACE, LOCK=NONE');
            return;
        }

        throw new LogicException('Unsupported database driver for guest token index: '.$driver);
    }

    public function down(): void
    {
        if ($this->indexExists()) {
            $driver = Schema::getConnection()->getDriverName();
            DB::statement($driver === 'mysql'
                ? 'ALTER TABLE form_answers DROP INDEX `'.self::INDEX.'`'
                : 'DROP INDEX IF EXISTS '.self::INDEX);
        }

        if (Schema::hasColumn('form_answers', 'guest_token_hash')) {
            Schema::table('form_answers', function (Blueprint $table): void {
                $table->dropColumn('guest_token_hash');
            });
        }
    }

    private function indexExists(): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            return DB::select('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1', ['form_answers', self::INDEX]) !== [];
        }

        if ($driver === 'sqlite') {
            return DB::select("SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ? LIMIT 1", ['form_answers', self::INDEX]) !== [];
        }

        if ($driver === 'pgsql') {
            return DB::select('SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1', ['form_answers', self::INDEX]) !== [];
        }

        return false;
    }
};
