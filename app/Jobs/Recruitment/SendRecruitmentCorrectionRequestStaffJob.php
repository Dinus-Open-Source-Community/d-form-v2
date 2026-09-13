<?php

namespace App\Jobs\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\User;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRecruitmentCorrectionRequestStaffJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;

    public function __construct(
        public string $correctionRequestId,
    ) {
    }

    public function handle(RecruitmentEmailRenderer $renderer): void
    {
        $correction = RecruitmentCorrectionRequest::query()
            ->with(['application.period', 'application.primaryDivision'])
            ->find($this->correctionRequestId);

        if ($correction === null) {
            Log::warning('[SendRecruitmentCorrectionRequestStaffJob] Correction request not found.', [
                'correction_request_id' => $this->correctionRequestId,
            ]);

            return;
        }

        $application = $correction->application;

        if ($application === null) {
            return;
        }

        $recipients = $this->resolveStaffRecipients();

        if ($recipients === []) {
            Log::warning('[SendRecruitmentCorrectionRequestStaffJob] No staff recipients configured.');

            return;
        }

        $variables = [
            'applicant_name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'period_name' => $application->period?->name ?? 'OpenRecruitment DOSCOM',
            'correction_request_message' => $correction->request_message,
            'application_admin_url' => url(route('dashboard.recruitment.applications.show', $application, false)),
        ];

        $rendered = $renderer->renderTemplate('correction_request_staff', $variables);

        foreach ($recipients as $recipientEmail) {
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
                    'notification_type' => EmailNotificationType::RecruitmentCorrectionRequestStaff,
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
                    'notification_type' => EmailNotificationType::RecruitmentCorrectionRequestStaff,
                    'error_message' => $exception->getMessage(),
                    'sent_at' => null,
                ]);

                throw $exception;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveStaffRecipients(): array
    {
        $configured = config('recruitment.staff_notification_email');

        if (is_string($configured) && trim($configured) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $configured))));
        }

        return User::permission('recruitment.corrections.review')
            ->pluck('email')
            ->filter(fn (?string $email): bool => filled($email))
            ->unique()
            ->values()
            ->all();
    }
}
