<?php

namespace App\Jobs\Recruitment;

use App\Enums\EmailNotificationType;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\Recruitment\RecruitmentInterview;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentInterviewVariableBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRecruitmentInterviewerNotificationJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public function __construct(
        public string $interviewId,
        public string $templateKey = 'interview_assignment',
    ) {
        $this->initializeEmailDelivery();
    }

    public function handle(
        RecruitmentEmailRenderer $renderer,
        RecruitmentInterviewVariableBuilder $variableBuilder,
    ): void {
        $interview = RecruitmentInterview::query()
            ->with(['application', 'interviewer'])
            ->find($this->interviewId);

        if ($interview === null || $interview->interviewer === null) {
            Log::warning('[SendRecruitmentInterviewerNotificationJob] Interview or interviewer not found.', [
                'interview_id' => $this->interviewId,
            ]);

            return;
        }

        $recipientEmail = $interview->interviewer->email ?? '';

        $variables = $variableBuilder->build($interview);

        $logAttributes = [
            'recruitment_application_id' => $interview->recruitment_application_id,
            'event_id' => null,
            'user_id' => $interview->interviewer_id,
            'recipient_email' => $recipientEmail,
            'notification_type' => EmailNotificationType::RecruitmentInterviewAssignment,
        ];

        if ($recipientEmail === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'No interviewer email address configured.');

            return;
        }

        $rendered = $renderer->renderTemplate($this->templateKey, $variables);

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(new RecruitmentApplicationConfirmationMail(
                subjectLine: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
                bodyText: $rendered['body_text'],
            ));

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $exception) {
            $this->markEmailLogFailed($log, $exception);

            Log::error('[SendRecruitmentInterviewerNotificationJob] Email send failed.', [
                'notification_type' => EmailNotificationType::RecruitmentInterviewAssignment->value,
                'interview_id' => $interview->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
