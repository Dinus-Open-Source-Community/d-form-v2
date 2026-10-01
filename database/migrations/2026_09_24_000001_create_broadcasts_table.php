<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('source');
            $table->uuid('event_id')->nullable();
            $table->uuid('period_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->integer('send_delay_seconds')->default(0);
            $table->string('status')->default('draft');
            $table->json('recipient_snapshot')->nullable();
            $table->integer('recipient_count')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            $table->foreign('period_id')->references('id')->on('recruitment_periods')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
