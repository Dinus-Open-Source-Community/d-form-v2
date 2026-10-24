<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering index untuk SUM(registered_count) di dashboard: menghindari
 * full table scan pada tabel `events`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->index(['deleted_at', 'registered_count'], 'events_deleted_registered_count_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_deleted_registered_count_index');
        });
    }
};
