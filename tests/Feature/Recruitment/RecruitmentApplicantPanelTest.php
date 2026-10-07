<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentApplicantPanelTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentPeriod $otherPeriod;

    private RecruitmentApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->period = RecruitmentPeriod::factory()->create();
        $this->otherPeriod = RecruitmentPeriod::factory()->create();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'full_name' => 'Budi Santoso',
        ]);
    }

    private function admin(array $permissions = []): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach ($permissions as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    public function test_application_param_memuat_detail_ter_scoped(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applicant_detail.id', $this->application->id)
                ->where('applicant_detail.full_name', 'Budi Santoso'));
    }

    public function test_application_param_lintas_periode_ditolak_404(): void
    {
        $foreign = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->otherPeriod->id,
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => $foreign->id,
            ]))
            ->assertNotFound();
    }

    public function test_application_param_tidak_ditemukan_404(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => '11111111-1111-4111-8111-111111111111',
            ]))
            ->assertNotFound();
    }

    public function test_application_param_bukan_uuid_diabaikan_tanpa_error(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => 'bukan-uuid',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('applicant_detail'));
    }

    public function test_tanpa_application_param_tidak_ada_payload_detail(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 1)
                ->missing('applicant_detail'));
    }

    public function test_tanpa_permission_list_tidak_ada_payload_detail(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('recruitment.dashboard.view');
        $viewer->givePermissionTo('recruitment.periods.view');

        $this->actingAs($viewer)
            ->get(route('dashboard.recruitment.periods.show', [
                'period' => $this->period->id,
                'tab' => 'peserta',
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('applicant_detail'));
    }

    public function test_json_detail_memuat_applicant_scoped_dengan_reason_options(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->getJson(route('dashboard.recruitment.periods.applications.show', [
                'period' => $this->period->id,
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertJsonPath('application.id', $this->application->id)
            ->assertJsonPath('application.full_name', 'Budi Santoso')
            ->assertJsonStructure([
                'application' => ['id', 'can_screen'],
                'screening_reason_options' => [['value', 'label']],
                'can_screen',
            ]);
    }

    public function test_json_detail_lintas_periode_ditolak_404(): void
    {
        $foreign = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->otherPeriod->id,
        ]);

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->getJson(route('dashboard.recruitment.periods.applications.show', [
                'period' => $this->period->id,
                'application' => $foreign->id,
            ]))
            ->assertNotFound();
    }

    public function test_json_detail_tidak_ditemukan_404(): void
    {
        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->getJson(route('dashboard.recruitment.periods.applications.show', [
                'period' => $this->period->id,
                'application' => '11111111-1111-4111-8111-111111111111',
            ]))
            ->assertNotFound();
    }

    public function test_json_detail_memuat_evaluasi_primary_dan_secondary(): void
    {
        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $evaluator = User::factory()->create();

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $programming->id,
            'secondary_division_id' => $data->id,
        ]);

        $primaryInterview = $this->interview($application->id, $this->makeSession($programming->id)->id, 'primary');
        $secondaryInterview = $this->interview($application->id, $this->makeSession($data->id)->id, 'secondary');

        $this->evaluation($application->id, $primaryInterview->id, $evaluator->id, 'recommended', 'Cocok untuk programming.');
        $this->evaluation($application->id, $secondaryInterview->id, $evaluator->id, 'not_recommended', 'Kurang cocok untuk data.');

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->getJson(route('dashboard.recruitment.periods.applications.show', [
                'period' => $this->period->id,
                'application' => $application->id,
            ]))
            ->assertOk()
            ->assertJsonPath('application.evaluations.primary.recommendation', 'recommended')
            ->assertJsonPath('application.evaluations.primary.division', $programming->name)
            ->assertJsonPath('application.evaluations.secondary.recommendation', 'not_recommended')
            ->assertJsonPath('application.evaluations.secondary.division', $data->name)
            ->assertJsonPath('application.evaluation.recommendation', 'recommended');
    }

    public function test_json_detail_secondary_kosong_bila_belum_diinterview(): void
    {
        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $data = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $evaluator = User::factory()->create();

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $programming->id,
            'secondary_division_id' => $data->id,
        ]);

        $primaryInterview = $this->interview($application->id, $this->makeSession($programming->id)->id, 'primary');
        $this->evaluation($application->id, $primaryInterview->id, $evaluator->id, 'recommended', 'Cocok untuk programming.');

        $this->actingAs($this->admin(['recruitment.periods.view', 'recruitment.applications.list']))
            ->getJson(route('dashboard.recruitment.periods.applications.show', [
                'period' => $this->period->id,
                'application' => $application->id,
            ]))
            ->assertOk()
            ->assertJsonPath('application.evaluations.primary.recommendation', 'recommended')
            ->assertJsonPath('application.evaluations.secondary', null);
    }

    private function makeSession(string $divisionId): RecruitmentInterviewSession
    {
        return RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $divisionId,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    private function interview(string $applicationId, string $sessionId, string $kind): RecruitmentInterview
    {
        return RecruitmentInterview::query()->create([
            'recruitment_application_id' => $applicationId,
            'recruitment_interview_session_id' => $sessionId,
            'interview_kind' => $kind,
            'scheduled_at' => now()->subHour(),
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'status' => InterviewStatus::Completed,
        ]);
    }

    private function evaluation(string $applicationId, string $interviewId, string $evaluatorId, string $recommendation, string $notes): void
    {
        RecruitmentEvaluation::query()->create([
            'recruitment_application_id' => $applicationId,
            'recruitment_interview_id' => $interviewId,
            'speaking_score' => 8,
            'technical_score' => 7,
            'attitude_score' => 9,
            'recommendation' => $recommendation,
            'notes' => $notes,
            'evaluated_by' => $evaluatorId,
            'evaluated_at' => now(),
        ]);
    }
}
