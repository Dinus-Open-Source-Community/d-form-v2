<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Status form (draft/published) — meniru pola `events.status`. */
return new class () extends Migration {
    private const INDEX_NAME = 'forms_event_id_status_index';

    public function up(): void
    {
        if (! Schema::hasColumn('forms', 'status')) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->string('status', 20)->default('published');
            });
        }

        if (! Schema::hasIndex('forms', self::INDEX_NAME)) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->index(['event_id', 'status'], self::INDEX_NAME);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('forms', self::INDEX_NAME)) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX_NAME);
            });
        }

        if (Schema::hasColumn('forms', 'status')) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
