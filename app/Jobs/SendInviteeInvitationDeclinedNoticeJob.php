<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Enums\EmailNotificationType;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Mail\RegistrationRejectedMail;
use App\Models\FormAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInviteeInvitationDeclinedNoticeJob implements ShouldQueue
{
    use AppliesOutgoingEmailDelay;
    use Queueable;
    use TracksOutgoingEmail;

    public function __construct(
        public string $formAnswerId,
    ) {
        $this->initializeEmailDelivery();
    }

    public function handle(): void
    {
        $submission = FormAnswer::query()
            ->with(['form.event', 'user'])
            ->find($this->formAnswerId);

        if ($submission === null) {
            Log::warning('[SendInviteeInvitationDeclinedNoticeJob] FormAnswer not found.', [
                'form_answer_id' => $this->formAnswerId,
            ]);

            return;
        }

        if ($submission->registration_role !== RegistrationRole::Member
            || $submission->member_confirmation_status !== MemberConfirmationStatus::Rejected) {
            Log::warning('[SendInviteeInvitationDeclinedNoticeJob] Expected a member submission declined by invitee; skipping.', [
                'form_answer_id' => $submission->id,
                'registration_role' => $submission->registration_role?->value,
                'member_confirmation_status' => $submission->member_confirmation_status?->value,
            ]);

            return;
        }

        $user = $submission->user;
        if ($user === null) {
            Log::warning('[SendInviteeInvitationDeclinedNoticeJob] Submission has no user.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $event = $submission->form->event;

        $logAttributes = [
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'recipient_email' => (string) $user->email,
            'notification_type' => EmailNotificationType::InvitationDeclinedByInvitee,
        ];

        if ($user->email === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'User has no email address configured.');

            Log::warning('[SendInviteeInvitationDeclinedNoticeJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            Mail::to($user->email)->send(
                new RegistrationRejectedMail(
                    $submission,
                    forInvitationSelfDeclined: true,
                )
            );

            $this->markEmailLogSent($log, $user->email);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);

            Log::error('[SendInviteeInvitationDeclinedNoticeJob] Email send failed.', [
                'notification_type' => EmailNotificationType::InvitationDeclinedByInvitee->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }
}
