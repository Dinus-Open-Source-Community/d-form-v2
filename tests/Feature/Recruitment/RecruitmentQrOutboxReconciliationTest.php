<?php

namespace Tests\Feature\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentQrDispatchOutbox;
use App\Models\User;
use App\Services\Recruitment\RecruitmentQrEligibilityQuery;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * H3: an ambiguous `sending` intent must be reconcilable. Otherwise the linked email log stays
 * `queued` forever, the applicant is excluded from `eligible()` permanently, and the QR summary
 * never drains — the ADR told operators to reconcile a state that had no operation.
 *
 * Invariant under test: after reconciliation the outbox and its email log agree on one terminal
 * state, and eligibility/counts reflect that state.
 */
class RecruitmentQrOutboxReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private RecruitmentPeriod $period;

    private RecruitmentApplication $application;

    private EmailLog $emailLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');
        $this->period = RecruitmentPeriod::factory()->create(['created_by' => $this->staff->id]);
        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        $this->emailLog = EmailLog::query()->create([
            'recruitment_application_id' => $this->application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => (string) $this->application->personal_email,
            'status' => EmailLogStatus::Queued,
            'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
            'error_message' => null,
            'sent_at' => null,
        ]);
    }

    public function test_reconciling_as_failed_drains_the_intent_and_restores_eligibility(): void
    {
        $this->makeOutbox(RecruitmentQrDispatchOutbox::STATUS_SENDING);
        $this->assertAmbiguousStateIsBlocking();

        $this->artisan('recruitment:reconcile-qr-outbox', [
            'emailLogId' => $this->emailLog->id,
            'state' => 'failed',
        ])->expectsOutputToContain('recorded as failed')->assertExitCode(0);

        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $this->emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_FAILED,
        ]);
        $log = EmailLog::query()->whereKey($this->emailLog->id)->firstOrFail();
        self::assertSame(EmailLogStatus::Failed, $log->status);
        self::assertNotNull($log->error_message);
        self::assertStringNotContainsString('@', (string) $log->error_message);

        $query = app(RecruitmentQrEligibilityQuery::class);
        self::assertSame(0, $query->countInFlight($this->period));
        self::assertSame(1, $query->countEligible($this->period));

        $this->assertSummary(queued: 0, sent: 0, failed: 1, eligible: 1);
    }

    public function test_reconciling_as_sent_marks_delivery_without_resending(): void
    {
        $this->makeOutbox(RecruitmentQrDispatchOutbox::STATUS_SENDING);
        $this->assertAmbiguousStateIsBlocking();

        $this->artisan('recruitment:reconcile-qr-outbox', [
            'emailLogId' => $this->emailLog->id,
            'state' => 'sent',
        ])->expectsOutputToContain('recorded as sent')->assertExitCode(0);

        $outbox = RecruitmentQrDispatchOutbox::query()->whereKey($this->emailLog->id)->firstOrFail();
        self::assertSame(RecruitmentQrDispatchOutbox::STATUS_SENT, $outbox->status);
        self::assertNotNull($outbox->sent_at);

        $log = EmailLog::query()->whereKey($this->emailLog->id)->firstOrFail();
        self::assertSame(EmailLogStatus::Sent, $log->status);
        self::assertNotNull($log->sent_at);

        $query = app(RecruitmentQrEligibilityQuery::class);
        self::assertSame(1, $query->countInFlight($this->period));
        self::assertSame(0, $query->countEligible($this->period));

        $this->assertSummary(queued: 0, sent: 1, failed: 0, eligible: 0);
    }

    public function test_reconcile_refuses_an_intent_that_is_not_ambiguous(): void
    {
        $this->makeOutbox(RecruitmentQrDispatchOutbox::STATUS_QUEUED);

        $this->artisan('recruitment:reconcile-qr-outbox', [
            'emailLogId' => $this->emailLog->id,
            'state' => 'failed',
        ])->expectsOutputToContain("is 'queued', not 'sending'")
            ->assertExitCode(1);

        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $this->emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_QUEUED,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'id' => $this->emailLog->id,
            'status' => EmailLogStatus::Queued->value,
        ]);
    }

    public function test_reconcile_rejects_an_unknown_state_and_an_unknown_intent(): void
    {
        $this->makeOutbox(RecruitmentQrDispatchOutbox::STATUS_SENDING);

        $this->artisan('recruitment:reconcile-qr-outbox', [
            'emailLogId' => $this->emailLog->id,
            'state' => 'maybe',
        ])->expectsOutputToContain('State must be "sent" or "failed".')->assertExitCode(2);

        $this->artisan('recruitment:reconcile-qr-outbox', [
            'emailLogId' => '9f9f9f9f-0000-0000-0000-000000000000',
            'state' => 'sent',
        ])->assertExitCode(1);

        $this->assertDatabaseHas('recruitment_qr_dispatch_outboxes', [
            'email_log_id' => $this->emailLog->id,
            'status' => RecruitmentQrDispatchOutbox::STATUS_SENDING,
        ]);
    }

    private function makeOutbox(string $status): void
    {
        RecruitmentQrDispatchOutbox::query()->create([
            'email_log_id' => $this->emailLog->id,
            'status' => $status,
            'available_at' => now()->subMinutes(20),
            'delay_seconds' => 7,
            'dispatch_attempts' => 1,
        ]);
    }

    /** An ambiguous send blocks the applicant and leaves the queue badge stuck forever. */
    private function assertAmbiguousStateIsBlocking(): void
    {
        $query = app(RecruitmentQrEligibilityQuery::class);

        self::assertSame(1, $query->countInFlight($this->period));
        self::assertSame(0, $query->countEligible($this->period));

        $this->assertSummary(queued: 1, sent: 0, failed: 0, eligible: 0);
    }

    /**
     * `qr_eligible_count` lives in a 60 s period-aggregate cache (worker-driven changes are
     * documented as TTL-bounded), so the assertion drops that key to observe the settled state.
     */
    private function assertSummary(int $queued, int $sent, int $failed, int $eligible): void
    {
        Cache::forget("recruitment.period.aggregates.{$this->period->id}");

        $this->actingAs($this->staff)
            ->get(route('dashboard.recruitment.periods.show', ['period' => $this->period->id, 'tab' => 'peserta']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('qr_status_summary.queued', $queued)
                ->where('qr_status_summary.sent', $sent)
                ->where('qr_status_summary.failed', $failed)
                ->where('qr_eligible_count', $eligible));
    }
}
