<?php

namespace App\Jobs;

use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\FormAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drops the member submission after invitations are sent so the user can submit the event form again
 * under the same login (unique user_id + form_id).
 *
 * Keeps organizer history via email logs; email_logs.form_answer_id is null-on-delete.
 */
class DeleteDeclinedInvitationMemberSubmissionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $memberFormAnswerId,
    ) {
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

        $submission->delete();
    }
}
