<?php

namespace Tests\Feature\Recruitment;

use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentTrackingAuthenticator;
use App\Services\Recruitment\RecruitmentTrackingPortalUrlBuilder;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecruitmentTrackingResendEndToEndTest extends TestCase
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

    public function test_admin_json_detail_exposes_resend_capability(): void
    {
        $this->actingAs($this->staff())
            ->getJson(route('dashboard.recruitment.periods.applications.show', [
                'period' => $this->period->id,
                'application' => $this->application->id,
            ]))
            ->assertOk()
            ->assertJsonPath('application.id', $this->application->id)
            ->assertJsonPath('application.can_resend_tracking', true);
    }

    public function test_full_flow_admin_resend_then_applicant_tracks_with_new_token(): void
    {
        Mail::fake();
        Queue::fake();

        $staff = $this->staff();
        $newToken = null;

        $this->actingAs($staff)
            ->post(route('dashboard.recruitment.applications.resend-tracking', $this->application))
            ->assertRedirect()
            ->assertSessionHas('message');

        Queue::assertPushed(SendRecruitmentApplicationConfirmationJob::class, function (SendRecruitmentApplicationConfirmationJob $job) use (&$newToken): bool {
            $newToken = $job->trackingToken;

            return $job->applicationId === $this->application->id
                && strlen($job->trackingToken) === 8;
        });

        $this->assertNotNull($newToken);

        $this->application->refresh();
        $this->assertFalse(Hash::check($this->originalToken, (string) $this->application->tracking_token_hash));
        $this->assertTrue(Hash::check($newToken, (string) $this->application->tracking_token_hash));

        $job = new SendRecruitmentApplicationConfirmationJob($this->application->id, $newToken);
        $job->handle(
            app(RecruitmentEmailRenderer::class),
            app(RecruitmentTrackingPortalUrlBuilder::class),
        );

        Mail::assertSent(RecruitmentApplicationConfirmationMail::class, function (RecruitmentApplicationConfirmationMail $mail): bool {
            return $mail->hasTo('applicant@example.com');
        });

        $authenticator = app(RecruitmentTrackingAuthenticator::class);
        $this->assertNull($authenticator->attempt($this->application->registration_number, $this->originalToken));

        $authenticated = $authenticator->attempt($this->application->registration_number, $newToken);
        $this->assertNotNull($authenticated);
        $this->assertTrue($authenticated->is($this->application));

        $this->post(route('recruitment.track.authenticate'), [
            'registration_number' => $this->application->registration_number,
            'tracking_token' => $newToken,
        ])
            ->assertRedirect(route('recruitment.track.show', absolute: false));

        $this->get(route('recruitment.track.show'))
            ->assertOk();
    }

    public function test_get_resend_tracking_url_is_not_allowed(): void
    {
        $this->actingAs($this->staff())
            ->get('/admin/recruitment/applications/'.$this->application->id.'/resend-tracking')
            ->assertMethodNotAllowed();
    }
}
