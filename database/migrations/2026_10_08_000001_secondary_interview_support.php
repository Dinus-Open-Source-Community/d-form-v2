<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Idempoten: migrasi ini pernah gagal di tengah jalan pada sebagian
        // database (statement MySQL dieksekusi satu per satu), sehingga
        // langkah yang sudah teraplikasi harus dilewati saat diulang.
        // Catatan MySQL: UNIQUE lama tidak boleh di-drop bila masih menjadi
        // satu-satunya penopang foreign key (error 1553). Untuk interviews,
        // composite UNIQUE (application_id, kind) dibuat DULU agar FK tetap
        // tertopang; untuk evaluations, FK di-drop dan dipasang ulang karena
        // UNIQUE baru (interview_id) tidak mencakup application_id.
        if (! Schema::hasColumn('recruitment_interviews', 'interview_kind')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->string('interview_kind', 20)->default('primary');
            });
        }

        if (! Schema::hasIndex('recruitment_interviews', 'rec_interviews_app_kind_uniq')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->unique(['recruitment_application_id', 'interview_kind'], 'rec_interviews_app_kind_uniq');
            });
        }

        if (Schema::hasIndex('recruitment_interviews', 'recruitment_interviews_recruitment_application_id_unique')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->dropUnique(['recruitment_application_id']);
            });
        }

        DB::table('recruitment_interviews')
            ->whereNull('interview_kind')
            ->update(['interview_kind' => 'primary']);

        if (! Schema::hasColumn('recruitment_evaluations', 'save_count')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->unsignedTinyInteger('save_count')->default(0);
            });
        }

        if (Schema::hasIndex('recruitment_evaluations', 'recruitment_evaluations_recruitment_application_id_unique')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                // SQLite tidak mendukung drop by-name; MySQL butuh nama eksplisit
                // karena FK memakai nama custom (bukan konvensi Laravel).
                if (DB::getDriverName() === 'mysql') {
                    $table->dropForeign('rec_eval_app_fk');
                } else {
                    $table->dropForeign(['recruitment_application_id']);
                }
            });

            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->dropUnique(['recruitment_application_id']);
            });

            if (! Schema::hasIndex('recruitment_evaluations', 'rec_eval_interview_uniq')) {
                Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                    $table->unique(['recruitment_interview_id'], 'rec_eval_interview_uniq');
                });
            }

            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->foreign('recruitment_application_id', 'rec_eval_app_fk')
                    ->references('id')
                    ->on('recruitment_applications')
                    ->cascadeOnDelete();
            });
        } elseif (! Schema::hasIndex('recruitment_evaluations', 'rec_eval_interview_uniq')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->unique(['recruitment_interview_id'], 'rec_eval_interview_uniq');
            });
        }

        DB::table('recruitment_evaluations')
            ->whereNotNull('locked_at')
            ->update(['save_count' => 3]);

        DB::table('recruitment_evaluations')
            ->whereNull('locked_at')
            ->update(['save_count' => 1]);
    }

    public function down(): void
    {
        // Conflicts are detected before any DDL, and the uniqueness constraint is
        // established before the first destructive change, so a duplicate row that
        // appears after the check makes that statement fail while the schema is
        // still intact instead of leaving a half-reverted migration behind.
        $this->assertRollbackHasNoConflicts();

        // Added first on purpose: MySQL accepts this redundant unique while the
        // composite (recruitment_application_id, interview_kind) still exists, so
        // the race window closes before any drop.
        if (! Schema::hasIndex('recruitment_evaluations', 'recruitment_evaluations_recruitment_application_id_unique')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->unique(['recruitment_application_id']);
            });
        }

        if (! Schema::hasIndex('recruitment_interviews', 'recruitment_interviews_recruitment_application_id_unique')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->unique(['recruitment_application_id']);
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                if (Schema::hasIndex('recruitment_evaluations', 'rec_eval_interview_uniq')) {
                    $table->dropUnique('rec_eval_interview_uniq');
                }

                if (Schema::hasColumn('recruitment_evaluations', 'save_count')) {
                    $table->dropColumn('save_count');
                }
            });

            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                if (Schema::hasIndex('recruitment_interviews', 'rec_interviews_app_kind_uniq')) {
                    $table->dropUnique('rec_interviews_app_kind_uniq');
                }

                if (Schema::hasColumn('recruitment_interviews', 'interview_kind')) {
                    $table->dropColumn('interview_kind');
                }
            });

            return;
        }

        // MySQL: a foreign key cannot lose its only supporting index (1553),
        // so keys resting on the dropped uniques are removed and re-added.
        if ($this->mysqlForeignKeyExists('recruitment_evaluations', 'rec_eval_interview_fk')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->dropForeign('rec_eval_interview_fk');
            });
        }

        if (Schema::hasIndex('recruitment_evaluations', 'rec_eval_interview_uniq')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->dropUnique('rec_eval_interview_uniq');
            });
        }

        if (Schema::hasColumn('recruitment_evaluations', 'save_count')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->dropColumn('save_count');
            });
        }

        if (! $this->mysqlForeignKeyExists('recruitment_evaluations', 'rec_eval_interview_fk')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->foreign('recruitment_interview_id', 'rec_eval_interview_fk')
                    ->references('id')
                    ->on('recruitment_interviews')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->mysqlForeignKeyExists('recruitment_evaluations', 'rec_eval_app_fk')) {
            Schema::table('recruitment_evaluations', function (Blueprint $table): void {
                $table->foreign('recruitment_application_id', 'rec_eval_app_fk')
                    ->references('id')
                    ->on('recruitment_applications')
                    ->cascadeOnDelete();
            });
        }

        if ($this->mysqlForeignKeyExists('recruitment_interviews', 'rec_interviews_app_fk')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->dropForeign('rec_interviews_app_fk');
            });
        }

        if (Schema::hasIndex('recruitment_interviews', 'rec_interviews_app_kind_uniq')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->dropUnique('rec_interviews_app_kind_uniq');
            });
        }

        if (Schema::hasColumn('recruitment_interviews', 'interview_kind')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->dropColumn('interview_kind');
            });
        }

        if (! $this->mysqlForeignKeyExists('recruitment_interviews', 'rec_interviews_app_fk')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->foreign('recruitment_application_id', 'rec_interviews_app_fk')
                    ->references('id')
                    ->on('recruitment_applications')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Abort before any DDL when duplicate applications would break the unique
     * keys restored by this rollback.
     */
    private function assertRollbackHasNoConflicts(): void
    {
        $conflictingInterviews = $this->countDuplicateApplications('recruitment_interviews');
        $conflictingEvaluations = $this->countDuplicateApplications('recruitment_evaluations');

        if ($conflictingInterviews === 0 && $conflictingEvaluations === 0) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Rollback of 2026_10_08_000001_secondary_interview_support aborted: %d recruitment application(s) have more than one row in recruitment_interviews and %d have more than one row in recruitment_evaluations. '
            .'Restoring unique(recruitment_application_id) would reject those rows. '
            .'This migration applied no schema changes; migrations newer than it were already rolled back by this command, so re-check the migrations table before retrying. '
            .'Export the extra interviews/evaluations, delete or reassign them, then retry the rollback.',
            $conflictingInterviews,
            $conflictingEvaluations
        ));
    }

    /** Count recruitment applications owning more than one row in the given table. */
    private function countDuplicateApplications(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table($table)
                    ->select('recruitment_application_id')
                    ->groupBy('recruitment_application_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicate_applications'
            )
            ->count();
    }

    /** Check a foreign key through information_schema (MySQL path only). */
    private function mysqlForeignKeyExists(string $table, string $foreignKey): bool
    {
        return DB::selectOne(
            "SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ?",
            [Schema::getConnection()->getDatabaseName(), $table, $foreignKey]
        ) !== null;
    }
};
