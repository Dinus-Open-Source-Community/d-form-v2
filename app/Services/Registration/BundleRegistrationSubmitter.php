<?php

namespace App\Services\Registration;

use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BundleRegistrationSubmitter
{
    public function __construct(
        private EventRegistrationCounter $registrationCounter,
        private BundleGuestDuplicateChecker $duplicateChecker,
        private FormAnswerWriter $formAnswerWriter,
    ) {
    }

    public static function isBundleForm(Form $form): bool
    {
        $metadata = $form->metadata;
        if (! is_array($metadata)) {
            return false;
        }

        $mode = $metadata['registration_mode'] ?? null;

        return is_string($mode) && strtolower($mode) === 'bundle';
    }

    /**
     *
     * @param  list<array<string, mixed>>  $memberAnswerRows  one merged answers payload per member (same keys as leader)
     * @param  list<string>  $memberEmails  normalized lowercase emails
     * @param  array<string, string>|null  $resolvedUserIdsByEmail  output of
     * BundleGuestDuplicateChecker::assertEmailsAvailableForForm() when the caller already
     * checked before mapping answers; null re-runs the full check in the transaction.
     * @return array{leader: FormAnswer, members: list<FormAnswer>}
     *
     */
    public function submit(
        Form $form,
        Event $event,
        User $leaderUser,
        array $leaderAnswers,
        array $memberAnswerRows,
        array $memberEmails,
        bool $adminExemptFromQuota,
        ?array $resolvedUserIdsByEmail = null,
    ): array {
        $teamSize = TeamRegistrationSubmitter::resolveTeamSize($form);

        if ($teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This bundle form is misconfigured (team size). Contact the organizer.'),
            ]);
        }

        if (count($memberEmails) !== $teamSize - 1 || count($memberAnswerRows) !== count($memberEmails)) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('Bundle participants were misconfigured. Try again or contact the organizer.'),
            ]);
        }

        $memberAnswerRows = array_map(
            fn (array $row) => json_decode(json_encode($row, JSON_THROW_ON_ERROR), true),
            $memberAnswerRows
        );

        return DB::transaction(function () use (
            $form,
            $event,
            $leaderUser,
            $memberEmails,
            $leaderAnswers,
            $memberAnswerRows,
            $adminExemptFromQuota,
            $teamSize,
            $resolvedUserIdsByEmail
        ): array {
            // Concurrency invariant: the read view that makes the race guard
            // correct starts after the event row lock; do not add a non-locking
            // read before it and do not move the lock. Bundle submits for the
            // same event serialize here, so assertEmailsStillAvailable() must be
            // the first consistent (non-locking) read after lockForUpdate();
            // a plain SELECT earlier would fix the snapshot before the competing
            // submit commits and silently hide the duplicate.
            $lockedEvent = Event::query()->lockForUpdate()->find($event->id);
            if ($lockedEvent === null) {
                throw new \RuntimeException('Event not found.');
            }

            if (FormAnswer::query()
                ->where('form_id', $form->id)
                ->where('user_id', $leaderUser->id)
                ->excludeRejectedSubmissions()
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'team_member_emails' => __('A participant is already registered for this form.'),
                ]);
            }

            // The caller that already rejected duplicates before mapping answers
            // passes its resolution, so this transaction only re-runs the race
            // predicate (never the user lookup or the answer mapping).
            if ($resolvedUserIdsByEmail !== null) {
                $this->duplicateChecker->assertEmailsStillAvailable($form, $memberEmails, $resolvedUserIdsByEmail);
                $userIdsByEmail = $resolvedUserIdsByEmail;
            } else {
                $userIdsByEmail = $this->duplicateChecker->assertEmailsAvailableForForm($form, $memberEmails);
            }

            $this->registrationCounter->assertCanReserve($lockedEvent, $teamSize, $adminExemptFromQuota);

            $groupToken = $this->generateUniqueGroupToken((string) $form->id);

            $leader = $this->formAnswerWriter->create([
                'answers' => $leaderAnswers,
                'form_id' => $form->id,
                'user_id' => (string) $leaderUser->id,
                'leader_form_answer_id' => null,
                'registration_role' => RegistrationRole::Leader,
                'member_confirmation_status' => MemberConfirmationStatus::Accepted,
                'member_confirmed_at' => now(),
                'invitation_token' => null,
                'group_token' => $groupToken,
                'invited_email' => null,
                'invitation_expired_at' => null,
            ]);

            $memberRows = [];

            foreach ($memberEmails as $i => $email) {
                $userId = $userIdsByEmail[mb_strtolower(trim($email))] ?? null;

                try {
                    $memberRows[] = $this->formAnswerWriter->create([
                        'answers' => $memberAnswerRows[$i],
                        'form_id' => $form->id,
                        'user_id' => $userId,
                        'leader_form_answer_id' => $leader->id,
                        'registration_role' => RegistrationRole::Member,
                        'member_confirmation_status' => MemberConfirmationStatus::Accepted,
                        'member_confirmed_at' => now(),
                        'invitation_token' => null,
                        'group_token' => $groupToken,
                        'invited_email' => $email,
                        'invitation_expired_at' => null,
                    ]);
                } catch (QueryException $exception) {
                    TeamRegistrationSubmitter::rethrowParticipantWriteFailure($exception, (int) $i);
                }
            }

            $this->registrationCounter->reserveLocked($lockedEvent, $teamSize);

            return TeamRegistrationSubmitter::reloadSubmissionGroup($leader, $memberRows);
        });
    }

    private function generateUniqueGroupToken(string $formId): string
    {
        for ($i = 0; $i < 48; $i++) {
            $token = strtoupper(Str::random(8));
            if (! FormAnswer::query()->where('form_id', $formId)->where('group_token', $token)->exists()) {
                return $token;
            }
        }

        throw new \RuntimeException('Could not generate a unique group token.');
    }
}
