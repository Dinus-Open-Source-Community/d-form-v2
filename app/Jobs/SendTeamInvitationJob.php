<?php

namespace App\Jobs;

use App\Enums\EmailNotificationType;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Jobs\Concerns\TracksOutgoingEmail;
use App\Mail\TeamInvitationMail;
use App\Models\FormAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendTeamInvitationJob implements ShouldQueue
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
            ->with(['form.event', 'user', 'teamLeaderSubmission.user'])
            ->find($this->formAnswerId);

        if ($submission === null) {
            Log::warning('[SendTeamInvitationJob] FormAnswer not found.', [
                'form_answer_id' => $this->formAnswerId,
            ]);

            return;
        }

        $user = $submission->user;
        if ($user === null) {
            Log::warning('[SendTeamInvitationJob] Submission has no user.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $form = $submission->form;
        if ($form === null) {
            Log::error('[SendTeamInvitationJob] Submission has no form; cannot send invitation.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        $event = $form->event;
        if ($event === null) {
            Log::error('[SendTeamInvitationJob] Form has no event; cannot send invitation.', [
                'form_answer_id' => $submission->id,
                'form_id' => $form->id,
            ]);

            return;
        }

        if ($submission->registration_role !== RegistrationRole::Member) {
            Log::warning('[SendTeamInvitationJob] Expected a member submission; skipping send.', [
                'form_answer_id' => $submission->id,
                'registration_role' => $submission->registration_role?->value,
            ]);

            return;
        }

        if ($submission->member_confirmation_status !== MemberConfirmationStatus::Pending) {
            Log::info('[SendTeamInvitationJob] Member is not pending confirmation; skipping send.', [
                'form_answer_id' => $submission->id,
                'member_confirmation_status' => $submission->member_confirmation_status?->value,
            ]);

            return;
        }

        $recipientEmail = trim((string) ($user->email ?? ''));

        $logAttributes = [
            'form_answer_id' => $submission->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'recipient_email' => $recipientEmail,
            'notification_type' => EmailNotificationType::TeamInvitation,
        ];

        if ($recipientEmail === '') {
            $this->markEmailLogUndeliverable($logAttributes, 'User has no email address configured.');

            Log::warning('[SendTeamInvitationJob] No recipient email.', [
                'form_answer_id' => $submission->id,
            ]);

            return;
        }

        if (($submission->invitation_token ?? '') === '') {
            try {
                $submission->invitation_token = $this->generateUniqueInvitationToken();
                if ($submission->invitation_expired_at === null) {
                    $submission->invitation_expired_at = now()->addDays((int) config('registration.invitation_ttl_days', 7));
                }
                $submission->save();

                Log::warning('[SendTeamInvitationJob] invitation_token was missing; regenerated before send.', [
                    'form_answer_id' => $submission->id,
                ]);
            } catch (\Throwable $tokenException) {
                Log::error('[SendTeamInvitationJob] Could not recover missing invitation_token.', [
                    'form_answer_id' => $submission->id,
                    'event_id' => $event->id,
                    'delivery_key' => $this->deliveryKey,
                    'exception_class' => $tokenException::class,
                ]);

                $this->markEmailLogUndeliverable($logAttributes, 'Missing invitation_token and could not generate one.');

                return;
            }
        }

        $this->applyOutgoingEmailJitter();

        $log = $this->reserveEmailLog($logAttributes);

        if ($log === null) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(new TeamInvitationMail($submission));

            $this->markEmailLogSent($log, $recipientEmail);
        } catch (\Throwable $e) {
            $this->markEmailLogFailed($log, $e);

            Log::error('[SendTeamInvitationJob] Email send failed.', [
                'notification_type' => EmailNotificationType::TeamInvitation->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'delivery_key' => $this->deliveryKey,
                'exception_class' => $e::class,
            ]);

            $this->throwRedacted($e);
        }
    }

    private function generateUniqueInvitationToken(): string
    {
        for ($i = 0; $i < 32; $i++) {
            $token = Str::random(48);
            if (! FormAnswer::query()->where('invitation_token', $token)->exists()) {
                return $token;
            }
        }

        throw new \RuntimeException('Could not generate a unique invitation token.');
    }
}
