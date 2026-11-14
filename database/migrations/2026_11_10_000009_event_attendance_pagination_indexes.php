<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index pagination laporan & export event (BE-P2-025, BE-P2-035). */
return new class () extends Migration {
    private const ATTENDANCE_TABLE = 'event_attendances';

    private const ATTENDANCE_INDEX = 'event_attendances_event_id_scanned_at_id_index';

    private const ANSWER_TABLE = 'form_answers';

    private const ANSWER_INDEX = 'form_answers_form_id_created_at_id_index';

    public function up(): void
    {
        if (! Schema::hasIndex(self::ATTENDANCE_TABLE, ['event_id', 'scanned_at', 'id'])) {
            Schema::table(self::ATTENDANCE_TABLE, function (Blueprint $table): void {
                $table->index(['event_id', 'scanned_at', 'id'], self::ATTENDANCE_INDEX);
            });
        }

        if (! Schema::hasIndex(self::ANSWER_TABLE, ['form_id', 'created_at', 'id'])) {
            Schema::table(self::ANSWER_TABLE, function (Blueprint $table): void {
                $table->index(['form_id', 'created_at', 'id'], self::ANSWER_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::ATTENDANCE_TABLE, self::ATTENDANCE_INDEX)) {
            Schema::table(self::ATTENDANCE_TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::ATTENDANCE_INDEX);
            });
        }

        if (Schema::hasIndex(self::ANSWER_TABLE, self::ANSWER_INDEX)) {
            Schema::table(self::ANSWER_TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::ANSWER_INDEX);
            });
        }
    }
};
