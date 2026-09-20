<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\ScreeningReason;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentScreening;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentScreeningTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Queue::fake();

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
            'revision_required' => false,
        ]);
    }

    public function test_staff_pass_application_moves_to_interview_and_queues_email(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $this->application))
            ->assertRedirect();

        $this->application->refresh();
        $this->assertSame(ApplicationStage::Interview, $this->application->stage);
        $this->assertFalse($this->application->revision_required);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job): bool {
            return $job->applicationId === $this->application->id
                && $job->templateKey === 'passed_screening';
        });
    }

    public function test_staff_revision_without_reason_returns_validation_error(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.revision', $this->application), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_staff_reject_without_reason_returns_validation_error(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.reject', $this->application), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_staff_revision_with_reason_sets_flag_and_queues_email(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.revision', $this->application), [
                'reason' => ScreeningReason::IncompleteData->value,
                'notes' => 'Lengkapi CV.',
                'sections' => ['data_diri', 'cv'],
            ])
            ->assertRedirect();

        $this->application->refresh();
        $this->assertSame(ApplicationStage::Screening, $this->application->stage);
        $this->assertTrue($this->application->revision_required);

        $screening = RecruitmentScreening::query()
            ->where('recruitment_application_id', $this->application->id)
            ->firstOrFail();
        $this->assertSame(['data_diri', 'cv'], $screening->sections);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job): bool {
            return $job->applicationId === $this->application->id
                && $job->templateKey === 'revision_required'
                && $job->revisionSections === ['data_diri', 'cv']
                && $job->revisionNotes === 'Lengkapi CV.';
        });
    }

    public function test_staff_revision_without_sections_returns_validation_error(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.revision', $this->application), [
                'reason' => ScreeningReason::IncompleteData->value,
                'notes' => 'Lengkapi CV.',
            ])
            ->assertSessionHasErrors('sections');
    }

    public function test_staff_revision_after_verification_succeeds(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.verify', $this->application))
            ->assertRedirect();

        $this->application->refresh();
        $this->assertTrue($this->application->is_verified);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.revision', $this->application), [
                'reason' => ScreeningReason::IncompleteData->value,
                'notes' => 'Lengkapi CV.',
                'sections' => ['data_diri', 'cv'],
            ])
            ->assertRedirect();

        $this->application->refresh();
        $this->assertTrue($this->application->revision_required);
        $this->assertTrue($this->application->is_verified);

        $screening = RecruitmentScreening::query()
            ->where('recruitment_application_id', $this->application->id)
            ->firstOrFail();
        $this->assertSame(['data_diri', 'cv'], $screening->sections);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job): bool {
            return $job->applicationId === $this->application->id
                && $job->templateKey === 'revision_required'
                && $job->revisionSections === ['data_diri', 'cv'];
        });
    }

    public function test_screening_decision_is_recorded_in_history(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $this->application));

        $this->assertDatabaseHas('recruitment_screenings', [
            'recruitment_application_id' => $this->application->id,
            'decision' => 'pass',
            'acted_by' => $this->staff->id,
        ]);

        $this->assertSame(1, RecruitmentScreening::query()->where('recruitment_application_id', $this->application->id)->count());
    }

    public function test_screening_decision_creates_activity_log_entry(): void
    {
        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.screening.pass', $this->application));

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'actor_id' => $this->staff->id,
            'action' => 'screening.pass',
        ]);

        $this->assertSame(1, RecruitmentActivityLog::query()->where('recruitment_application_id', $this->application->id)->count());
    }

    public function test_interviewer_cannot_screen_application(): void
    {
        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $this->actingAs($interviewer)
            ->post(route('dashboard.recruitment.applications.screening.pass', $this->application))
            ->assertForbidden();
    }

    public function test_staff_can_view_application_json_detail_and_period_applicant_list(): void
    {
        $this->actingAs($this->staff)
            ->getJson(route('dashboard.recruitment.periods.applications.show', [
                'period' => $this->application->recruitment_period_id,
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertJsonPath('application.id', $this->application->id);

        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.show', $this->application->recruitment_period_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('applications'));
    }
}
