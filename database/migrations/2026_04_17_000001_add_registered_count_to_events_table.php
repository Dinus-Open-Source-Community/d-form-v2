<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'registered_count')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedSmallInteger('registered_count')
                    ->default(0)
                    ->after('quota');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('events', 'registered_count')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('registered_count');
            });
        }
    }
};
