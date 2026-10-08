<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\MembershipType;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
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
            ->assertRedirect();

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

    public function test_accept_aa_with_new_group_link_saves_to_aa_column(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $application = $this->applicationInFinalReview('aa1');
        $aaUrl = 'https://chat.whatsapp.com/aa123';
        $memberUrl = 'https://chat.whatsapp.com/member123';
        $this->period->update(['whatsapp_group_member_url' => $memberUrl]);

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
                'whatsapp_group_url' => $aaUrl,
                'include_group_link' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_periods', [
            'id' => $this->period->id,
            'whatsapp_group_aa_url' => $aaUrl,
            'whatsapp_group_member_url' => $memberUrl,
        ]);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application, $aaUrl): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'final_accepted'
                && $job->whatsappGroupUrl === $aaUrl;
        });
    }

    public function test_accept_member_with_new_group_link_saves_to_member_column(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $application = $this->applicationInFinalReview('m1');
        $aaUrl = 'https://chat.whatsapp.com/aa123';
        $memberUrl = 'https://chat.whatsapp.com/member123';
        $this->period->update(['whatsapp_group_aa_url' => $aaUrl]);

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Member->value,
                'final_division_id' => $this->programming->id,
                'whatsapp_group_url' => $memberUrl,
                'include_group_link' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recruitment_periods', [
            'id' => $this->period->id,
            'whatsapp_group_aa_url' => $aaUrl,
            'whatsapp_group_member_url' => $memberUrl,
        ]);

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application, $memberUrl): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'final_accepted'
                && $job->whatsappGroupUrl === $memberUrl;
        });
    }

    public function test_accept_uses_stored_membership_link_without_override(): void
    {
        $application = $this->applicationInFinalReview('aa2');
        $aaUrl = 'https://chat.whatsapp.com/aa-stored';
        $this->period->update([
            'whatsapp_group_aa_url' => $aaUrl,
            'whatsapp_group_member_url' => 'https://chat.whatsapp.com/member-stored',
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
                'include_group_link' => true,
            ])
            ->assertRedirect();

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application, $aaUrl): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'final_accepted'
                && $job->whatsappGroupUrl === $aaUrl;
        });
    }

    public function test_accept_with_include_group_link_false_queues_job_without_url(): void
    {
        $application = $this->applicationInFinalReview('aa3');
        $this->period->update(['whatsapp_group_aa_url' => 'https://chat.whatsapp.com/aa-existing']);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
                'include_group_link' => false,
            ])
            ->assertRedirect();

        Queue::assertPushed(SendRecruitmentNotificationJob::class, function (SendRecruitmentNotificationJob $job) use ($application): bool {
            return $job->applicationId === $application->id
                && $job->templateKey === 'final_accepted'
                && ($job->whatsappGroupUrl === null || $job->whatsappGroupUrl === '');
        });
    }

    public function test_accept_with_include_true_but_no_url_fails_validation(): void
    {
        $application = $this->applicationInFinalReview('aa4');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
                'include_group_link' => true,
            ])
            ->assertSessionHasErrors('whatsapp_group_url');

        $this->assertSame(0, RecruitmentFinalDecision::query()->where('recruitment_application_id', $application->id)->count());
    }

    public function test_accept_with_invalid_group_url_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $application = $this->applicationInFinalReview('aa5');

        $this->actingAs($admin)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
                'whatsapp_group_url' => 'http://not-https.example/grup',
                'include_group_link' => true,
            ])
            ->assertSessionHasErrors('whatsapp_group_url');
    }

    public function test_accept_staff_without_period_edit_cannot_save_group_link(): void
    {
        $application = $this->applicationInFinalReview('aa6');

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
                'whatsapp_group_url' => 'https://chat.whatsapp.com/aa123',
                'include_group_link' => true,
            ])
            ->assertSessionHasErrors('whatsapp_group_url');

        $this->assertSame(0, RecruitmentFinalDecision::query()->where('recruitment_application_id', $application->id)->count());
    }

    public function test_accept_email_contains_membership_group_link(): void
    {
        Mail::fake();

        $application = $this->applicationInFinalReview('mail2');
        $aaUrl = 'https://chat.whatsapp.com/aa-mail';
        $this->period->update([
            'whatsapp_group_aa_url' => $aaUrl,
            'whatsapp_group_member_url' => 'https://chat.whatsapp.com/member-mail',
        ]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.applications.final.accept', $application), [
                'membership_type' => MembershipType::Aa->value,
                'final_division_id' => $this->programming->id,
                'include_group_link' => true,
            ])
            ->assertRedirect();

        $job = new SendRecruitmentNotificationJob($application->id, 'final_accepted', null, null, null, $aaUrl);
        $job->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentInterviewVariableBuilder::class),
            app(RecruitmentQrPngGenerator::class),
        );

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (object $mail) use ($aaUrl): bool {
            return str_contains($mail->bodyHtml, $aaUrl)
                && ! str_contains($mail->bodyHtml, 'member-mail')
                && str_contains($mail->bodyText, $aaUrl);
        });
    }
}
