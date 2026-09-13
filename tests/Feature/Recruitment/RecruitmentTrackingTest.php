<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentTrackingAuthenticator;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecruitmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKING_TOKEN = 'valid-tracking-token-1234567890ab';

    private RecruitmentApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);

        $period = RecruitmentPeriod::factory()->open()->create(['name' => 'OpRec 2026']);
        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->application = RecruitmentApplication::factory()
            ->for($period, 'period')
            ->withTrackingToken(self::TRACKING_TOKEN)
            ->create([
                'registration_number' => 'OPREC-2026-00042',
                'primary_division_id' => $programming->id,
                'stage' => ApplicationStage::Screening,
            ]);
    }

    public function test_valid_registration_number_and_token_grants_tracking_dashboard(): void
    {
        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $this->application->registration_number,
            'tracking_token' => self::TRACKING_TOKEN,
        ])->assertRedirect(route('open-recruitment.track.show'));

        $this->get(route('open-recruitment.track.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OpenRecruitment/Track/Show')
                ->where('tracking.application.registration_number', $this->application->registration_number)
                ->where('tracking.application.stage', 'screening'));
    }

    public function test_wrong_tracking_token_returns_generic_error(): void
    {
        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $this->application->registration_number,
            'tracking_token' => 'wrong-token-1234567890123456',
        ])->assertSessionHasErrors('credentials');

        $this->assertSame(
            RecruitmentTrackingAuthenticator::INVALID_CREDENTIALS_MESSAGE,
            session('errors')?->get('credentials')[0],
        );
    }

    public function test_invalid_registration_number_returns_generic_error(): void
    {
        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => 'OPREC-2099-99999',
            'tracking_token' => self::TRACKING_TOKEN,
        ])->assertSessionHasErrors('credentials');
    }

    public function test_tracking_response_exposes_only_public_fields(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('admin');

        DB::table('recruitment_screenings')->insert([
            'id' => (string) Str::uuid(),
            'recruitment_application_id' => $this->application->id,
            'decision' => 'revision_required',
            'reason' => 'invalid_cv',
            'notes' => 'INTERNAL SCREENING NOTES MUST NOT LEAK',
            'acted_by' => $staff->id,
            'acted_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('recruitment_final_decisions')->insert([
            'id' => (string) Str::uuid(),
            'recruitment_application_id' => $this->application->id,
            'membership_type' => 'member',
            'final_division_id' => $this->application->primary_division_id,
            'internal_reason' => 'INTERNAL REJECT REASON',
            'public_message' => 'Terima kasih sudah mendaftar.',
            'decided_by' => $staff->id,
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->application->update(['stage' => ApplicationStage::Completed]);

        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $this->application->registration_number,
            'tracking_token' => self::TRACKING_TOKEN,
        ]);

        $response = $this->get(route('open-recruitment.track.show'));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']['tracking'] ?? []);

        $this->assertIsString($json);
        $this->assertStringNotContainsString('INTERNAL SCREENING NOTES', $json);
        $this->assertStringNotContainsString('INTERNAL REJECT REASON', $json);
        $this->assertStringNotContainsString('invalid_cv', $json);
        $this->assertStringContainsString('Terima kasih sudah mendaftar.', $json);
    }

    public function test_rate_limit_exceeded_on_track_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('open-recruitment.track.authenticate'), [
                'registration_number' => 'OPREC-2099-99999',
                'tracking_token' => 'wrong-token-1234567890123456',
            ]);
        }

        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => 'OPREC-2099-99999',
            'tracking_token' => 'wrong-token-1234567890123456',
        ])->assertStatus(429);
    }

    public function test_tracking_dashboard_requires_session(): void
    {
        $this->get(route('open-recruitment.track.show'))
            ->assertRedirect(route('open-recruitment.track.login'));
    }

    public function test_tracking_login_page_renders(): void
    {
        $this->get(route('open-recruitment.track.login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('OpenRecruitment/Track/Login'));
    }
}
