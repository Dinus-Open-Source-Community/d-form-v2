<?php

namespace Tests\Feature\Recruitment;

use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentTrackingAuthenticator;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentTrackingResendTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentPeriod $period;

    private RecruitmentApplication $application;

    private string $originalToken = 'AB12CD34';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $division = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        $this->application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $division->id,
            'personal_email' => 'applicant@example.com',
            'tracking_token_hash' => Hash::make($this->originalToken),
        ]);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo([
            'recruitment.periods.view',
            'recruitment.applications.list',
            'recruitment.applications.view',
            'recruitment.screening.decide',
        ]);

        return $user;
    }

    public function test_staff_can_resend_tracking_and_rotates_token(): void
    {
        Queue::fake();

        $this->actingAs($this->staff())
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertRedirect()
            ->assertSessionHas('message');

        $this->application->refresh();

        $this->assertFalse(Hash::check($this->originalToken, (string) $this->application->tracking_token_hash));

        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job): bool {
            if ($job->applicationId !== $this->application->id) {
                return false;
            }

            $this->application->refresh();

            return Hash::check($job->trackingToken, (string) $this->application->tracking_token_hash);
        });

        $this->assertDatabaseHas('recruitment_activity_logs', [
            'recruitment_application_id' => $this->application->id,
            'action' => 'tracking.resend',
        ]);
    }

    public function test_new_token_can_authenticate_after_resend(): void
    {
        Queue::fake();

        $this->actingAs($this->staff())
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application));

        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job): bool {
            $authenticator = app(RecruitmentTrackingAuthenticator::class);

            $this->assertNull(
                $authenticator->attempt($this->application->registration_number, $this->originalToken),
            );

            $application = $authenticator->attempt(
                $this->application->registration_number,
                $job->trackingToken,
            );

            return $application?->is($this->application) === true;
        });
    }

    public function test_user_without_permission_cannot_resend_tracking(): void
    {
        Queue::fake();

        $viewer = User::factory()->create();
        $viewer->assignRole('recruitment-interviewer');
        $viewer->givePermissionTo(['recruitment.applications.view', 'recruitment.periods.view']);

        $this->actingAs($viewer)
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertForbidden();

        Queue::assertNothingPushed();

        $this->assertTrue(Hash::check($this->originalToken, (string) $this->application->fresh()->tracking_token_hash));
    }

    public function test_resend_logs_activity_with_recipient_email(): void
    {
        Queue::fake();

        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application));

        /** @var RecruitmentActivityLog $log */
        $log = RecruitmentActivityLog::query()
            ->where('recruitment_application_id', $this->application->id)
            ->where('action', 'tracking.resend')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->actor_id);
        $this->assertSame('applicant@example.com', $log->new_values['recipient_email'] ?? null);
    }
}
