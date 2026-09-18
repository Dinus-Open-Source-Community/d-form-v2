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
use App\Services\Recruitment\InterviewSchedulingService;
use App\Services\Scan\ScanStreamFeed;
use App\Support\RecruitmentQrPayload;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $staff,
            $session,
            [$application->id],
        );

        $this->actingAs($staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => RecruitmentQrPayload::encode($application->id),
            ])
            ->assertOk();

        $feed = app(\App\Services\Scan\ScanStreamFeed::class);

        $rows = $feed->since(null);

        $this->assertNotEmpty($rows);
        $this->assertSame(['id', 'ts', 'type', 'eventTitle', 'name', 'identifier', 'queueNumber'], array_keys($rows[0]));

        $this->assertSame(['event', 'recruitment'], array_column($rows, 'type'));

        $sorted = array_column($rows, 'ts');
        $ascending = $sorted;
        sort($ascending);
        $this->assertSame($ascending, $sorted);

        $this->assertSame($application->registration_number, $rows[1]['identifier']);
        $this->assertSame(1, $rows[1]['queueNumber']);
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
}
