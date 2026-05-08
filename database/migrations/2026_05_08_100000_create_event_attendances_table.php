<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('event_attendances')) {
            return;
        }

        Schema::create('event_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('form_answer_id');
            $table->uuid('scanned_by_user_id')->nullable();
            $table->timestamp('scanned_at');

            $table->timestamps();

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('form_answer_id')
                ->references('id')
                ->on('form_answers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('scanned_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->unique(['event_id', 'form_answer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendances');
    }
};
