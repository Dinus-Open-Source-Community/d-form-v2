<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index-usable, case-insensitive email lookup for `users`.
 *
 * MariaDB 10.11 (production) has no functional/expression indexes and its
 * optimizer ignores indexed VIRTUAL generated columns for range/ref access
 * until 11.8, so the portable path is a generated normalized column that is
 * PERSISTENT/STORED on MySQL and MariaDB. SQLite can only add VIRTUAL generated
 * columns via ALTER TABLE, which it then indexes as an expression index.
 *
 * The column is database-maintained, so every existing and future email write
 * keeps it consistent without an application observer or backfill update.
 *
 * DEPLOY / ROLLBACK ORDER (read before touching production):
 * - `ADD COLUMN ... STORED` forces an InnoDB table rebuild (no instant path
 *   for stored generated columns on MariaDB 10.11; INPLACE still rewrites
 *   every row). Measured ~60 s
 *   on the 1M-row dev `users` table; run it pre-deploy or in a maintenance
 *   window with disk headroom for a full table copy.
 * - The application calls `User::whereNormalizedEmailIn()` / the shared email
 *   resolver after this migration. A rollback therefore requires shipping the
 *   code first (or having the app tolerate the missing column): running
 *   `down()` against the current code would break registration and OAuth.
 * - `down()` drops the index then the column; `users.email` is untouched, so no
 *   operational data is lost.
 * - The index is only equivalent to the old predicate when the production
 *   `DB_COLLATION` is `utf8mb4_unicode_ci` (the collation tested on MariaDB
 *   10.11/MySQL 9); a binary or non-unicode collation changes matching.
 */
return new class () extends Migration {
    private const COLUMN = 'email_normalized';

    private const INDEX = 'users_email_normalized_index';

    public function up(): void
    {
        if (! $this->columnExists()) {
            $definition = $this->driver() === 'sqlite'
                ? 'VARCHAR(255) GENERATED ALWAYS AS (lower(email)) VIRTUAL'
                : 'VARCHAR(255) GENERATED ALWAYS AS (LOWER(email)) STORED';

            DB::statement('ALTER TABLE users ADD COLUMN '.self::COLUMN.' '.$definition);
        }

        if (! Schema::hasIndex('users', self::INDEX)) {
            DB::statement('CREATE INDEX '.self::INDEX.' ON users ('.self::COLUMN.')');
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('users', self::INDEX)) {
            $statement = $this->driver() === 'sqlite'
                ? 'DROP INDEX '.self::INDEX
                : 'DROP INDEX '.self::INDEX.' ON users';

            DB::statement($statement);
        }

        if ($this->columnExists()) {
            DB::statement('ALTER TABLE users DROP COLUMN '.self::COLUMN);
        }
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * SQLite omits generated columns from `PRAGMA table_info`, so `Schema::hasColumn`
     * cannot detect this column there; use `table_xinfo` instead.
     */
    private function columnExists(): bool
    {
        if ($this->driver() !== 'sqlite') {
            return Schema::hasColumn('users', self::COLUMN);
        }

        foreach (DB::select("PRAGMA table_xinfo('users')") as $column) {
            if (($column->name ?? null) === self::COLUMN) {
                return true;
            }
        }

        return false;
    }
};
