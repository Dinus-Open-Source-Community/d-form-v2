<?php

namespace App\Services\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationStage;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;

final class RecruitmentQrBulkService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /** Kirim QR interview ke applicant tahap Interview yang belum absen; yang sudah terkirim dilewati kecuali $includeSent. */
    public function send(User $actor, RecruitmentPeriod $period, bool $includeSent = false): array
    {
        $recipientsQuery = RecruitmentApplication::query()
            ->where('recruitment_period_id', $period->id)
            ->whereNull('cancelled_at')
            ->where('stage', ApplicationStage::Interview->value)
            ->whereDoesntHave('attendance');

        if (! $includeSent) {
            $recipientsQuery->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('email_logs')
                    ->whereColumn('email_logs.recruitment_application_id', 'recruitment_applications.id')
                    ->where('email_logs.notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
                    ->where('email_logs.status', EmailLogStatus::Sent->value);
            });
        }

        $recipients = $recipientsQuery
            ->orderBy('submitted_at')
            ->get();

        $skipped = 0;

        if (! $includeSent) {
            $skipped = RecruitmentApplication::query()
                ->where('recruitment_period_id', $period->id)
                ->whereNull('cancelled_at')
                ->where('stage', ApplicationStage::Interview->value)
                ->whereDoesntHave('attendance')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('email_logs')
                        ->whereColumn('email_logs.recruitment_application_id', 'recruitment_applications.id')
                        ->where('email_logs.notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
                        ->where('email_logs.status', EmailLogStatus::Sent->value);
                })
                ->count();
        }

        $delaySeconds = (int) config('registration.email_send_delay_seconds', 7);

        foreach ($recipients as $index => $application) {
            EmailLog::query()->create([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => (string) $application->personal_email,
                'status' => EmailLogStatus::Queued,
                'notification_type' => EmailNotificationType::RecruitmentInterviewScheduled,
                'error_message' => null,
                'sent_at' => null,
            ]);

            SendRecruitmentNotificationJob::dispatch($application->id, 'interview_scheduled')
                ->delay(now()->addSeconds($index * $delaySeconds));

            $this->activityLogger->log(
                action: 'email.qr_bulk',
                actor: $actor,
                application: $application,
                newValues: [
                    'template' => 'interview_scheduled',
                    'recipient_email' => (string) $application->personal_email,
                ],
            );
        }

        return ['dispatched' => $recipients->count(), 'skipped' => $skipped];
    }
}
