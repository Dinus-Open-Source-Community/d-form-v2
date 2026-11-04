<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentDocument;
use App\Models\Recruitment\RecruitmentFeedback;
use App\Models\Recruitment\RecruitmentPeriod;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mutation tracking OpRec (update, correction, feedback) wajib punya limiter
 * per sesi tracking. Test ini mengunci kuota, isolasi antar sesi, fallback IP
 * untuk request tanpa sesi, dan memastikan 429 tidak menyimpan data.
 */
class RecruitmentTrackingMutationThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKING_TOKEN = 'mutation-throttle-token-1234567890';

    private RecruitmentDivision $programming;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Storage::fake('local');
        Queue::fake();

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeApplication(
        string $registrationNumber,
        string $token,
        array $overrides = [],
    ): RecruitmentApplication {
        $period = RecruitmentPeriod::factory()->open()->create();

        return RecruitmentApplication::factory()
            ->for($period, 'period')
            ->withTrackingToken($token)
            ->create(array_merge([
                'registration_number' => $registrationNumber,
                'primary_division_id' => $this->programming->id,
                'stage' => ApplicationStage::Screening,
                'result' => ApplicationResult::Pending,
            ], $overrides));
    }

    private function attachDocument(RecruitmentApplication $application): void
    {
        RecruitmentDocument::query()->create([
            'recruitment_application_id' => $application->id,
            'cv_path' => 'recruitment/'.$application->recruitment_period_id.'/'.$application->id.'/cv.pdf',
            'cv_original_name' => 'cv.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1000,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://portfolio.example.com/original',
            'instagram_follow_path' => 'recruitment/'.$application->recruitment_period_id.'/'.$application->id.'/follow.jpg',
            'instagram_follow_original_name' => 'follow.jpg',
            'instagram_follow_mime' => 'image/jpeg',
            'instagram_follow_size_bytes' => 2048,
            'twibbon_url' => 'https://instagram.com/p/twibbon-original',
        ]);
    }

    private function authenticateTracking(RecruitmentApplication $application, string $token): void
    {
        $this->post(route('recruitment.track.authenticate'), [
            'registration_number' => $application->registration_number,
            'tracking_token' => $token,
        ])->assertRedirect(route('recruitment.track.show'));
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayload(RecruitmentApplication $application, string $fullName): array
    {
        return [
            'full_name' => $fullName,
            'nim' => $application->nim,
            'semester' => 1,
            'phone' => '081234567890',
            'personal_email' => 'budi@gmail.com',
            'student_email' => 'budi@students.udinus.ac.id',
            'instagram_username' => 'budirevisi',
            'primary_division_id' => $this->programming->id,
            'secondary_division_id' => null,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://portfolio.example.com/revised',
            'twibbon_url' => 'https://instagram.com/p/twibbon-revised',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function feedbackPayload(): array
    {
        return [
            'rating_registration_ease' => 5,
            'rating_info_clarity' => 4,
            'rating_tracking_ease' => 5,
            'rating_interview_experience' => 4,
            'rating_staff_service' => 5,
            'feedback_text' => 'Alur OpRec jelas dan panitia responsif.',
        ];
    }

    private function updateLimit(): int
    {
        return (int) config('recruitment.tracking_mutation_throttle.update');
    }

    private function correctionLimit(): int
    {
        return (int) config('recruitment.tracking_mutation_throttle.correction');
    }

    private function feedbackLimit(): int
    {
        return (int) config('recruitment.tracking_mutation_throttle.feedback');
    }

    public function test_update_mutation_returns_429_after_session_quota_is_spent(): void
    {
        $application = $this->makeApplication('OPREC-2026-MT001', self::TRACKING_TOKEN, [
            'revision_required' => true,
            'is_verified' => false,
        ]);
        $this->attachDocument($application);
        $this->authenticateTracking($application, self::TRACKING_TOKEN);

        for ($attempt = 1; $attempt <= $this->updateLimit(); $attempt++) {
            $this->put(route('recruitment.track.update'), $this->editPayload($application, 'Nama Sah '.$attempt))
                ->assertRedirect(route('recruitment.track.show'));
        }

        $this->put(route('recruitment.track.update'), $this->editPayload($application, 'Nama Ditolak'))
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame('Nama Sah '.$this->updateLimit(), $application->refresh()->full_name);
    }

    public function test_correction_mutation_returns_429_after_session_quota_is_spent(): void
    {
        $application = $this->makeApplication('OPREC-2026-MT002', self::TRACKING_TOKEN, [
            'is_verified' => true,
            'revision_required' => false,
        ]);
        $this->authenticateTracking($application, self::TRACKING_TOKEN);

        for ($attempt = 1; $attempt <= $this->correctionLimit(); $attempt++) {
            $response = $this->post(route('recruitment.track.correction'), [
                'request_message' => 'Mohon izin memperbaiki data portofolio saya.',
            ]);

            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->post(route('recruitment.track.correction'), [
            'request_message' => 'Mohon izin memperbaiki data portofolio saya.',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(
            1,
            RecruitmentCorrectionRequest::query()
                ->where('recruitment_application_id', $application->id)
                ->count(),
            'Domain gate tetap membatasi satu permintaan koreksi aktif.',
        );
    }

    public function test_feedback_mutation_returns_429_after_session_quota_is_spent(): void
    {
        $application = $this->makeApplication('OPREC-2026-MT003', self::TRACKING_TOKEN, [
            'stage' => ApplicationStage::Completed,
            'result' => ApplicationResult::Accepted,
        ]);
        $this->authenticateTracking($application, self::TRACKING_TOKEN);

        for ($attempt = 1; $attempt <= $this->feedbackLimit(); $attempt++) {
            $response = $this->post(route('recruitment.track.feedback.store'), $this->feedbackPayload());

            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->post(route('recruitment.track.feedback.store'), $this->feedbackPayload())
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(
            1,
            RecruitmentFeedback::query()
                ->where('recruitment_application_id', $application->id)
                ->count(),
        );
    }

    public function test_quota_is_isolated_per_tracking_session(): void
    {
        $first = $this->makeApplication('OPREC-2026-MT004', self::TRACKING_TOKEN, [
            'revision_required' => true,
            'is_verified' => false,
        ]);
        $this->attachDocument($first);
        $this->authenticateTracking($first, self::TRACKING_TOKEN);

        for ($attempt = 1; $attempt <= $this->updateLimit() + 1; $attempt++) {
            $this->put(route('recruitment.track.update'), $this->editPayload($first, 'Nama Pertama '.$attempt));
        }

        $this->put(route('recruitment.track.update'), $this->editPayload($first, 'Nama Pertama Terakhir'))
            ->assertStatus(429);

        $otherToken = 'mutation-throttle-token-second-0987654321';
        $second = $this->makeApplication('OPREC-2026-MT005', $otherToken, [
            'revision_required' => true,
            'is_verified' => false,
        ]);
        $this->attachDocument($second);
        $this->authenticateTracking($second, $otherToken);

        $this->put(route('recruitment.track.update'), $this->editPayload($second, 'Nama Kedua'))
            ->assertRedirect(route('recruitment.track.show'));

        $this->assertSame('Nama Kedua', $second->refresh()->full_name);
    }

    public function test_requests_without_tracking_session_fall_back_to_ip_limit(): void
    {
        for ($attempt = 1; $attempt <= $this->updateLimit(); $attempt++) {
            $this->put(route('recruitment.track.update'), [])
                ->assertRedirect(route('recruitment.track.login'));
        }

        $this->put(route('recruitment.track.update'), [])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_each_mutation_route_declares_its_named_limiter(): void
    {
        $expected = [
            'recruitment.track.update' => 'throttle:oprec-track-update',
            'recruitment.track.correction' => 'throttle:oprec-track-correction',
            'recruitment.track.feedback.store' => 'throttle:oprec-track-feedback',
        ];

        $routes = collect(app('router')->getRoutes())
            ->keyBy(fn ($route): string => (string) $route->getName());

        foreach ($expected as $name => $middleware) {
            $route = $routes->get($name);

            $this->assertNotNull($route, 'Route '.$name.' tidak terdaftar.');
            $this->assertContains(
                $middleware,
                $route->gatherMiddleware(),
                'Route '.$name.' wajib memakai '.$middleware.'.',
            );
        }
    }
}
