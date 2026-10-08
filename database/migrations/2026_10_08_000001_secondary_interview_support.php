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
        Schema::table('recruitment_evaluations', function (Blueprint $table): void {
            $table->dropUnique('rec_eval_interview_uniq');
            $table->dropColumn('save_count');
        });

        Schema::table('recruitment_evaluations', function (Blueprint $table): void {
            $table->unique('recruitment_application_id');
        });

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropUnique('rec_interviews_app_kind_uniq');
            $table->dropColumn('interview_kind');
        });

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->unique('recruitment_application_id');
        });
    }
};
