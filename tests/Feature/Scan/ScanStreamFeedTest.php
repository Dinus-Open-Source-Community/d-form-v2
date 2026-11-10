<?php

namespace Tests\Feature\Scan;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\AttendanceService;
use App\Services\Scan\ScanStreamFeed;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScanStreamFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_since_returns_event_and_oprec_rows_sorted_asc(): void
    {
        // Arrange: one accepted event answer + EventAttendance row, one oprec
        // application + RecruitmentAttendance row (mirror Task 2 helpers and
        // RecruitmentAttendanceQueueTest::scheduleApplicant + staffCheckIn flow).
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);
        $participant = User::factory()->create(['email' => 'stream@example.test']);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'CHK-STR-001',
        ]);
        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $admin->id,
            'scanned_at' => now()->subMinutes(10),
        ]);

        $this->seed(RecruitmentDivisionSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();
        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $programming->id,
            'registration_number' => 'OPREC-2026-0000S1',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $programming->id,
        ]);

        app(AttendanceService::class)->checkIn(
            $session,
            $application,
            AttendanceMethod::Qr,
            $staff,
        );

        $feed = app(\App\Services\Scan\ScanStreamFeed::class);

        $rows = $feed->since(null);

        $this->assertNotEmpty($rows);
        $this->assertSame(['id', 'ts', 'type', 'eventTitle', 'name', 'identifier', 'queueNumber'], array_keys($rows[0]));

        $this->assertSame(['event', 'recruitment'], array_column($rows, 'type'));

        $sorted = array_column($rows, 'ts');
        $ascending = $sorted;
        sort($ascending);
        $this->assertSame($ascending, $sorted);

        $recRow = collect($rows)->firstWhere('type', 'recruitment');
        $this->assertNotNull($recRow);
        $this->assertSame($application->registration_number, $recRow['identifier']);
        $this->assertNull($recRow['queueNumber']);
        $this->assertSame('waiting', $recRow['interviewStatus']);
    }

    public function test_since_merges_interleaved_sources_without_gaps(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        $this->seed(RecruitmentDivisionSeeder::class);

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();
        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        // 30 event rows on even seconds, 30 recruitment rows on odd seconds:
        // perfectly interleaved so a per-source limit without over-fetch
        // would risk dropping valid rows from the merged slice.
        $base = now()->subHours(2)->startOfSecond();

        for ($i = 0; $i < 30; $i++) {
            $participant = User::factory()->create();
            $answer = FormAnswer::factory()->create([
                'form_id' => $form->id,
                'user_id' => $participant->id,
                'review_status' => FormAnswerReviewStatus::Accepted,
                'registration_code' => sprintf('CHK-BULK-E%03d', $i),
            ]);
            EventAttendance::query()->create([
                'event_id' => $event->id,
                'form_answer_id' => $answer->id,
                'scanned_by_user_id' => $admin->id,
                'scanned_at' => $base->copy()->addSeconds($i * 2),
            ]);
        }

        for ($j = 0; $j < 30; $j++) {
            $application = RecruitmentApplication::factory()->create([
                'recruitment_period_id' => $period->id,
                'primary_division_id' => $programming->id,
                'registration_number' => sprintf('OPREC-2026-B%04d', $j),
            ]);
            RecruitmentAttendance::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_session_id' => $session->id,
                'method' => AttendanceMethod::Qr,
                'checked_in_at' => $base->copy()->addSeconds($j * 2 + 1),
                'checked_in_by' => $admin->id,
            ]);
        }

        $expectedEvents = EventAttendance::all()->map(fn (EventAttendance $a): array => [
            'id' => 'evt:'.$a->form_answer_id.':'.$a->scanned_at->toIso8601String(),
            'ts' => $a->scanned_at->timestamp,
        ]);
        $expectedRecs = RecruitmentAttendance::all()->map(fn (RecruitmentAttendance $a): array => [
            'id' => 'rec:'.$a->id,
            'ts' => $a->checked_in_at->timestamp,
        ]);
        $expectedTop50 = $expectedEvents->merge($expectedRecs)
            ->sortBy('ts')->values()->take(50)->values();

        $rows = app(ScanStreamFeed::class)->since(null, 50);

        $this->assertCount(50, $rows);

        $actualTs = array_map(fn (array $row): int => (int) strtotime($row['ts']), $rows);
        $ascending = $actualTs;
        sort($ascending);
        $this->assertSame($ascending, $actualTs);

        $this->assertSame(
            $expectedTop50->pluck('ts')->sort()->values()->all(),
            collect($actualTs)->sort()->values()->all(),
        );
        $this->assertSame(
            $expectedTop50->pluck('id')->sort()->values()->all(),
            collect(array_column($rows, 'id'))->sort()->values()->all(),
        );
    }

    public function test_composite_cursor_delivers_every_row_sharing_one_timestamp(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $scannedAt = Carbon::parse('2026-11-10 09:00:00');

        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        for ($index = 1; $index <= 3; $index++) {
            $participant = User::factory()->create();
            $answer = FormAnswer::factory()->create([
                'form_id' => $form->id,
                'user_id' => $participant->id,
                'review_status' => FormAnswerReviewStatus::Accepted,
                'registration_code' => sprintf('CHK-TIE-%03d', $index),
            ]);
            EventAttendance::query()->create([
                'event_id' => $event->id,
                'form_answer_id' => $answer->id,
                'scanned_by_user_id' => $admin->id,
                'scanned_at' => $scannedAt->copy(),
            ]);
        }

        $this->seed(RecruitmentDivisionSeeder::class);

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();
        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $programming->id,
            'session_date' => $scannedAt->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        for ($index = 1; $index <= 3; $index++) {
            $application = RecruitmentApplication::factory()->create([
                'recruitment_period_id' => $period->id,
                'primary_division_id' => $programming->id,
                'registration_number' => sprintf('OPREC-2026-TIE%03d', $index),
            ]);
            RecruitmentAttendance::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_session_id' => $session->id,
                'method' => AttendanceMethod::Qr,
                'checked_in_at' => $scannedAt->copy(),
                'checked_in_by' => $admin->id,
            ]);
        }

        $feed = app(ScanStreamFeed::class);
        $delivered = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $feed->page($cursor, 2);
            $pages++;

            $this->assertLessThanOrEqual(2, count($page['rows']));

            foreach ($page['rows'] as $row) {
                $delivered[] = $row['id'];
            }

            $cursor = $page['cursor'];
        } while ($page['rows'] !== [] && $cursor !== null && $pages < 10);

        $expected = EventAttendance::query()->get()
            ->map(fn (EventAttendance $attendance): string => 'evt:'.$attendance->form_answer_id.':'.$attendance->scanned_at->toIso8601String())
            ->merge(RecruitmentAttendance::query()->get()->map(
                fn (RecruitmentAttendance $attendance): string => 'rec:'.$attendance->id
            ))
            ->all();

        $this->assertCount(6, $expected);
        $this->assertSame(6, count($delivered));
        $this->assertSame($delivered, array_values(array_unique($delivered)));
        $this->assertEqualsCanonicalizing($expected, $delivered);
    }

    public function test_page_cursor_is_opaque_and_resumes_after_the_last_row(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);
        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $admin->id,
            'scanned_at' => now()->subMinute(),
        ]);

        $first = app(ScanStreamFeed::class)->page(null, 50);

        $this->assertNotSame('', (string) $first['cursor']);
        $this->assertStringNotContainsString('evt:', (string) $first['cursor']);
        $this->assertStringNotContainsString('|', (string) $first['cursor']);

        $resumed = app(ScanStreamFeed::class)->page($first['cursor'], 50);

        $this->assertSame([], $resumed['rows']);
        $this->assertSame($first['cursor'], $resumed['cursor']);
    }

    /**
     * Regresi H1: blok baris yang tidak bisa dipetakan (event/form ter-soft-delete)
     * lebih besar dari `limit` tidak boleh membekukan feed. Kursor wajib maju
     * melewati baris yang sudah diambil lalu dibuang permanen.
     */
    public function test_page_advances_past_permanently_dropped_rows(): void
    {
        [$droppedEvent, $droppedForm] = $this->eventWithForm();
        $base = Carbon::parse('2026-11-10 08:00:00');

        for ($index = 0; $index < 6; $index++) {
            $this->attendance($droppedEvent, $droppedForm, $base->copy()->addSeconds($index), 'CHK-DROP-'.$index);
        }

        // Cascade soft-delete form milik event: baris di atas tidak bisa dipetakan lagi.
        $droppedEvent->delete();

        [$validEvent, $validForm] = $this->eventWithForm();
        $validTs = $base->copy()->addMinutes(5);
        $first = $this->attendance($validEvent, $validForm, $validTs, 'CHK-OK-1');
        $second = $this->attendance($validEvent, $validForm, $validTs->copy()->addSecond(), 'CHK-OK-2');

        $feed = app(ScanStreamFeed::class);
        $cursor = null;
        $delivered = [];

        for ($calls = 0; $calls < 12; $calls++) {
            $page = $feed->page($cursor, 2);

            foreach ($page['rows'] as $row) {
                $delivered[] = $row['id'];
            }

            if ($page['cursor'] === $cursor) {
                break;
            }

            $cursor = $page['cursor'];
        }

        $expected = [
            'evt:'.$first->form_answer_id.':'.$first->scanned_at->toIso8601String(),
            'evt:'.$second->form_answer_id.':'.$second->scanned_at->toIso8601String(),
        ];

        $this->assertSame($expected, $delivered);
        $this->assertSame($delivered, array_values(array_unique($delivered)));
    }

    /**
     * Baris yang hanya tertahan `limit` (detik yang sama) tidak boleh dilewati,
     * termasuk saat ada baris yang dibuang permanen di antara keduanya.
     */
    public function test_page_does_not_skip_rows_beyond_the_limit_window(): void
    {
        [$validEvent, $validForm] = $this->eventWithForm();
        [$droppedEvent, $droppedForm] = $this->eventWithForm();

        $ts = Carbon::parse('2026-11-10 09:00:00');

        $first = $this->attendance($validEvent, $validForm, $ts, 'CHK-LIM-1');
        $this->attendance($droppedEvent, $droppedForm, $ts->copy(), 'CHK-LIM-DROP');
        $droppedEvent->delete();
        $second = $this->attendance($validEvent, $validForm, $ts->copy(), 'CHK-LIM-2');

        $feed = app(ScanStreamFeed::class);
        $cursor = null;
        $delivered = [];

        for ($calls = 0; $calls < 12; $calls++) {
            $page = $feed->page($cursor, 1);

            foreach ($page['rows'] as $row) {
                $delivered[] = $row['id'];
            }

            if ($page['cursor'] === $cursor) {
                break;
            }

            $cursor = $page['cursor'];
        }

        $this->assertSame([
            'evt:'.$first->form_answer_id.':'.$first->scanned_at->toIso8601String(),
            'evt:'.$second->form_answer_id.':'.$second->scanned_at->toIso8601String(),
        ], $delivered);
    }

    /**
     * Jendela poll tanpa baris recruitment tidak boleh memicu `whereIn([])`
     * untuk tabel relasi pendukung.
     */
    public function test_empty_poll_does_not_query_recruitment_relations(): void
    {
        DB::enableQueryLog();
        $page = app(ScanStreamFeed::class)->page(null, 50);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertSame([], $page['rows']);
        $this->assertCount(2, $queries);
        $this->assertSame([], array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'recruitment_applications')
                || str_contains($sql, 'recruitment_interview_sessions'),
        )));
    }

    /**
     * Token/kursor dengan `ts` relatif atau kosong harus ditolak, bukan
     * dinormalisasi menjadi "sekarang" oleh Carbon.
     */
    public function test_cursor_timestamp_must_be_absolute(): void
    {
        $this->assertNull(ScanStreamFeed::normalizeCursor(''));
        $this->assertNull(ScanStreamFeed::normalizeCursor('now'));
        $this->assertNull(ScanStreamFeed::normalizeCursor('next monday'));
        $this->assertNull(ScanStreamFeed::normalizeCursor('not-a-cursor'));

        $token = ScanStreamFeed::normalizeCursor('2026-11-10T09:00:00+07:00');

        $this->assertIsString($token);

        $decoded = base64_decode(strtr((string) $token, '-_', '+/'), true);

        $this->assertIsString($decoded);
        $this->assertStringStartsWith('2026-11-10T09:00:00+07:00|', (string) $decoded);
    }

    /**
     * @return array{0: Event, 1: Form}
     */
    private function eventWithForm(): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        return [$event, $form];
    }

    private function attendance(Event $event, Form $form, Carbon $scannedAt, string $code): EventAttendance
    {
        $participant = User::factory()->create();
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => $code,
        ]);

        return EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => null,
            'scanned_at' => $scannedAt->copy(),
        ]);
    }

    /**
     * Service memotong `limit` sendiri (pertahanan berlapis) walau controller
     * juga memotong; nilai besar dari pemanggil lain tidak boleh memperbesar
     * jendela query tanpa batas.
     */
    public function test_page_clamps_limit_to_one_hundred(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scannedAt = Carbon::parse('2026-11-10 10:00:00');

        for ($index = 0; $index < 101; $index++) {
            $this->attendance($event, $form, $scannedAt->copy(), 'CHK-CLAMP-'.$index);
        }

        $rows = app(ScanStreamFeed::class)->page(null, 5000)['rows'];

        $this->assertCount(100, $rows);
    }
}
