<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('form_answer_id')->nullable();
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->string('recipient_email');
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('form_answer_id')
                ->references('id')
                ->on('form_answers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index('form_answer_id');
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
