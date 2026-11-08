<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_logs', 'recruitment_application_id')) {
                $table->uuid('recruitment_application_id')->nullable()->after('form_answer_id');
                $table->foreign('recruitment_application_id', 'email_logs_rec_app_fk')
                    ->references('id')
                    ->on('recruitment_applications')
                    ->nullOnDelete();
                $table->index('recruitment_application_id', 'email_logs_rec_app_idx');
            }
        });

        if (! Schema::hasColumn('email_logs', 'event_id')) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS email_logs_event_id_status_index');
            Schema::table('email_logs', function (Blueprint $table): void {
                $table->dropColumn('event_id');
            });
            Schema::table('email_logs', function (Blueprint $table): void {
                $table->uuid('event_id')->nullable();
                $table->foreign('event_id')
                    ->references('id')
                    ->on('events')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->index(['event_id', 'status']);
            });

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE email_logs MODIFY event_id CHAR(36) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE email_logs ALTER COLUMN event_id DROP NOT NULL');
        }

        Schema::table('email_logs', function (Blueprint $table): void {
            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // This down() deliberately does not restore the old `email_logs.event_id`
        // NOT NULL constraint: existing rows may be NULL, so it stays nullable.
        if (! Schema::hasColumn('email_logs', 'recruitment_application_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite cannot drop a foreign key by name; the column form
            // triggers a table rebuild that drops it.
            Schema::table('email_logs', function (Blueprint $table): void {
                $table->dropForeign(['recruitment_application_id']);
            });
        } elseif ($this->foreignKeyExists('email_logs', 'email_logs_rec_app_fk')) {
            Schema::table('email_logs', function (Blueprint $table): void {
                $table->dropForeign('email_logs_rec_app_fk');
            });
        }

        if (Schema::hasIndex('email_logs', 'email_logs_rec_app_idx')) {
            Schema::table('email_logs', function (Blueprint $table): void {
                $table->dropIndex('email_logs_rec_app_idx');
            });
        }

        Schema::table('email_logs', function (Blueprint $table): void {
            $table->dropColumn('recruitment_application_id');
        });
    }

    /** Check a named foreign key on mysql (information_schema) or pgsql (pg_constraint). */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return DB::selectOne(
                "SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ?",
                [Schema::getConnection()->getDatabaseName(), $table, $foreignKey]
            ) !== null;
        }

        return DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ? LIMIT 1',
            [$foreignKey]
        ) !== null;
    }
};
