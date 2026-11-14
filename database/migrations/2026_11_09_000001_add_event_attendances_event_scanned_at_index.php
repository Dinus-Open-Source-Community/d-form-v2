<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index for event-attendance log pagination. */
return new class () extends Migration {
    private const TABLE = 'event_attendances';

    private const INDEX = 'event_attendances_event_id_scanned_at_index';

    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['event_id', 'scanned_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
