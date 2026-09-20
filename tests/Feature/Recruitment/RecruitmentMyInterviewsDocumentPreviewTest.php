<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentDocument;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\InterviewSchedulingService;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentMyInterviewsDocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $interviewer;

    private RecruitmentPeriod $period;

    private RecruitmentDivision $programming;

    private RecruitmentInterviewSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
        Queue::fake();
        Storage::fake('local');

        $this->staff = User::factory()->create();
        $this->staff->assignRole('recruitment-staff');

        $this->interviewer = User::factory()->create();
        $this->interviewer->assignRole('recruitment-interviewer');

        $this->programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $this->period = RecruitmentPeriod::factory()->create();

        RecruitmentInterviewerDivision::query()->create([
            'user_id' => $this->interviewer->id,
            'recruitment_division_id' => $this->programming->id,
        ]);

        $this->session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $this->period->id,
            'recruitment_division_id' => $this->programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);
    }

    private function createAssignedApplication(string $suffix): RecruitmentApplication
    {
        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $this->period->id,
            'primary_division_id' => $this->programming->id,
            'registration_number' => 'OPREC-2026-MID'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        app(InterviewSchedulingService::class)->scheduleApplicants(
            $this->staff,
            $this->session,
            [$application->id],
        );

        RecruitmentInterview::query()
            ->where('recruitment_application_id', $application->id)
            ->update(['interviewer_id' => $this->interviewer->id]);

        return $application->fresh(['interview', 'document']);
    }

    public function test_show_exposes_document_preview_urls_and_metadata(): void
    {
        $application = $this->createAssignedApplication('1');

        $cvPath = 'recruitment/cv/preview-1.pdf';
        $portfolioPath = 'recruitment/portfolio/preview-1.pdf';
        Storage::disk('local')->put($cvPath, 'cv content');
        Storage::disk('local')->put($portfolioPath, 'portfolio content');

        RecruitmentDocument::query()->create([
            'recruitment_application_id' => $application->id,
            'cv_path' => $cvPath,
            'cv_original_name' => 'Curriculum Vitae.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 2048,
            'portfolio_type' => 'file',
            'portfolio_path' => $portfolioPath,
            'portfolio_original_name' => 'Portfolio.pdf',
            'portfolio_mime' => 'application/pdf',
            'portfolio_size_bytes' => 4096,
        ]);

        $cvPreviewUrl = route('dashboard.recruitment.applications.documents.download', [
            'application' => $application->id,
            'type' => 'cv',
            'preview' => 1,
        ]);

        $portfolioPreviewUrl = route('dashboard.recruitment.applications.documents.download', [
            'application' => $application->id,
            'type' => 'portfolio',
            'preview' => 1,
        ]);

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.show', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Recruitment/MyInterviews/Show')
                ->where('detail.documents.has_cv', true)
                ->where('detail.documents.has_portfolio', true)
                ->where('detail.documents.cv_preview_url', $cvPreviewUrl)
                ->where('detail.documents.portfolio_preview_url', $portfolioPreviewUrl)
                ->where('detail.documents.cv_original_name', 'Curriculum Vitae.pdf')
                ->where('detail.documents.cv_size_bytes', 2048)
                ->where('detail.documents.portfolio_original_name', 'Portfolio.pdf')
                ->where('detail.documents.portfolio_size_bytes', 4096));

        $cvQuery = [];
        parse_str((string) parse_url($cvPreviewUrl, PHP_URL_QUERY), $cvQuery);

        $this->assertSame('1', $cvQuery['preview'] ?? null);
        $this->assertStringContainsString('/documents/cv', $cvPreviewUrl);

        $portfolioQuery = [];
        parse_str((string) parse_url($portfolioPreviewUrl, PHP_URL_QUERY), $portfolioQuery);

        $this->assertSame('1', $portfolioQuery['preview'] ?? null);
        $this->assertStringContainsString('/documents/portfolio', $portfolioPreviewUrl);
    }

    public function test_show_returns_null_preview_urls_when_application_has_no_files(): void
    {
        $application = $this->createAssignedApplication('2');

        $this->actingAs($this->interviewer)
            ->get(route('dashboard.recruitment.my-interviews.show', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Recruitment/MyInterviews/Show')
                ->where('detail.documents.has_cv', false)
                ->where('detail.documents.cv_preview_url', null)
                ->where('detail.documents.portfolio_preview_url', null)
                ->where('detail.documents.cv_original_name', null)
                ->where('detail.documents.portfolio_original_name', null)
                ->where('detail.documents.cv_size_bytes', null)
                ->where('detail.documents.portfolio_size_bytes', null));
    }
}
