<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Enums\Recruitment\MembershipType;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentDocument;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentPermissionTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_A = 'permission-test-token-applicant-a';

    private const TOKEN_B = 'permission-test-token-applicant-b';

    private User $staff;

    private User $admin;

    private User $interviewer;

    private User $member;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        Queue::fake();
        Storage::fake('local');

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->member = User::factory()->create();
        $this->member->assignRole('member');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);
    }

    private function application(string $suffix, string $token): RecruitmentApplication
    {
        return RecruitmentApplication::factory()
            ->for($this->period, 'period')
            ->withTrackingToken($token)
            ->create([
                'registration_number' => 'OPREC-2026-P'.$suffix,
                'primary_division_id' => $this->programming->id,
                'stage' => ApplicationStage::Submitted,
                'result' => ApplicationResult::Pending,
            ]);
    }

    private function assignedApplication(string $suffix = '1'): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-E'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $this->staff,
            $session,
            [$application->id],
        );

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update(['interviewer_id' => $this->interviewer->id]);

        $cvPath = 'recruitment/cv/test-'.$suffix.'.pdf';
        Storage::disk('local')->put($cvPath, 'fake pdf content');

        RecruitmentDocument::query()->create([
            'recruitment_application_id' => $application->id,
            'cv_path' => $cvPath,
            'cv_original_name' => 'cv.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 120,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://example.com',
        ]);

        return $application->fresh(['document', 'interview']);
    }

    public function test_p01_member_cannot_access_recruitment_dashboard(): void
    {
        $this->actingAs($this->member)
            ->get(route('dashboard.recruitment.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_p02_staff_can_access_recruitment_dashboard(): void
    {
        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk();
    }

    public function test_p03_interviewer_cannot_screen_application(): void
    {
        $application = $this->application('01', self::TOKEN_A);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.applications.screening.pass', $application))
            ->assertForbidden();
    }

    public function test_p04_interviewer_cannot_decide_final(): void
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'stage' => ApplicationStage::FinalReview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
            ])
            ->assertForbidden();
    }

    public function test_p05_staff_can_decide_final(): void
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'stage' => ApplicationStage::FinalReview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Member->value,
                'final_division_id' => $this->programming->id,
            ])
            ->assertRedirect();
    }

    public function test_p06_staff_cannot_manage_periods(): void
    {
        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.user.can_manage_recruitment_periods', false));

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.store'), [
                'name' => 'OpRec Forbidden',
            ])
            ->assertForbidden();
    }

    public function test_p07_admin_can_manage_periods(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard.recruitment.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('periods')
                ->where('auth.user.can_manage_recruitment_periods', true));
    }

    public function test_p08_interviewer_can_evaluate_assigned_interview(): void
    {
        $application = $this->assignedApplication('9');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.my-interviews.evaluate', $application), [
                'speaking_score' => 8,
                'technical_score' => 7,
                'attitude_score' => 9,
                'recommendation' => EvaluationRecommendation::Recommended->value,
                'notes' => 'Solid candidate.',
            ])
            ->assertRedirect();
    }

    public function test_p09_interviewer_cannot_download_cv_unassigned(): void
    {
        $application = $this->assignedApplication('10');

        $other = User::factory()->create();
        $other->assignRole('recruitment-interviewer');

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $other->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        $this->actingAs($other)
            ->get(route('dashboard.recruitment.applications.documents.download', [
                'application' => $application,
                'type' => 'cv',
            ]))
            ->assertForbidden();
    }

    public function test_p10_interviewer_can_download_cv_assigned(): void
    {
        $application = $this->assignedApplication('11');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.applications.documents.download', [
                'application' => $application,
                'type' => 'cv',
            ]))
            ->assertOk();
    }

    public function test_p11_tracking_session_isolated_per_applicant(): void
    {
        $applicationA = $this->application('A', self::TOKEN_A);
        $applicationB = $this->application('B', self::TOKEN_B);

        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $applicationA->registration_number,
            'tracking_token' => self::TOKEN_B,
        ])->assertSessionHasErrors('credentials');

        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $applicationA->registration_number,
            'tracking_token' => self::TOKEN_A,
        ])->assertRedirect(route('open-recruitment.track.show'));

        $this->get(route('open-recruitment.track.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tracking.application.registration_number', $applicationA->registration_number));

        $this->assertNotSame($applicationA->registration_number, $applicationB->registration_number);
    }
}
