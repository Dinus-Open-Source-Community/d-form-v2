<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Http\Requests\Events\ShowEventReportRequest;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BE-P3-018 / BE-P3-019: query string laporan kehadiran event.
 *
 * `per_page` dibatasi allowlist (nilai lain jatuh ke 20, bukan 422) dan halaman
 * di luar `last_page` di-clamp ke halaman terakhir dengan response contract yang
 * sama (`current_page`, `last_page`, `total`, `per_page`, `data`).
 */
class EventLaporanPaginationRequestTest extends TestCase
{
    use RefreshDatabase;

    private const TOTAL_ATTENDANCES = 30;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Event + form dengan `$count` baris kehadiran berurutan waktu.
     */
    private function eventWithAttendances(int $count): Event
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'category' => 'rkt',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        $base = now()->startOfSecond()->subHour();

        for ($index = 0; $index < $count; $index++) {
            $answer = FormAnswer::factory()->create([
                'form_id' => $form->id,
            ]);

            EventAttendance::query()->create([
                'event_id' => $event->id,
                'form_answer_id' => $answer->id,
                'scanned_at' => $base->copy()->addMinute($index),
            ]);
        }

        return $event;
    }

    public function test_per_page_from_allowlist_is_applied(): void
    {
        $event = $this->eventWithAttendances(self::TOTAL_ATTENDANCES);
        $admin = $this->admin();

        foreach (ShowEventReportRequest::PER_PAGE_OPTIONS as $perPage) {
            $this->actingAs($admin)
                ->get(route('dashboard.events.laporan', ['event' => $event, 'per_page' => $perPage]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('eventReporting.attendanceLog.per_page', $perPage)
                    ->where('eventReporting.attendanceLog.current_page', 1)
                    ->where('eventReporting.attendanceLog.total', self::TOTAL_ATTENDANCES)
                    ->has('eventReporting.attendanceLog.data', min($perPage, self::TOTAL_ATTENDANCES)));
        }
    }

    public function test_invalid_or_absent_per_page_falls_back_to_default(): void
    {
        $event = $this->eventWithAttendances(self::TOTAL_ATTENDANCES);
        $admin = $this->admin();

        $cases = [
            'absent' => null,
            'not numeric' => 'abc',
            'zero' => '0',
            'negative' => '-5',
            'above maximum' => '99999',
            'not in allowlist' => '7',
        ];

        foreach ($cases as $label => $value) {
            $this->actingAs($admin)
                ->get(route('dashboard.events.laporan', $value === null
                    ? $event
                    : ['event' => $event, 'per_page' => $value]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('eventReporting.attendanceLog.per_page', ShowEventReportRequest::DEFAULT_PER_PAGE)
                    ->has('eventReporting.attendanceLog.data', ShowEventReportRequest::DEFAULT_PER_PAGE));
        }
    }

    public function test_page_anomalies_resolve_to_sane_pages(): void
    {
        $event = $this->eventWithAttendances(self::TOTAL_ATTENDANCES);
        $admin = $this->admin();

        foreach (['0', '-5', 'abc'] as $value) {
            $this->actingAs($admin)
                ->get(route('dashboard.events.laporan', ['event' => $event, 'page' => $value]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('eventReporting.attendanceLog.current_page', 1)
                    ->where('eventReporting.attendanceLog.last_page', 2)
                    ->has('eventReporting.attendanceLog.data', 20));
        }

        // 30 baris / 8 per halaman → halaman terakhir (4) berisi 6 baris.
        $this->actingAs($admin)
            ->get(route('dashboard.events.laporan', [
                'event' => $event,
                'per_page' => 8,
                'page' => '999999',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('eventReporting.attendanceLog.current_page', 4)
                ->where('eventReporting.attendanceLog.last_page', 4)
                ->where('eventReporting.attendanceLog.per_page', 8)
                ->where('eventReporting.attendanceLog.total', self::TOTAL_ATTENDANCES)
                ->has('eventReporting.attendanceLog.data', 6));
    }
}
