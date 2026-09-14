<?php

namespace App\Jobs\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentTrackingPortalUrlBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRecruitmentApplicationConfirmationJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $applicationId,
        public string $trackingToken,
    ) {
    }

    public function handle(
        RecruitmentEmailRenderer $renderer,
        RecruitmentTrackingPortalUrlBuilder $portalUrlBuilder,
    ): void {
        $application = RecruitmentApplication::query()
            ->with(['period', 'primaryDivision'])
            ->find($this->applicationId);

        if ($application === null) {
            Log::warning('[SendRecruitmentApplicationConfirmationJob] Application not found.', [
                'application_id' => $this->applicationId,
            ]);

            return;
        }

        $recipientEmail = $application->personal_email;
        $trackingUrl = url(route('open-recruitment.track.login', absolute: false));
        $trackingPortalUrl = $portalUrlBuilder->loginUrl(
            $application->registration_number,
            $this->trackingToken,
        );

        $variables = [
            'applicant_name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'period_name' => $application->period?->name ?? 'OpenRecruitment DOSCOM',
            'organization_name' => 'DOSCOM',
            'nim' => $application->nim,
            'semester' => (string) $application->semester,
            'primary_division' => $application->primaryDivision?->name ?? '',
            'tracking_url' => $trackingUrl,
            'tracking_portal_url' => $trackingPortalUrl,
            'tracking_portal_button' => $portalUrlBuilder->loginButtonHtml($trackingPortalUrl),
            'tracking_token' => $this->trackingToken,
        ];

        if ($recipientEmail === '') {
            EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => '',
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::RecruitmentApplicationSubmitted,
                'error_message' => 'No recipient email address configured.',
                'sent_at' => null,
            ]);

            return;
        }

        $rendered = $renderer->renderTemplate('application_submitted', $variables);

        try {
            Mail::to($recipientEmail)->send(new RecruitmentApplicationConfirmationMail(
                subjectLine: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
                bodyText: $rendered['body_text'],
            ));

            EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => $recipientEmail,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::RecruitmentApplicationSubmitted,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => $recipientEmail,
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::RecruitmentApplicationSubmitted,
                'error_message' => $exception->getMessage(),
                'sent_at' => null,
            ]);

            throw $exception;
        }
    }
}
