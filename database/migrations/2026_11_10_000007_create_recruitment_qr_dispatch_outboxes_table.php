<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL DDL tidak transaksional: setelah CREATE sukses, migrasi bisa crash sebelum tercatat.
 * Guard ini membuat re-run menjadi no-op alih-alih gagal "table already exists".
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('recruitment_qr_dispatch_outboxes')) {
            return;
        }

        Schema::create('recruitment_qr_dispatch_outboxes', function (Blueprint $table): void {
            $table->uuid('email_log_id')->primary();
            $table->string('status', 16)->default('pending');
            $table->timestamp('available_at');
            $table->unsignedInteger('delay_seconds')->default(0);
            $table->unsignedInteger('dispatch_attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('email_log_id', 'recruitment_qr_outboxes_email_log_fk')
                ->references('id')
                ->on('email_logs')
                ->cascadeOnDelete();
            $table->index(['status', 'available_at'], 'recruitment_qr_outbox_status_available_idx');
            $table->index(['status', 'sent_at'], 'recruitment_qr_outbox_status_sent_idx');
            $table->index(['status', 'failed_at'], 'recruitment_qr_outbox_status_failed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_qr_dispatch_outboxes');
    }
};
