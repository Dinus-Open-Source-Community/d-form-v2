<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recruitment_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('draft');
            $table->text('description')->nullable();
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->date('interview_starts_at')->nullable();
            $table->date('interview_ends_at')->nullable();
            $table->date('finalization_deadline_at')->nullable();
            $table->json('landing_content')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'rec_periods_status_idx');
            $table->index(['registration_opens_at', 'registration_closes_at'], 'rec_periods_reg_window_idx');
        });

        Schema::create('recruitment_divisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recruitment_interviewer_divisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('recruitment_division_id');
            $table->timestamps();

            $table->foreign('user_id', 'rec_iv_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('recruitment_division_id', 'rec_iv_div_fk')->references('id')->on('recruitment_divisions')->cascadeOnDelete();
            $table->unique(['user_id', 'recruitment_division_id'], 'rec_iv_user_div_uniq');
        });

        Schema::create('recruitment_registration_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_period_id')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->foreign('recruitment_period_id', 'rec_reg_seq_period_fk')->references('id')->on('recruitment_periods')->cascadeOnDelete();
        });

        Schema::create('recruitment_email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 50)->unique();
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_email_templates');
        Schema::dropIfExists('recruitment_registration_sequences');
        Schema::dropIfExists('recruitment_interviewer_divisions');
        Schema::dropIfExists('recruitment_divisions');
        Schema::dropIfExists('recruitment_periods');
    }
};
