<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BE-P2-017: record which event archived a form/field so the cascade restore revives only the rows it archived itself. */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('forms', 'archived_by_event_id')) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->uuid('archived_by_event_id')->nullable();
            });
        }

        if (! Schema::hasColumn('form_fields', 'archived_by_event_id')) {
            Schema::table('form_fields', function (Blueprint $table): void {
                $table->uuid('archived_by_event_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forms', 'archived_by_event_id')) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->dropColumn('archived_by_event_id');
            });
        }

        if (Schema::hasColumn('form_fields', 'archived_by_event_id')) {
            Schema::table('form_fields', function (Blueprint $table): void {
                $table->dropColumn('archived_by_event_id');
            });
        }
    }
};
