<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index FULLTEXT untuk pencarian acara di halaman publik `/events`.
 * LIKE '%kata%' memindai 1 juta baris (±4,3 detik); MATCH ... AGAINST memakai index ini.
 */
return new class () extends Migration {
    public function up(): void
    {
        // SQLite (dipakai test) tidak punya FULLTEXT; index hanya untuk MySQL.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->fullText(['title', 'location'], 'events_title_location_fulltext');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropFullText('events_title_location_fulltext');
        });
    }
};
