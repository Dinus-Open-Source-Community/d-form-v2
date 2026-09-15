<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentDocument;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentRegistrationSequence;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKING_TOKEN = 'security-test-tracking-token-abc';

    private RecruitmentPeriod $openPeriod;

    private RecruitmentDivision $programming;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Storage::fake('local');
        Queue::fake();

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        $this->openPeriod = RecruitmentPeriod::factory()->open()->create([
            'name' => 'OpRec 2026',
        ]);

        RecruitmentRegistrationSequence::query()->create([
            'recruitment_period_id' => $this->openPeriod->id,
            'last_sequence' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Budi Santoso',
            'nim' => 'A11.2024.01234',
            'semester' => 2,
            'phone' => '081234567890',
            'personal_email' => 'budi@gmail.com',
            'student_email' => 'budi@students.udinus.ac.id',
            'instagram_username' => 'budisantoso',
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => RecruitmentDivision::query()->where('code', 'data')->value('id'),
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://portfolio.example.com/budi',
            'cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
        ], $overrides);
    }

    public function test_s01_direct_url_to_storage_cv_is_not_accessible(): void
    {
        $cvPath = 'recruitment/cv/secret-applicant.pdf';
        Storage::disk('local')->put($cvPath, 'secret cv bytes');

        $response = $this->get('/storage/'.$cvPath);
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_s02_guessing_application_uuid_returns_forbidden_for_interviewer(): void
    {
        $interviewer = User::factory()->create();
        $interviewer->assignRole('recruitment-interviewer');

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->openPeriod->id,
            'primary_division_id' => $this->programming->id,
            'stage' => ApplicationStage::Submitted,
            'result' => ApplicationResult::Pending,
        ]);

        $this->actingAs($interviewer)
            ->get(route('dashboard.recruitment.applications.show', $application))
            ->assertForbidden();
    }

    public function test_s03_brute_force_tracking_token_is_rate_limited(): void
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

    public function test_s04_public_oprec_post_routes_are_on_web_csrf_stack(): void
    {
        $webMiddleware = app('router')->getMiddlewareGroups()['web'] ?? [];

        $this->assertTrue(
            collect($webMiddleware)->contains(
                fn (string $middleware): bool => str_contains($middleware, 'ValidateCsrfToken'),
            ),
            'Web middleware group must include CSRF protection for public OpRec forms.',
        );

        $applyRoute = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->getName() === 'open-recruitment.apply.store');

        $this->assertNotNull($applyRoute);
        $this->assertContains('web', $applyRoute->gatherMiddleware());

        $trackRoute = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->getName() === 'open-recruitment.track.authenticate');

        $this->assertNotNull($trackRoute);
        $this->assertContains('web', $trackRoute->gatherMiddleware());
    }

    public function test_s05_xss_in_applicant_name_is_not_rendered_as_html_in_tracking(): void
    {
        $xssName = '<script>alert("xss")</script>';

        $application = RecruitmentApplication::factory()
            ->for($this->openPeriod, 'period')
            ->withTrackingToken(self::TRACKING_TOKEN)
            ->create([
                'full_name' => $xssName,
                'registration_number' => 'OPREC-2026-XSS01',
                'primary_division_id' => $this->programming->id,
                'stage' => ApplicationStage::Submitted,
                'result' => ApplicationResult::Pending,
            ]);

        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $application->registration_number,
            'tracking_token' => self::TRACKING_TOKEN,
        ]);

        $response = $this->get(route('open-recruitment.track.show'));
        $response->assertOk();

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $content);
        $this->assertStringNotContainsString('<script>alert', $content);

        $response->assertInertia(fn ($page) => $page
            ->where('tracking.application.full_name', $xssName));
    }

    public function test_s06_upload_executable_disguised_as_pdf_is_rejected(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'cv' => UploadedFile::fake()->create('malware.pdf', 50, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('cv');
    }

    public function test_s07_oversized_cv_upload_is_rejected(): void
    {
        $this->post(route('open-recruitment.apply.store'), $this->validPayload([
            'cv' => UploadedFile::fake()->create('cv.pdf', 5121, 'application/pdf'),
        ]))->assertSessionHasErrors('cv');
    }

    public function test_cv_download_requires_authorization_even_when_path_known(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->openPeriod->id,
            'primary_division_id' => $this->programming->id,
        ]);

        $cvPath = 'recruitment/cv/protected.pdf';
        Storage::disk('local')->put($cvPath, 'protected');

        RecruitmentDocument::query()->create([
            'recruitment_application_id' => $application->id,
            'cv_path' => $cvPath,
            'cv_original_name' => 'cv.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 100,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://example.com',
        ]);

        $guest = User::factory()->create();
        $guest->assignRole('member');

        $this->actingAs($guest)
            ->get(route('dashboard.recruitment.applications.documents.download', [
                'application' => $application,
                'type' => 'cv',
            ]))
            ->assertRedirect(route('dashboard'));
    }
}
