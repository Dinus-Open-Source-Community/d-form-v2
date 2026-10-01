<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\ScreeningDecision;
use App\Enums\Recruitment\ScreeningReason;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentScreening;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecruitmentGroupLinkBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private const WA_URL = 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt';

    private User $staff;

    private RecruitmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->period = RecruitmentPeriod::factory()->create([
            'created_by' => $this->staff->id,
            'whatsapp_group_url' => self::WA_URL,
        ]);
    }

    public function test_broadcast_to_passed_applicants_dispatches_jobs_and_logs(): void
    {
        Mail::fake();

        $passedOne = $this->makePassedApplicant();
        $passedTwo = $this->makePassedApplicant();
        $submitted = $this->makeApplicant();
        $revision = $this->makeApplicant(['stage' => ApplicationStage::Screening]);
        $this->makeScreening($revision, ScreeningDecision::RevisionRequired, ScreeningReason::IncompleteData);
        $rejected = $this->makeApplicant(['stage' => ApplicationStage::Completed, 'result' => ApplicationResult::Rejected]);
        $this->makeScreening($rejected, ScreeningDecision::Reject, ScreeningReason::RequirementsNotMet);
        $cancelled = $this->makeApplicant(['cancelled_at' => now()]);
        $this->makeScreening($cancelled, ScreeningDecision::Pass);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-group-link', $this->period))
            ->assertOk()
            ->assertJson(['dispatched' => 2]);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 2);
        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (object $mail): bool {
            return str_contains($mail->bodyHtml, self::WA_URL)
                && str_contains($mail->bodyHtml, 'Gabung Grup WA');
        });

        foreach ([$passedOne, $passedTwo] as $application) {
            $this->assertDatabaseHas('email_logs', [
                'recruitment_application_id' => $application->id,
                'notification_type' => 'recruitment_group_link',
                'status' => 'sent',
            ]);
            $this->assertDatabaseHas('recruitment_activity_logs', [
                'recruitment_application_id' => $application->id,
                'action' => 'email.group_link',
            ]);
        }

        foreach ([$submitted, $revision, $rejected, $cancelled] as $application) {
            $this->assertDatabaseMissing('email_logs', [
                'recruitment_application_id' => $application->id,
                'notification_type' => 'recruitment_group_link',
            ]);
        }
    }

    public function test_broadcast_without_group_link_returns_422(): void
    {
        $period = RecruitmentPeriod::factory()->create([
            'created_by' => $this->staff->id,
            'whatsapp_group_url' => null,
        ]);

        $this->actingAs($this->staff)
            ->postJson(route('dashboard.recruitment.periods.send-group-link', $period))
            ->assertStatus(422)
            ->assertJsonValidationErrors('whatsapp_group_url');
    }

    public function test_broadcast_by_non_owner_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->post(route('dashboard.recruitment.periods.send-group-link', $this->period))
            ->assertForbidden();
    }

    public function test_final_rejected_with_pass_screening_is_excluded(): void
    {
        Mail::fake();

        $passed = $this->makePassedApplicant();
        $finalRejected = $this->makePassedApplicant();
        $finalRejected->update(['result' => ApplicationResult::Rejected]);

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-group-link', $this->period))
            ->assertOk()
            ->assertJson(['dispatched' => 1]);

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, 1);

        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $passed->id,
            'notification_type' => 'recruitment_group_link',
        ]);
        $this->assertDatabaseMissing('email_logs', [
            'recruitment_application_id' => $finalRejected->id,
            'notification_type' => 'recruitment_group_link',
        ]);
    }

    public function test_broadcast_with_zero_passed_returns_ok_zero(): void
    {
        Mail::fake();

        $this->makeApplicant();

        $this->actingAs($this->staff)
            ->post(route('dashboard.recruitment.periods.send-group-link', $this->period))
            ->assertOk()
            ->assertJson(['dispatched' => 0]);

        Mail::assertNothingSent();
    }

    /** Applicant lolos: stage Interview + screening Pass (cermin ScreeningService::pass). */
    private function makePassedApplicant(): RecruitmentApplication
    {
        $application = $this->makeApplicant(['stage' => ApplicationStage::Interview]);
        $this->makeScreening($application, ScreeningDecision::Pass);

        return $application;
    }

    /** @param array<string, mixed> $overrides */
    private function makeApplicant(array $overrides = []): RecruitmentApplication
    {
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        return RecruitmentApplication::factory()->create(array_merge([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ], $overrides));
    }

    private function makeScreening(
        RecruitmentApplication $application,
        ScreeningDecision $decision,
        ?ScreeningReason $reason = null,
    ): RecruitmentScreening {
        return RecruitmentScreening::query()->create([
            'recruitment_application_id' => $application->id,
            'decision' => $decision,
            'reason' => $reason,
            'acted_by' => $this->staff->id,
            'acted_at' => now(),
        ]);
    }
}
