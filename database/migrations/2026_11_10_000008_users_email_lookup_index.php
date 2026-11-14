<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds an index-usable normalized column for case-insensitive email lookup. */
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

    /** SQLite omits generated columns from `PRAGMA table_info`, so `Schema::hasColumn` cannot detect this column there; use `table_xinfo` instead. */
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
