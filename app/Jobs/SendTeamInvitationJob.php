<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Jobs\Concerns\AppliesOutgoingEmailDelay;
use App\Mail\TeamInvitationMail;
use App\Models\EmailLog;
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

    public function __construct(
        public string $formAnswerId,
    ) {
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
        if ($recipientEmail === '') {
            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => '',
                'status' => EmailLogStatus::Failed,
                'notification_type' => EmailNotificationType::TeamInvitation,
                'error_message' => 'User has no email address configured.',
                'sent_at' => null,
            ]);

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
                    'recipient_email' => $recipientEmail,
                    'exception_class' => $tokenException::class,
                    'exception_message' => $tokenException->getMessage(),
                    'exception' => $tokenException,
                ]);

                try {
                    EmailLog::query()->create([
                        'form_answer_id' => $submission->id,
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'recipient_email' => $recipientEmail,
                        'status' => EmailLogStatus::Failed,
                        'notification_type' => EmailNotificationType::TeamInvitation,
                        'error_message' => 'Missing invitation_token and could not generate one: '.$tokenException->getMessage(),
                        'sent_at' => null,
                    ]);
                } catch (\Throwable $logPersistError) {
                    Log::error('[SendTeamInvitationJob] Failed to persist EmailLog after token recovery failure.', [
                        'form_answer_id' => $submission->id,
                        'exception' => $logPersistError,
                    ]);
                }

                return;
            }
        }

        try {
            $this->applyOutgoingEmailJitter();

            Mail::to($recipientEmail)->send(new TeamInvitationMail($submission));

            EmailLog::query()->create([
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'recipient_email' => $recipientEmail,
                'status' => EmailLogStatus::Sent,
                'notification_type' => EmailNotificationType::TeamInvitation,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[SendTeamInvitationJob] Email send failed.', [
                'notification_type' => EmailNotificationType::TeamInvitation->value,
                'form_answer_id' => $submission->id,
                'event_id' => $event->id,
                'recipient_email' => $recipientEmail,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'exception' => $e,
            ]);

            try {
                EmailLog::query()->create([
                    'form_answer_id' => $submission->id,
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'recipient_email' => $recipientEmail,
                    'status' => EmailLogStatus::Failed,
                    'notification_type' => EmailNotificationType::TeamInvitation,
                    'error_message' => $e->getMessage(),
                    'sent_at' => null,
                ]);
            } catch (\Throwable $logPersistError) {
                Log::error('[SendTeamInvitationJob] Failed to persist EmailLog after send failure.', [
                    'form_answer_id' => $submission->id,
                    'exception' => $logPersistError,
                ]);
            }

            throw $e;
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
