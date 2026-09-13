<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentFeedback;
use App\Models\Recruitment\RecruitmentPeriod;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKING_TOKEN = 'feedback-tracking-token-abc123';

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
                'registration_number' => 'OPREC-2026-FB001',
                'primary_division_id' => $programming->id,
                'stage' => ApplicationStage::Completed,
                'result' => ApplicationResult::Accepted,
            ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function validFeedbackPayload(): array
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

    private function authenticateTracking(): void
    {
        $this->post(route('open-recruitment.track.authenticate'), [
            'registration_number' => $this->application->registration_number,
            'tracking_token' => self::TRACKING_TOKEN,
        ])->assertRedirect(route('open-recruitment.track.show'));
    }

    public function test_submit_feedback_after_completed_is_saved(): void
    {
        $this->authenticateTracking();

        $this->get(route('open-recruitment.track.feedback'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('OpenRecruitment/Track/Feedback'));

        $this->post(route('open-recruitment.track.feedback.store'), $this->validFeedbackPayload())
            ->assertRedirect(route('open-recruitment.track.show'))
            ->assertSessionHas('toast');

        $feedback = RecruitmentFeedback::query()
            ->where('recruitment_application_id', $this->application->id)
            ->first();

        $this->assertNotNull($feedback);
        $this->assertSame(5, $feedback->rating_registration_ease);
        $this->assertSame('Alur OpRec jelas dan panitia responsif.', $feedback->feedback_text);
        $this->assertNotNull($feedback->submitted_at);

        $this->get(route('open-recruitment.track.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tracking.feedback.submitted', true)
                ->where('tracking.feedback.can_submit', false));
    }

    public function test_submit_feedback_before_completed_is_blocked(): void
    {
        $this->application->update(['stage' => ApplicationStage::Interview]);

        $this->authenticateTracking();

        $this->get(route('open-recruitment.track.feedback'))
            ->assertRedirect(route('open-recruitment.track.show'))
            ->assertSessionHasErrors('feedback');

        $this->post(route('open-recruitment.track.feedback.store'), $this->validFeedbackPayload())
            ->assertSessionHasErrors('feedback');

        $this->assertSame(
            0,
            RecruitmentFeedback::query()->where('recruitment_application_id', $this->application->id)->count(),
        );
    }

    public function test_duplicate_feedback_is_blocked(): void
    {
        $this->authenticateTracking();

        $this->post(route('open-recruitment.track.feedback.store'), $this->validFeedbackPayload())
            ->assertRedirect(route('open-recruitment.track.show'));

        $this->post(route('open-recruitment.track.feedback.store'), $this->validFeedbackPayload())
            ->assertSessionHasErrors('feedback');

        $this->assertSame(
            1,
            RecruitmentFeedback::query()->where('recruitment_application_id', $this->application->id)->count(),
        );
    }

    public function test_feedback_without_valid_tracking_session_is_blocked(): void
    {
        $this->get(route('open-recruitment.track.feedback'))
            ->assertRedirect(route('open-recruitment.track.login'));

        $this->post(route('open-recruitment.track.feedback.store'), $this->validFeedbackPayload())
            ->assertRedirect(route('open-recruitment.track.login'));
    }
}
