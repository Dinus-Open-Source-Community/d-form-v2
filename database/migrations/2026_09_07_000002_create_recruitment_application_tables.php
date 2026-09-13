<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recruitment_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_period_id');
            $table->string('registration_number', 30)->unique();
            $table->string('tracking_token_hash');
            $table->string('full_name');
            $table->string('nim', 50);
            $table->unsignedTinyInteger('semester');
            $table->string('phone', 30);
            $table->string('personal_email');
            $table->string('student_email');
            $table->string('instagram_username', 100);
            $table->uuid('primary_division_id');
            $table->uuid('secondary_division_id')->nullable();
            $table->string('stage', 30)->default('submitted');
            $table->string('result', 20)->default('pending');
            $table->boolean('is_verified')->default(false);
            $table->boolean('revision_required')->default(false);
            $table->dateTime('submitted_at');
            $table->dateTime('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamps();

            $table->foreign('recruitment_period_id', 'rec_apps_period_fk')->references('id')->on('recruitment_periods')->cascadeOnDelete();
            $table->foreign('primary_division_id', 'rec_apps_pri_div_fk')->references('id')->on('recruitment_divisions');
            $table->foreign('secondary_division_id', 'rec_apps_sec_div_fk')->references('id')->on('recruitment_divisions')->nullOnDelete();
            $table->foreign('verified_by', 'rec_apps_verified_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by', 'rec_apps_cancelled_fk')->references('id')->on('users')->nullOnDelete();

            $table->unique(['recruitment_period_id', 'nim'], 'rec_apps_period_nim_uniq');
            $table->index(['recruitment_period_id', 'stage'], 'rec_apps_period_stage_idx');
            $table->index(['recruitment_period_id', 'result'], 'rec_apps_period_result_idx');
            $table->index(['recruitment_period_id', 'primary_division_id'], 'rec_apps_period_div_idx');
        });

        Schema::create('recruitment_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->unique();
            $table->string('cv_path', 500);
            $table->string('cv_original_name');
            $table->string('cv_mime', 100);
            $table->unsignedInteger('cv_size_bytes');
            $table->string('portfolio_type', 10);
            $table->string('portfolio_url', 500)->nullable();
            $table->string('portfolio_path', 500)->nullable();
            $table->string('portfolio_original_name')->nullable();
            $table->string('portfolio_mime', 100)->nullable();
            $table->unsignedInteger('portfolio_size_bytes')->nullable();
            $table->timestamps();

            $table->foreign('recruitment_application_id', 'rec_docs_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
        });

        Schema::create('recruitment_screenings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id');
            $table->string('decision', 30);
            $table->string('reason', 50)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('acted_by');
            $table->dateTime('acted_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('recruitment_application_id', 'rec_screen_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('acted_by', 'rec_screen_acted_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['recruitment_application_id', 'acted_at'], 'rec_screenings_app_acted_idx');
        });

        Schema::create('recruitment_correction_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id');
            $table->string('status', 20)->default('pending');
            $table->text('request_message');
            $table->text('review_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('recruitment_application_id', 'rec_corr_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'rec_corr_reviewed_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_correction_requests');
        Schema::dropIfExists('recruitment_screenings');
        Schema::dropIfExists('recruitment_documents');
        Schema::dropIfExists('recruitment_applications');
    }
};
