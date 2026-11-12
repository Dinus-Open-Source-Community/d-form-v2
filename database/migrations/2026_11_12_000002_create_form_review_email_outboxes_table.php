<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('form_review_email_outboxes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_answer_id');
            $table->enum('action', ['accepted', 'rejected']);
            $table->string('delivery_key', 96)->unique();
            $table->enum('status', ['pending', 'dispatching', 'enqueuing', 'queued', 'sending', 'sent', 'failed', 'manual_review'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('form_answer_id')
                ->references('id')
                ->on('form_answers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index(['status', 'available_at'], 'form_review_outboxes_recovery_index');
            $table->index(['status', 'queued_at'], 'form_review_outboxes_stale_queued_index');
            $table->index('form_answer_id', 'form_review_outboxes_form_answer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_review_email_outboxes');
    }
};
