<?php

namespace Tests\Feature\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentInterviewVariableBuilder;
use App\Services\Recruitment\RecruitmentQrPngGenerator;
use App\Services\Recruitment\RecruitmentTrackingPortalUrlBuilder;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class RecruitmentEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RecruitmentDivisionSeeder::class);
    }

    private function application(): RecruitmentApplication
    {
        $period = RecruitmentPeriod::factory()->create();
        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();

        return RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $programming->id,
            'personal_email' => 'applicant@example.com',
            'stage' => ApplicationStage::FinalReview,
            'result' => ApplicationResult::Pending,
        ]);
    }

    public function test_notification_job_logs_failure_and_rethrows_for_queue_retry(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $application = $this->application();

        $job = new SendRecruitmentNotificationJob($application->id, 'final_accepted');

        try {
            $job->handle(
                app(RecruitmentEmailRenderer::class),
                app(RecruitmentInterviewVariableBuilder::class),
                app(RecruitmentQrPngGenerator::class),
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $application->id,
            'recipient_email' => 'applicant@example.com',
            'status' => 'failed',
            'notification_type' => 'recruitment_final_accepted',
        ]);
    }

    public function test_confirmation_job_logs_failure_and_rethrows_for_queue_retry(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail transport error'));

        $application = $this->application();

        $job = new SendRecruitmentApplicationConfirmationJob($application->id, 'plain-tracking-token');

        try {
            $job->handle(
                app(RecruitmentEmailRenderer::class),
                app(RecruitmentTrackingPortalUrlBuilder::class),
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Mail transport error', $exception->getMessage());
        }

        $this->assertDatabaseHas('email_logs', [
            'recruitment_application_id' => $application->id,
            'recipient_email' => 'applicant@example.com',
            'status' => 'failed',
            'notification_type' => 'recruitment_application_submitted',
        ]);
    }

    public function test_recruitment_email_jobs_define_retry_policy(): void
    {
        $notificationJob = new SendRecruitmentNotificationJob('app-id', 'passed_screening');
        $confirmationJob = new SendRecruitmentApplicationConfirmationJob('app-id', 'token');

        $this->assertSame(3, $notificationJob->tries);
        $this->assertSame([60, 300, 900], $notificationJob->backoff);
        $this->assertSame(3, $confirmationJob->tries);
        $this->assertSame([60, 300, 900], $confirmationJob->backoff);
    }
}
