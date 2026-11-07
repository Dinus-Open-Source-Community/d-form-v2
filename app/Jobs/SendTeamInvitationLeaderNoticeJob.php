<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Enums\EmailNotificationType;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationRejectedMail;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationAnswersSummarizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTeamInvitationLeaderNoticeJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public function __construct(
        public string $memberFormAnswerId,
        public string $decision,
        /** Shown only in the leader decline email; not stored on FormAnswer. */
        public ?string $declineReason = null,
    ) {
        $this->initializeEmailDelivery();
    }

    public function handle(RegistrationAnswersSummarizer $summarizer): void
    {
        if (! in_array($this->decision, ['accepted', 'rejected'], true)) {
            Log::warning('[SendTeamInvitationLeaderNoticeJob] Invalid decision.', [
                'decision' => $this->decision,
            ]);

            return;
        }

        $memberSubmission = FormAnswer::query()
            ->with(['form.event', 'user'])
            ->find($this->memberFormAnswerId);

        if ($memberSubmission === null) {
            Log::warning('[SendTeamInvitationLeaderNoticeJob] FormAnswer not found.', [
                'form_answer_id' => $this->memberFormAnswerId,
            ]);

            return;
        }

        if ($memberSubmission->registration_role !== RegistrationRole::Member) {
            Log::warning('[SendTeamInvitationLeaderNoticeJob] Expected a member submission.', [
                'form_answer_id' => $memberSubmission->id,
            ]);

            return;
        }

        $expected = $this->decision === 'accepted'
            ? MemberConfirmationStatus::Accepted
            : MemberConfirmationStatus::Rejected;

        if ($memberSubmission->member_confirmation_status !== $expected) {
            Log::warning('[SendTeamInvitationLeaderNoticeJob] Member confirmation status does not match decision.', [
                'form_answer_id' => $memberSubmission->id,
                'decision' => $this->decision,
                'member_confirmation_status' => $memberSubmission->member_confirmation_status?->value,
            ]);

            return;
        }

        $memberSubmission->loadMissing('teamLeaderSubmission.user');
        $leaderSubmission = $memberSubmission->teamLeaderSubmission;
        $leader = $leaderSubmission?->user;

        $event = $memberSubmission->form->event;
        $notificationType = $this->decision === 'accepted'
            ? EmailNotificationType::TeamMemberInvitationAcceptedLeaderNotice
            : EmailNotificationType::TeamMemberInvitationDeclinedLeaderNotice;

        $logAttributes = [
            'form_answer_id' => $memberSubmission->id,
            'event_id' => $event->id,
            'user_id' => $leader?->id,
            'recipient_email' => $leader?->email ?? '',
            'notification_type' => $notificationType,
        ];

        if ($leader === null || $leader->email === '') {
            if ($leader !== null && $leader->email === '') {
                $this->markEmailLogUndeliverable($logAttributes, 'Leader has no email address configured.');
            }

            Log::warning('[SendTeamInvitationLeaderNoticeJob] No leader or leader email.', [
                'form_answer_id' => $memberSubmission->id,
            ]);

            return;
        }

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            if ($this->decision === 'accepted') {
                $answersSummary = $summarizer->summarize($memberSubmission);
                Mail::to($leader->email)->send(
                    new RegistrationConfirmationMail(
                        $memberSubmission,
                        $answersSummary,
                        null,
                        $leader,
                        true,
                    )
                );
            } else {
                Mail::to($leader->email)->send(
                    new RegistrationRejectedMail(
                        $memberSubmission,
                        forTeammateDeclinedLeaderNotice: true,
                        greetingUser: $leader,
                        declineReason: $this->declineReason !== null && $this->declineReason !== ''
                            ? $this->declineReason
                            : null,
                    )
                );
            }

            $this->markEmailLogSent($log, $leader->email);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);

            Log::error('[SendTeamInvitationLeaderNoticeJob] Email send failed.', [
                'notification_type' => $notificationType->value,
                'form_answer_id' => $memberSubmission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $e::class,
            ]);

            $this->throwRedacted($e);
        }
    }
}
