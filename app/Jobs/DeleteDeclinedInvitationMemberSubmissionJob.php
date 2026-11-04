<?php

namespace App\Jobs;

use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\FormAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Drops the member submission after invitations are sent so the user can submit the event form again
 * under the same login (unique user_id + form_id).
 *
 * Keeps organizer history via email logs; email_logs.form_answer_id is null-on-delete.
 */
class DeleteDeclinedInvitationMemberSubmissionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(
        public string $memberFormAnswerId,
    ) {
    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('[DeleteDeclinedInvitationMemberSubmissionJob] Job failed permanently.', [
            'job' => static::class,
            'member_form_answer_id' => $this->memberFormAnswerId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }

    public function handle(): void
    {
        $submission = FormAnswer::query()->find($this->memberFormAnswerId);

        if ($submission === null) {
            return;
        }

        if ($submission->registration_role !== RegistrationRole::Member) {
            return;
        }

        if ($submission->member_confirmation_status !== MemberConfirmationStatus::Rejected) {
            return;
        }

        // Quota was released when the invitee declined; delete only frees the unique slot.
        $submission->delete();
    }
}
