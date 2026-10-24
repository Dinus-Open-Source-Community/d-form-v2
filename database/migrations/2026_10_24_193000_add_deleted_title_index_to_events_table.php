<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar event admin memakai `ORDER BY title` sebagai default; tanpa index ini
 * MySQL melakukan filesort atas 1 juta baris (8–10 detik saat cache dingin).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->index(['deleted_at', 'title'], 'events_deleted_title_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_deleted_title_index');
        });
    }
};
