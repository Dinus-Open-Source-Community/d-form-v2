<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('du_redeem_issuances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_answer_id');
            $table->string('course_uid')->nullable();
            $table->string('status')->default('pending');
            $table->string('redeem_code')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique('form_answer_id');
            $table->index(['status', 'issued_at']);
            $table->foreign('form_answer_id')->references('id')->on('form_answers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('du_redeem_issuances');
    }
};
