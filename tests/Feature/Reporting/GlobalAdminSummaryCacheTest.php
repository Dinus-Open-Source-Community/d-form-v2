<?php

namespace Tests\Feature\Reporting;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Event\EventCache;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ringkasan global admin di-cache singkat; dua pemanggilan berurutan hanya
 * boleh menjalankan satu set agregat.
 */
class GlobalAdminSummaryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_dua_pemanggilan_hanya_menjalankan_satu_set_query(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create(['form_id' => $form->id]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_at' => now(),
        ]);

        $query = app(EventReportingQuery::class);

        DB::enableQueryLog();
        $first = $query->globalAdminSummary();
        $firstCallQueries = count(DB::getQueryLog());
        $second = $query->globalAdminSummary();
        $bothCallsQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame([
            'total_events' => 1,
            'total_submissions' => 1,
            'total_attendance_records' => 1,
        ], $first);
        $this->assertSame($first, $second);
        $this->assertSame($firstCallQueries, $bothCallsQueries);
    }

    public function test_buster_statistik_membuang_ringkasan_tersimpan(): void
    {
        Event::factory()->count(2)->create();

        $query = app(EventReportingQuery::class);
        $this->assertSame(2, $query->globalAdminSummary()['total_events']);

        Event::factory()->create();
        EventCache::bump(EventCache::BUSTER_STATS);

        $this->assertSame(3, $query->globalAdminSummary()['total_events']);
    }
}
