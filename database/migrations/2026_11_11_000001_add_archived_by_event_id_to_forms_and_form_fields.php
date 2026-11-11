<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-P2-017: record which event archived a form/field so the cascade restore
 * revives only the rows it archived itself.
 *
 * Only rows cascade-archived by that event are restored; rows archived before
 * this column existed (marker NULL) stay archived. An event forceDelete leaves
 * children whose marker points at the hard-deleted event id; cleanup of those
 * rows is a separate follow-up.
 *
 * Nullable column at the end of the table: metadata-only for MySQL 8 /
 * MariaDB 10.11 (instant ADD COLUMN), so no rewrite of the domain tables.
 * No index: the cascade filters by the existing `event_id`/`form_id` indexes.
 */
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
