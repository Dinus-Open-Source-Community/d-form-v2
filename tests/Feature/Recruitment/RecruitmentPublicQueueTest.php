<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\QueueStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentQueueEntry;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentPublicQueueTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $division;

    private RecruitmentInterviewSession $session;

    private RecruitmentApplication $sapto;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->division = RecruitmentDivision::factory()->create(['name' => 'Pemrograman']);

        $interviewer = User::factory()->create(['name' => 'Nama Interviewer Rahasia']);

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $interviewer->id,
            'recruitment_division_id' => $this->division->id,
        ]);

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->division->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'R-PROGRAMMING',
            'is_active' => true,
        ]);

        $staff = User::factory()->create();

        $this->sapto = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->division->id,
            'registration_number' => 'OPREC-2026-00001',
            'full_name' => 'Sapto Prabowo',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants($staff, $this->session, [$this->sapto->id]);

        RecruitmentQueueEntry::query()->create([
            'recruitment_application_id' => $this->sapto->id,
            'recruitment_interview_session_id' => $this->session->id,
            'queue_number' => 1,
            'status' => QueueStatus::Called,
            'called_at' => now(),
        ]);

        $waitingApplicant = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->division->id,
            'registration_number' => 'OPREC-2026-00002',
            'full_name' => 'Budi Santoso',
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        RecruitmentQueueEntry::query()->create([
            'recruitment_application_id' => $waitingApplicant->id,
            'recruitment_interview_session_id' => $this->session->id,
            'queue_number' => 2,
            'status' => QueueStatus::Waiting,
        ]);
    }

    public function test_public_can_view_queue_display_without_authentication(): void
    {
        $this->get(route('recruitment.queue.show', $this->session))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('OpenRecruitment/QueueDisplay'));
    }

    public function test_public_snapshot_is_sanitized(): void
    {
        $response = $this->getJson(route('recruitment.queue.poll', $this->session));

        $response->assertOk()
            ->assertJsonPath('entries.0.queue_number', 1)
            ->assertJsonPath('entries.0.display_name', 'Sapto P.')
            ->assertJsonPath('current.display_name', 'Sapto P.')
            ->assertJsonPath('next.display_name', 'Budi S.')
            ->assertJsonMissingPath('entries.0.id')
            ->assertJsonMissingPath('entries.0.application')
            ->assertJsonMissingPath('entries.0.full_name');

        $payload = $response->getContent();

        $this->assertIsString($payload);
        $this->assertStringNotContainsString('Sapto Prabowo', $payload);
        $this->assertStringNotContainsString('Budi Santoso', $payload);
        $this->assertStringNotContainsString('OPREC-2026-00001', $payload);
        $this->assertStringNotContainsString('OPREC-2026-00002', $payload);
        $this->assertStringNotContainsString($this->sapto->id, $payload);
        $this->assertStringNotContainsString('Nama Interviewer Rahasia', $payload);
        $this->assertStringNotContainsString('full_name', $payload);
        $this->assertStringNotContainsString('registration_number', $payload);
    }

    public function test_inactive_session_is_not_found(): void
    {
        $this->session->update(['is_active' => false]);

        $this->get(route('recruitment.queue.show', $this->session))->assertNotFound();
        $this->get(route('recruitment.queue.poll', $this->session))->assertNotFound();
    }

    public function test_poll_endpoint_returns_json_snapshot(): void
    {
        $this->getJson(route('recruitment.queue.poll', $this->session))
            ->assertOk()
            ->assertJsonStructure([
                'session' => ['name', 'division', 'room', 'time'],
                'entries' => [['queue_number', 'display_name', 'division', 'room', 'status', 'status_label', 'called_at']],
                'current',
                'next',
                'stats' => ['waiting', 'called', 'completed', 'total'],
            ])
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.called', 1)
            ->assertJsonPath('stats.waiting', 1);
    }

    public function test_public_can_list_queue_sessions_without_authentication(): void
    {
        $this->get(route('recruitment.queue.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Komponen Vue milik lane paralel; cukup assert nama komponennya.
                ->component('OpenRecruitment/QueueIndex', false)
                ->has('sessions', 1)
                ->where('sessions.0.id', $this->session->id)
                ->where('sessions.0.name', 'Sesi Pemrograman — '.now()->translatedFormat('d M Y'))
                ->where('sessions.0.division', 'Pemrograman')
                ->where('sessions.0.room', 'R-PROGRAMMING')
                ->where('sessions.0.time', '09:00–12:00')
                ->where('sessions.0.board_url', route('recruitment.queue.show', $this->session))
                ->etc());
    }

    public function test_queue_session_index_only_lists_active_sessions(): void
    {
        RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->division->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '13:00:00',
            'ends_at' => '15:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'R-INACTIVE',
            'is_active' => false,
        ]);

        $this->get(route('recruitment.queue.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/QueueIndex', false)
                ->has('sessions', 1)
                ->where('sessions.0.id', $this->session->id)
                ->etc());
    }

    public function test_queue_session_index_does_not_leak_sensitive_data(): void
    {
        $response = $this->get(route('recruitment.queue.index'));

        $response->assertOk();

        $payload = $response->getContent();

        $this->assertIsString($payload);
        $this->assertStringNotContainsString('Sapto Prabowo', $payload);
        $this->assertStringNotContainsString('Budi Santoso', $payload);
        $this->assertStringNotContainsString('OPREC-2026-00001', $payload);
        $this->assertStringNotContainsString('OPREC-2026-00002', $payload);
        $this->assertStringNotContainsString($this->sapto->id, $payload);
        $this->assertStringNotContainsString('Nama Interviewer Rahasia', $payload);
        $this->assertStringNotContainsString('full_name', $payload);
        $this->assertStringNotContainsString('registration_number', $payload);
    }
}
