<?php

namespace Tests\Feature\Scan;

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
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalScanFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    private function scheduleApplicant(string $suffix): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-0000'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $this->staff,
            $this->session,
            [$application->id],
        );

        return $application->fresh(['interview']);
    }

    private function checkIn(RecruitmentApplication $application): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $application->registration_number,
            ])
            ->assertOk();
    }

    public function test_feed_returns_rows_and_ts_cursor_then_resumes_without_replay(): void
    {
        $application = $this->scheduleApplicant('1');
        $this->checkIn($application);

        $first = $this->actingAs($this->staff)
            ->getJson(route('dashboard.scan.feed'))
            ->assertOk();

        $rows = $first->json('rows');
        $this->assertNotEmpty($rows);
        $this->assertSame(
            ['id', 'ts', 'type', 'eventTitle', 'name', 'identifier', 'queueNumber'],
            array_keys($rows[0]),
        );

        $cursor = $first->json('cursor');
        $this->assertIsString($cursor);
        $this->assertSame($rows[array_key_last($rows)]['ts'], $cursor);
        $this->assertStringNotContainsString('evt:', $cursor);
        $this->assertStringNotContainsString('rec:', $cursor);

        $resume = $this->actingAs($this->staff)
            ->getJson(route('dashboard.scan.feed', ['since' => $cursor]))
            ->assertOk();

        $this->assertSame([], $resume->json('rows'));
        $this->assertSame($cursor, $resume->json('cursor'));
    }

    public function test_feed_returns_event_attendance_row(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create(['title' => 'Feed Event']);
        $form = Form::factory()->create(['event_id' => $event->id]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);
        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $admin->id,
            'scanned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.scan.feed'))
            ->assertOk()
            ->assertJsonPath('rows.0.type', 'event')
            ->assertJsonPath('rows.0.eventTitle', 'Feed Event');
    }

    public function test_store_and_feed_oprec_event_title_are_identical(): void
    {
        $application = $this->scheduleApplicant('2');

        $store = $this->actingAs($this->staff)
            ->postJson(route('dashboard.scan.store'), [
                'raw' => $application->registration_number,
            ])
            ->assertOk();

        $feed = $this->actingAs($this->staff)
            ->getJson(route('dashboard.scan.feed'))
            ->assertOk();

        $row = collect($feed->json('rows'))->firstWhere('type', 'recruitment');
        $this->assertNotNull($row);

        $storeTitle = $store->json('eventTitle');
        $this->assertSame($storeTitle, $row['eventTitle']);
        $this->assertStringContainsString($this->programming->name, $storeTitle);
        $this->assertStringNotContainsString($application->registration_number, $storeTitle);
    }

    public function test_feed_queue_snapshot_reports_live_oprec_queue(): void
    {
        $first = $this->scheduleApplicant('3');
        $second = $this->scheduleApplicant('4');
        $third = $this->scheduleApplicant('5');

        $this->checkIn($first);
        $this->checkIn($second);
        $this->checkIn($third);

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.recruitment.queue.call-next', $this->session))
            ->assertOk();

        $response = $this->actingAs($this->staff)
            ->getJson(route('dashboard.scan.feed'))
            ->assertOk()
            ->assertJsonPath('queue.0.sessionId', $this->session->id)
            ->assertJsonPath('queue.0.nowServing.queueNumber', 1)
            ->assertJsonPath('queue.0.waitingCount', 2)
            ->assertJsonCount(2, 'queue.0.waiting')
            ->assertJsonPath('queue.0.waiting.0.queueNumber', 2)
            ->assertJsonPath('queue.0.waiting.1.queueNumber', 3);

        $this->assertSame($first->fresh()->full_name, $response->json('queue.0.nowServing.name'));
        $this->assertStringContainsString($this->programming->name, $response->json('queue.0.label'));
    }

    public function test_feed_queue_omits_active_session_without_check_in(): void
    {
        $this->actingAs($this->staff)
            ->getJson(route('dashboard.scan.feed'))
            ->assertOk()
            ->assertJsonCount(0, 'queue');
    }

    public function test_feed_queue_only_includes_sessions_with_queue_entries(): void
    {
        $emptySession = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '13:00:00',
            'ends_at' => '15:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'B202',
            'is_active' => true,
        ]);

        $application = $this->scheduleApplicant('6');
        $this->checkIn($application);

        $response = $this->actingAs($this->staff)
            ->getJson(route('dashboard.scan.feed'))
            ->assertOk()
            ->assertJsonCount(1, 'queue')
            ->assertJsonPath('queue.0.sessionId', $this->session->id)
            ->assertJsonPath('queue.0.nowServing', null)
            ->assertJsonPath('queue.0.waitingCount', 1);

        $this->assertNotContains($emptySession->id, array_column($response->json('queue'), 'sessionId'));
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson(route('dashboard.scan.feed'))->assertUnauthorized();
    }

    public function test_member_is_forbidden(): void
    {
        $this->actingAs($this->member())
            ->getJson(route('dashboard.scan.feed'))
            ->assertForbidden();
    }
}
