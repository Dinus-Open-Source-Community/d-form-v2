<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->string('interview_kind', 20)->default('primary');
            $table->dropUnique(['recruitment_application_id']);
        });

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->unique(['recruitment_application_id', 'interview_kind'], 'rec_interviews_app_kind_uniq');
        });

        DB::table('recruitment_interviews')
            ->whereNull('interview_kind')
            ->update(['interview_kind' => 'primary']);

        Schema::table('recruitment_evaluations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('save_count')->default(0);
            $table->dropUnique(['recruitment_application_id']);
        });

        Schema::table('recruitment_evaluations', function (Blueprint $table): void {
            $table->unique(['recruitment_interview_id'], 'rec_eval_interview_uniq');
        });

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
            $table->dropUnique(['recruitment_interview_id']);
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
