<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_broadcasts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 180);
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('delay_min')->default(5);
            $table->unsignedInteger('delay_max')->default(15);
            $table->uuid('event_id')->nullable();
            $table->string('subject', 255)->nullable();
            $table->longText('content')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('datasets')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('event_id', 'email_broadcasts_event_fk')
                ->references('id')->on('events')->nullOnDelete();
            $table->foreign('created_by', 'email_broadcasts_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index('status', 'email_broadcasts_status_idx');
            $table->index('scheduled_at', 'email_broadcasts_scheduled_at_idx');
        });

        Schema::create('email_broadcast_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('broadcast_id');
            $table->string('name', 150)->nullable();
            $table->string('email', 255);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('broadcast_id', 'email_bc_recipients_broadcast_fk')
                ->references('id')->on('email_broadcasts')->cascadeOnDelete();
            $table->index(['broadcast_id', 'status'], 'email_bc_recipients_bc_status_idx');
            $table->index(['broadcast_id', 'email'], 'email_bc_recipients_bc_email_idx');
        });

        Schema::create('email_broadcast_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('broadcast_id');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->foreign('broadcast_id', 'email_bc_attachments_broadcast_fk')
                ->references('id')->on('email_broadcasts')->cascadeOnDelete();
            $table->index('broadcast_id', 'email_bc_attachments_bc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_broadcast_attachments');
        Schema::dropIfExists('email_broadcast_recipients');
        Schema::dropIfExists('email_broadcasts');
    }
};
