<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recruitment_interview_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_period_id');
            $table->uuid('recruitment_division_id');
            $table->date('session_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('location');
            $table->string('room', 100);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('recruitment_period_id', 'rec_int_sess_period_fk')->references('id')->on('recruitment_periods')->cascadeOnDelete();
            $table->foreign('recruitment_division_id', 'rec_int_sess_div_fk')->references('id')->on('recruitment_divisions')->cascadeOnDelete();
            $table->index(['recruitment_period_id', 'session_date'], 'rec_int_sess_period_date_idx');
            $table->index(['recruitment_division_id', 'session_date'], 'rec_int_sess_div_date_idx');
        });

        Schema::create('recruitment_interviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->unique();
            $table->uuid('recruitment_interview_session_id');
            $table->uuid('interviewer_id');
            $table->dateTime('scheduled_at');
            $table->string('location');
            $table->string('room', 100);
            $table->string('status', 30)->default('scheduled');
            $table->dateTime('reminder_h1_sent_at')->nullable();
            $table->dateTime('reminder_h2_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('recruitment_application_id', 'rec_interviews_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('recruitment_interview_session_id', 'rec_interviews_sess_fk')->references('id')->on('recruitment_interview_sessions')->cascadeOnDelete();
            $table->foreign('interviewer_id', 'rec_interviews_iv_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['interviewer_id', 'scheduled_at'], 'rec_interviews_iv_sched_idx');
            $table->index(['recruitment_interview_session_id', 'status'], 'rec_interviews_sess_stat_idx');
        });

        Schema::create('recruitment_attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->unique();
            $table->uuid('recruitment_interview_session_id');
            $table->string('method', 20);
            $table->dateTime('checked_in_at');
            $table->uuid('checked_in_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('recruitment_application_id', 'rec_attendance_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('recruitment_interview_session_id', 'rec_attendance_sess_fk')->references('id')->on('recruitment_interview_sessions')->cascadeOnDelete();
            $table->foreign('checked_in_by', 'rec_attendance_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['recruitment_interview_session_id', 'checked_in_at'], 'rec_attendance_sess_check_idx');
        });

        Schema::create('recruitment_queue_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->unique();
            $table->uuid('recruitment_interview_session_id');
            $table->unsignedInteger('queue_number');
            $table->string('status', 20)->default('waiting');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('recruitment_application_id', 'rec_queue_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('recruitment_interview_session_id', 'rec_queue_sess_fk')->references('id')->on('recruitment_interview_sessions')->cascadeOnDelete();
            $table->unique(['recruitment_interview_session_id', 'queue_number'], 'rec_queue_sess_num_uniq');
            $table->index(['recruitment_interview_session_id', 'status', 'queue_number'], 'rec_queue_sess_stat_num_idx');
        });

        Schema::create('recruitment_evaluations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->unique();
            $table->uuid('recruitment_interview_id');
            $table->unsignedTinyInteger('speaking_score');
            $table->unsignedTinyInteger('technical_score');
            $table->unsignedTinyInteger('attitude_score');
            $table->string('recommendation', 20);
            $table->text('notes')->nullable();
            $table->uuid('evaluated_by');
            $table->dateTime('evaluated_at');
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->foreign('recruitment_application_id', 'rec_eval_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('recruitment_interview_id', 'rec_eval_interview_fk')->references('id')->on('recruitment_interviews')->cascadeOnDelete();
            $table->foreign('evaluated_by', 'rec_eval_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('recruitment_final_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->unique();
            $table->string('membership_type', 10)->nullable();
            $table->uuid('final_division_id')->nullable();
            $table->text('internal_reason')->nullable();
            $table->text('public_message')->nullable();
            $table->uuid('decided_by');
            $table->dateTime('decided_at');
            $table->timestamps();

            $table->foreign('recruitment_application_id', 'rec_final_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('final_division_id', 'rec_final_div_fk')->references('id')->on('recruitment_divisions')->nullOnDelete();
            $table->foreign('decided_by', 'rec_final_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('recruitment_feedbacks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->unique();
            $table->uuid('recruitment_period_id');
            $table->unsignedTinyInteger('rating_registration_ease');
            $table->unsignedTinyInteger('rating_info_clarity');
            $table->unsignedTinyInteger('rating_tracking_ease');
            $table->unsignedTinyInteger('rating_interview_experience');
            $table->unsignedTinyInteger('rating_staff_service');
            $table->text('feedback_text')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('recruitment_application_id', 'rec_feedback_app_fk')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('recruitment_period_id', 'rec_feedback_period_fk')->references('id')->on('recruitment_periods')->cascadeOnDelete();
        });

        Schema::create('recruitment_activity_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('recruitment_application_id')->nullable();
            $table->uuid('recruitment_period_id')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->string('actor_type', 20);
            $table->string('action', 100);
            $table->string('entity_type', 100)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->foreign('recruitment_application_id', 'rec_activity_app_fk')->references('id')->on('recruitment_applications')->nullOnDelete();
            $table->foreign('recruitment_period_id', 'rec_activity_period_fk')->references('id')->on('recruitment_periods')->nullOnDelete();
            $table->foreign('actor_id', 'rec_activity_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['recruitment_application_id', 'created_at'], 'rec_activity_app_created_idx');
            $table->index(['recruitment_period_id', 'created_at'], 'rec_activity_period_created_idx');
            $table->index('action', 'rec_activity_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_activity_logs');
        Schema::dropIfExists('recruitment_feedbacks');
        Schema::dropIfExists('recruitment_final_decisions');
        Schema::dropIfExists('recruitment_evaluations');
        Schema::dropIfExists('recruitment_queue_entries');
        Schema::dropIfExists('recruitment_attendances');
        Schema::dropIfExists('recruitment_interviews');
        Schema::dropIfExists('recruitment_interview_sessions');
    }
};
