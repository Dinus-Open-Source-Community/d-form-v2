<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah relasi broadcast ke email_logs untuk keterlacakan kiriman.
     */
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            $table->uuid('broadcast_id')->nullable()->after('recruitment_application_id');
            $table->foreign('broadcast_id')->references('id')->on('broadcasts')->nullOnDelete();
        });
    }

    /**
     * Hapus relasi broadcast dari email_logs (rollback Task 3a).
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            $table->dropForeign(['broadcast_id']);
            $table->dropColumn('broadcast_id');
        });
    }
};
