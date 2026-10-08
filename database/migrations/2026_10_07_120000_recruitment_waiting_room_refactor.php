<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('recruitment_queue_entries');

        if (! Schema::hasColumn('recruitment_interviews', 'booked_at')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                // MySQL-safe: drop by explicit custom name (default-convention
                // name from dropForeign(['col']) does not exist; actual FK is
                // `rec_interviews_iv_fk`). Check information_schema first so a
                // missing FK never throws 1091, and also cover the legacy
                // default name in case an old schema used it.
                $database = Schema::getConnection()->getDatabaseName();
                $existingFks = DB::select(
                    "SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME IN (?, ?)",
                    [$database, 'recruitment_interviews', 'rec_interviews_iv_fk', 'recruitment_interviews_interviewer_id_foreign']
                );

                foreach ($existingFks as $fkRow) {
                    $fkName = is_array($fkRow) ? ($fkRow['name'] ?? null) : ($fkRow->name ?? null);
                    if (! is_string($fkName) || $fkName === '') {
                        continue;
                    }
                    Schema::table('recruitment_interviews', function (Blueprint $table) use ($fkName): void {
                        $table->dropForeign($fkName);
                    });
                }

                DB::statement('ALTER TABLE recruitment_interviews MODIFY interviewer_id CHAR(36) NULL');

                Schema::table('recruitment_interviews', function (Blueprint $table): void {
                    $table->dateTime('booked_at')->nullable()->after('interviewer_id');
                });

                // Guard re-add so a partial-failure resume does not fail on
                // duplicate FK (1826) when `rec_interviews_iv_fk` already exists.
                $fkExists = DB::selectOne(
                    "SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ?",
                    [$database, 'recruitment_interviews', 'rec_interviews_iv_fk']
                );

                if ($fkExists === null) {
                    Schema::table('recruitment_interviews', function (Blueprint $table): void {
                        $table->foreign('interviewer_id', 'rec_interviews_iv_fk')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    });
                }
            } else {
                // SQLite / other drivers (tests, --database=sqlite): no
                // MODIFY support and no named-FK drop; just add the column.
                // `after()` is MySQL-only so it is omitted here.
                Schema::table('recruitment_interviews', function (Blueprint $table): void {
                    $table->dateTime('booked_at')->nullable();
                });
            }
        }

        if (! Schema::hasTable('recruitment_interviews')) {
            return;
        }

        DB::table('recruitment_interviews')
            ->whereIn('status', ['scheduled', 'checked_in', 'queued'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('recruitment_attendances')
                    ->whereColumn(
                        'recruitment_attendances.recruitment_application_id',
                        'recruitment_interviews.recruitment_application_id',
                    );
            })
            ->update([
                'status' => 'waiting',
                'interviewer_id' => null,
                'booked_at' => null,
            ]);

        DB::table('recruitment_interviews')
            ->whereIn('status', ['scheduled', 'checked_in', 'queued', 'no_show'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('recruitment_attendances')
                    ->whereColumn(
                        'recruitment_attendances.recruitment_application_id',
                        'recruitment_interviews.recruitment_application_id',
                    );
            })
            ->delete();

        DB::table('recruitment_interviews')
            ->where('status', 'called')
            ->whereNotNull('interviewer_id')
            ->update(['status' => 'in_progress']);

        DB::table('recruitment_interviews')
            ->where('status', 'called')
            ->whereNull('interviewer_id')
            ->update(['status' => 'waiting']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('recruitment_interviews', 'booked_at')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->dropColumn('booked_at');
            });
        }
    }
};
