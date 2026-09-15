<?php

namespace Tests\Feature\Scan;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
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
}
