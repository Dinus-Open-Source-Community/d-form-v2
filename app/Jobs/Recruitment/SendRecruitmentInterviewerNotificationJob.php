<?php

namespace App\Jobs\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\EmailLog;
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

    public function __construct(
        public string $interviewId,
        public string $templateKey = 'interview_assignment',
    ) {
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

        if ($recipientEmail === '') {
            EmailLog::query()->create([
                'recruitment_application_id' => $interview->recruitment_application_id,
                'event_id' => null,
                'user_id' => $interview->interviewer_id,
                'recipient_email' => '',
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::RecruitmentInterviewAssignment,
                'error_message' => 'No interviewer email address configured.',
                'sent_at' => null,
            ]);

            return;
        }

        $rendered = $renderer->renderTemplate($this->templateKey, $variables);

        try {
            Mail::to($recipientEmail)->send(new RecruitmentApplicationConfirmationMail(
                subjectLine: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
                bodyText: $rendered['body_text'],
            ));

            EmailLog::query()->create([
                'recruitment_application_id' => $interview->recruitment_application_id,
                'event_id' => null,
                'user_id' => $interview->interviewer_id,
                'recipient_email' => $recipientEmail,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::RecruitmentInterviewAssignment,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            EmailLog::query()->create([
                'recruitment_application_id' => $interview->recruitment_application_id,
                'event_id' => null,
                'user_id' => $interview->interviewer_id,
                'recipient_email' => $recipientEmail,
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::RecruitmentInterviewAssignment,
                'error_message' => $exception->getMessage(),
                'sent_at' => null,
            ]);

            throw $exception;
        }
    }
}
