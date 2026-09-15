<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\MembershipType;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentFinalDecision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentInterviewVariableBuilder;
use App\Services\Recruitment\RecruitmentQrPngGenerator;
use App\Services\Recruitment\TrackingPresenter;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentFinalSelectionTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKING_TOKEN = 'final-selection-tracking-token-abc';

    private User $staff;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentDivision $dataDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Queue::fake();

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->dataDivision = RecruitmentDivision::query()->where('code', 'data')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();
    }

    private function applicationInFinalReview(string $suffix = '1'): RecruitmentApplication
    {
        return RecruitmentApplication::factory()
            ->for($this->period, 'period')
            ->withTrackingToken(self::TRACKING_TOKEN)
            ->create([
                'registration_number' => 'OPREC-2026-F'.$suffix,
                'primary_division_id' => $this->programming->id,
                'stage' => ApplicationStage::FinalReview,
                'result' => ApplicationResult::Pending,
            ]);
    }

    public function test_accept_as_aa_with_division(): void
    {
        $application = $this->applicationInFinalReview();

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->dataDivision->id,
            ])
            ->assertRedirect(route('dashboard.recruitment.applications.show', $application));

        $application->refresh();

        $this->assertSame(ApplicationStage::Completed, $application->stage);
        $this->assertSame(ApplicationResult::Accepted, $application->result);

        $decision = RecruitmentFinalDecision::query()
            ->where('recruitment_application_id', $application->id)
            ->first();

        $this->assertNotNull($decision);
        $this->assertSame(MembershipType::Aa->value, $decision->membership_type);
        $this->assertSame($this->dataDivision->id, $decision->final_division_id);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'final_accepted';
        });
    }

    public function test_accept_as_member_with_division(): void
    {
        $application = $this->applicationInFinalReview('2');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Member->value,
                'final_division_id' => $this->programming->id,
            ])
            ->assertRedirect();

        $decision = RecruitmentFinalDecision::query()
            ->where('recruitment_application_id', $application->id)
            ->first();

        $this->assertSame(MembershipType::Member->value, $decision?->membership_type);
    }

    public function test_accept_without_division_returns_validation_error(): void
    {
        $application = $this->applicationInFinalReview('3');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
            ])
            ->assertSessionHasErrors('final_division_id');
    }

    public function test_reject_without_reason_returns_validation_error(): void
    {
        $application = $this->applicationInFinalReview('4');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.reject', $application), [])
            ->assertSessionHasErrors(['internal_reason', 'public_message']);
    }

    public function test_reject_with_internal_and_public_message_saved_separately(): void
    {
        $application = $this->applicationInFinalReview('5');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.reject', $application), [
                'internal_reason' => 'Skor interview di bawah standar tim.',
                'public_message' => 'Terima kasih sudah mengikuti OpenRecruitment DOSCOM.',
            ])
            ->assertRedirect();

        $application->refresh();

        $this->assertSame(ApplicationResult::Rejected, $application->result);

        $decision = RecruitmentFinalDecision::query()
            ->where('recruitment_application_id', $application->id)
            ->first();

        $this->assertSame('Skor interview di bawah standar tim.', $decision?->internal_reason);
        $this->assertSame('Terima kasih sudah mengikuti OpenRecruitment DOSCOM.', $decision?->public_message);
        $this->assertNull($decision?->membership_type);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'final_rejected';
        });
    }

    public function test_final_visible_on_tracking_with_public_fields_only(): void
    {
        $application = $this->applicationInFinalReview('6');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.reject', $application), [
                'internal_reason' => 'INTERNAL ONLY REASON',
                'public_message' => 'Pesan aman untuk applicant.',
            ]);

        $application->refresh()->load('finalDecision.finalDivision');

        $tracking = app(TrackingPresenter::class)->present($application);

        $this->assertSame('rejected', $tracking['final']['result'] ?? null);
        $this->assertSame('Pesan aman untuk applicant.', $tracking['final']['public_message'] ?? null);

        $json = json_encode($tracking);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('INTERNAL ONLY REASON', $json);
    }

    public function test_result_email_queued_creates_email_log_on_send(): void
    {
        Mail::fake();

        $application = $this->applicationInFinalReview('7');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Member->value,
                'final_division_id' => $this->programming->id,
            ]);

        $job = new SendRecruitmentNotificationJob($application->fresh()->id, 'final_accepted');
        $job->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        );

        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $application->id,
            'recipient_email' => $application->personal_email,
            'notification_type' => 'recruitment_final_accepted',
            'status' => 'sent',
        ]);
    }

    public function test_final_decision_by_staff_is_allowed(): void
    {
        $application = $this->applicationInFinalReview('8');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->dataDivision->id,
            ])
            ->assertRedirect();
    }

    public function test_final_decision_by_interviewer_is_forbidden(): void
    {
        $application = $this->applicationInFinalReview('9');

        $this->actingAs($this->interviewer)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->dataDivision->id,
            ])
            ->assertForbidden();
    }
}
