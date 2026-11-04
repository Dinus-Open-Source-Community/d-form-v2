<?php

namespace App\Jobs\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Mail\Recruitment\RecruitmentApplicationConfirmationMail;
use App\Models\EmailLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentScreening;
use App\Services\Recruitment\RecruitmentEmailRenderer;
use App\Services\Recruitment\RecruitmentInterviewVariableBuilder;
use App\Services\Recruitment\RecruitmentQrPngGenerator;
use App\Enums\Recruitment\MembershipType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRecruitmentNotificationJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public function __construct(
        public string $applicationId,
        public string $templateKey,
        public ?string $interviewId = null,
        public ?array $revisionSections = null,
        public ?string $revisionNotes = null,
        public ?string $whatsappGroupUrl = null,
    ) {
        $this->initializeEmailDelivery();
    }

    public function handle(
        RecruitmentEmailRenderer $renderer,
        RecruitmentInterviewVariableBuilder $variableBuilder,
        RecruitmentQrPngGenerator $qrGenerator,
    ): void {
        $application = RecruitmentApplication::query()
            ->with(['period', 'primaryDivision', 'primaryInterview', 'attendance'])
            ->find($this->applicationId);

        if ($application === null) {
            Log::warning('[SendRecruitmentNotificationJob] Application not found.', [
                'application_id' => $this->applicationId,
                'template_key' => $this->templateKey,
            ]);

            return;
        }

        $notificationType = $this->resolveNotificationType();
        $recipientEmail = $application->personal_email;
        $trackingUrl = url(route('recruitment.track.login', absolute: false));

        $variables = [
            'applicant_name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'period_name' => $application->period?->name ?? 'OpenRecruitment DOSCOM',
            'organization_name' => 'DOSCOM',
            'nim' => $application->nim,
            'semester' => (string) $application->semester,
            'primary_division' => $application->primaryDivision?->name ?? '',
            'tracking_url' => $trackingUrl,
        ];

        if ($this->templateKey === 'passed_screening' && filled($this->whatsappGroupUrl)) {
            $variables['whatsapp_group_url'] = (string) $this->whatsappGroupUrl;
        }

        if ($this->templateKey === 'group_link') {
            $variables['whatsapp_group_url'] = (string) $this->whatsappGroupUrl;
        }

        if ($this->templateKey === 'final_accepted' && filled($this->whatsappGroupUrl)) {
            $variables['whatsapp_group_url'] = (string) $this->whatsappGroupUrl;
        }

        if ($this->templateKey === 'revision_required') {
            $variables = array_merge($variables, [
                'revision_sections' => $this->revisionSectionLabels(),
                'revision_notes' => (string) ($this->revisionNotes ?? ''),
            ]);
        }

        if ($this->interviewId !== null) {
            $interview = RecruitmentInterview::query()->find($this->interviewId);

            if ($interview !== null) {
                $variables = array_merge($variables, $variableBuilder->build($interview));
            }
        }

        if ($this->templateKey === 'interview_scheduled' && $this->interviewId === null) {
            $sessionList = $variableBuilder->buildSessionList($application);
            $variables['session_list'] = $sessionList['sessions'];
            $variables['session_division_name'] = $sessionList['session_division_name'];
        }

        if (in_array($this->templateKey, ['final_accepted', 'final_rejected'], true)) {
            $application->loadMissing('finalDecision.finalDivision');
            $decision = $application->finalDecision;
            $membershipType = filled($decision?->membership_type)
                ? MembershipType::tryFrom((string) $decision->membership_type)
                : null;

            $variables = array_merge($variables, [
                'membership_type' => $membershipType?->label() ?? '',
                'final_division' => $decision?->finalDivision?->name ?? '',
                'final_result' => $application->result->label(),
                'public_message' => $decision?->public_message ?? '',
            ]);
        }

        if ($recipientEmail === '') {
            $log = $this->reserveEmailLog([
                'recruitment_application_id' => $application->id,
                'event_id' => null,
                'user_id' => null,
                'recipient_email' => '',
                'notification_type' => $notificationType,
            ], fn (): ?EmailLog => $this->latestQueuedLog($application, $notificationType));

            if ($log !== null) {
                $this->markEmailLogFailedWithReason($log, 'No recipient email address configured.');
            }

            return;
        }

        $rendered = $renderer->renderTemplate($this->templateKey, $variables);

        $qrPng = $this->resolveAttendanceQrBinary($application, $qrGenerator);

        $log = $this->reserveEmailLog([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => $recipientEmail,
            'notification_type' => $notificationType,
        ], fn (): ?EmailLog => $this->latestQueuedLog($application, $notificationType));

        if ($log === null) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(new RecruitmentApplicationConfirmationMail(
                subjectLine: $rendered['subject'],
                bodyHtml: $rendered['body_html'],
                bodyText: $rendered['body_text'],
                qrPngBinary: $qrPng,
                headline: $this->resolveHeadline(),
            ));

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $exception) {
            $this->markEmailLogFailed($log, $exception);

            Log::error('[SendRecruitmentNotificationJob] Email send failed.', [
                'notification_type' => $notificationType->value,
                'application_id' => $application->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /** Ambil baris Queued yang belum diklaim — hanya dipakai template interview_scheduled. */
    private function latestQueuedLog(RecruitmentApplication $application, EmailNotificationType $notificationType): ?EmailLog
    {
        if ($this->templateKey !== 'interview_scheduled') {
            return null;
        }

        return EmailLog::query()
            ->where('recruitment_application_id', $application->id)
            ->where('notification_type', $notificationType)
            ->where('status', EmailLogStatus::Queued)
            ->whereNull('delivery_key')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function revisionSectionLabels(): array
    {
        return RecruitmentScreening::revisionSectionLabels($this->revisionSections);
    }

    private function resolveHeadline(): ?string
    {
        return match ($this->templateKey) {
            'application_submitted' => 'Pendaftaran diterima',
            'revision_required' => 'Perlu revisi pendaftaran',
            'passed_screening' => 'Lolos screening',
            'rejected_screening' => 'Hasil screening',
            'interview_scheduled' => 'Jadwal interview',
            'interview_rescheduled' => 'Jadwal interview diubah',
            'interview_reminder_h1' => 'Reminder interview besok',
            'interview_reminder_h2' => 'Reminder interview',
            'final_accepted' => 'Kamu diterima!',
            'final_rejected' => 'Hasil OpenRecruitment',
            'group_link' => 'Link grup WA',
            default => null,
        };
    }

    private function resolveNotificationType(): EmailNotificationType
    {
        return match ($this->templateKey) {
            'revision_required' => EmailNotificationType::RecruitmentRevisionRequired,
            'passed_screening' => EmailNotificationType::RecruitmentPassedScreening,
            'rejected_screening' => EmailNotificationType::RecruitmentRejectedScreening,
            'interview_scheduled' => EmailNotificationType::RecruitmentInterviewScheduled,
            'interview_rescheduled' => EmailNotificationType::RecruitmentInterviewRescheduled,
            'interview_reminder_h1' => EmailNotificationType::RecruitmentInterviewReminderH1,
            'interview_reminder_h2' => EmailNotificationType::RecruitmentInterviewReminderH2,
            'final_accepted' => EmailNotificationType::RecruitmentFinalAccepted,
            'final_rejected' => EmailNotificationType::RecruitmentFinalRejected,
            'group_link' => EmailNotificationType::RecruitmentGroupLink,
            default => EmailNotificationType::RecruitmentApplicationSubmitted,
        };
    }

    private function resolveAttendanceQrBinary(
        RecruitmentApplication $application,
        RecruitmentQrPngGenerator $qrGenerator,
    ): ?string {
        if (! in_array($this->templateKey, [
            'interview_scheduled',
            'interview_rescheduled',
            'interview_reminder_h1',
            'interview_reminder_h2',
        ], true)) {
            return null;
        }

        if ($application->attendance !== null) {
            return null;
        }

        $interview = $application->primaryInterview;

        if ($application->stage !== \App\Enums\Recruitment\ApplicationStage::Interview) {
            return null;
        }

        try {
            return $qrGenerator->pngForApplication($application->id);
        } catch (\JsonException) {
            return null;
        }
    }
}
