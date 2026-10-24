<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk query dashboard & daftar acara publik: semuanya memfilter
 * `deleted_at IS NULL` lalu mengurutkan/menghitung pada satu kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->index(['deleted_at', 'created_at'], 'events_deleted_created_index');
            $table->index(['deleted_at', 'status', 'start_date'], 'events_deleted_status_start_index');
            $table->index(['deleted_at', 'registration_end'], 'events_deleted_registration_end_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_deleted_created_index');
            $table->dropIndex('events_deleted_status_start_index');
            $table->dropIndex('events_deleted_registration_end_index');
        });
    }
};
