<?php

namespace Tests\Feature\Dashboard\Events;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kontrak skema setelah halaman laporan dihapus: index 2 kolom `(event_id, scanned_at)`
 * sudah tidak ada, sedangkan index keyset 3 kolom tetap dipakai export scan.
 */
class EventAttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_INDEX = 'event_attendances_event_id_scanned_at_index';

    private const KEYSET_INDEX = 'event_attendances_event_id_scanned_at_id_index';

    private const EXPORT_ANSWER_INDEX = 'form_answers_form_id_created_at_id_index';

    public function test_drop_migration_removes_legacy_index_and_keeps_keyset_indexes(): void
    {
        $attendanceIndexes = collect(Schema::getIndexes('event_attendances'))->keyBy('name');

        self::assertFalse($attendanceIndexes->has(self::LEGACY_INDEX));
        self::assertSame(
            ['event_id', 'scanned_at', 'id'],
            $attendanceIndexes[self::KEYSET_INDEX]['columns'],
        );

        $answerIndexes = collect(Schema::getIndexes('form_answers'))->keyBy('name');

        self::assertSame(
            ['form_id', 'created_at', 'id'],
            $answerIndexes[self::EXPORT_ANSWER_INDEX]['columns'],
        );
    }

    public function test_drop_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_11_17_000001_drop_redundant_event_attendance_scanned_at_index.php');

        $migration->down();
        self::assertTrue(collect(Schema::getIndexes('event_attendances'))->keyBy('name')->has(self::LEGACY_INDEX));

        $migration->up();
        self::assertFalse(collect(Schema::getIndexes('event_attendances'))->keyBy('name')->has(self::LEGACY_INDEX));
    }
}
