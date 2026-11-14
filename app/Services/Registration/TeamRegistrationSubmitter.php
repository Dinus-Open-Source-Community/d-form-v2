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

final class TeamRegistrationSubmitter
{
    public function __construct(
        private EventRegistrationCounter $registrationCounter,
        private FormAnswerWriter $formAnswerWriter,
    ) {
    }

    /** Total participants including leader (from form metadata). */
    public static function resolveTeamSize(Form $form): int
    {
        $metadata = $form->metadata;
        if (! is_array($metadata)) {
            return 0;
        }

        $teamSize = $metadata['team_size'] ?? null;
        if (is_numeric($teamSize) && (int) $teamSize >= 2) {
            return (int) $teamSize;
        }

        $maxTeam = $metadata['max_team_size'] ?? null;
        if (is_numeric($maxTeam) && (int) $maxTeam >= 2) {
            return (int) $maxTeam;
        }

        return 0;
    }

    public static function isTeamForm(Form $form): bool
    {
        $metadata = $form->metadata;
        if (! is_array($metadata)) {
            return false;
        }

        $mode = $metadata['registration_mode'] ?? null;

        return is_string($mode) && strtolower($mode) === 'team';
    }

    /**
     *
     * @param  list<User>  $memberUsers
     * @return array{leader: FormAnswer, members: list<FormAnswer>}
     *
     */
    public function submit(
        Form $form,
        Event $event,
        User $leaderUser,
        array $answers,
        array $memberUsers,
        bool $adminExemptFromQuota,
    ): array {
        $teamSize = self::resolveTeamSize($form);
        if ($teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This team form is misconfigured (team size). Contact the organizer.'),
            ]);
        }

        $memberAnswers = json_decode(json_encode($answers, JSON_THROW_ON_ERROR), true);

        return DB::transaction(function () use (
            $form,
            $event,
            $leaderUser,
            $memberUsers,
            $answers,
            $memberAnswers,
            $adminExemptFromQuota,
            $teamSize
        ): array {
            $lockedEvent = Event::query()->lockForUpdate()->find($event->id);
            if ($lockedEvent === null) {
                throw new \RuntimeException('Event not found.');
            }

            $participants = array_merge([$leaderUser], $memberUsers);
            $participantIds = array_map(static fn (User $participant): string => (string) $participant->id, $participants);

            $alreadyRegistered = FormAnswer::query()
                ->where('form_id', $form->id)
                ->whereIn('user_id', $participantIds)
                ->excludeRejectedSubmissions()
                ->lockForUpdate()
                ->pluck('user_id');

            if ($alreadyRegistered->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'team_member_emails' => __('A participant is already registered for this form.'),
                ]);
            }

            $this->registrationCounter->assertCanReserve($lockedEvent, $teamSize, $adminExemptFromQuota);

            $inviteExpiresAt = now()->addDays((int) config('registration.invitation_ttl_days', 7));

            $leader = $this->formAnswerWriter->create([
                'answers' => $answers,
                'form_id' => $form->id,
                'user_id' => (string) $leaderUser->id,
                'leader_form_answer_id' => null,
                'registration_role' => RegistrationRole::Leader,
                'member_confirmation_status' => MemberConfirmationStatus::Accepted,
                'member_confirmed_at' => now(),
                'invitation_token' => null,
                'invited_email' => null,
                'invitation_expired_at' => null,
            ]);

            $memberRows = [];
            $invitationTokens = $this->generateUniqueInvitationTokens(count($memberUsers));

            foreach ($memberUsers as $index => $memberUser) {
                try {
                    $memberRows[] = $this->formAnswerWriter->create([
                        'answers' => $memberAnswers,
                        'form_id' => $form->id,
                        'user_id' => (string) $memberUser->id,
                        'leader_form_answer_id' => $leader->id,
                        'registration_role' => RegistrationRole::Member,
                        'member_confirmation_status' => MemberConfirmationStatus::Pending,
                        'invitation_token' => $invitationTokens[$index],
                        'invited_email' => $memberUser->email,
                        'invitation_expired_at' => $inviteExpiresAt,
                    ]);
                } catch (QueryException $exception) {
                    self::rethrowParticipantWriteFailure($exception, (int) $index);
                }
            }

            $this->registrationCounter->reserveLocked($lockedEvent, $teamSize);

            return self::reloadSubmissionGroup($leader, $memberRows);
        });
    }

    /**
     *
     * Re-read one leader plus its members in a single query.
     * @param  list<FormAnswer>  $memberRows
     * @return array{leader: FormAnswer, members: list<FormAnswer>}
     *
     */
    public static function reloadSubmissionGroup(FormAnswer $leader, array $memberRows): array
    {
        $memberIds = array_map(static fn (FormAnswer $row): string => (string) $row->id, $memberRows);
        $persisted = FormAnswer::query()
            ->whereIn('id', [...$memberIds, (string) $leader->id])
            ->get()
            ->keyBy(static fn (FormAnswer $row): string => (string) $row->id);

        $hydratedLeader = $persisted->get((string) $leader->id);

        if (! $hydratedLeader instanceof FormAnswer) {
            throw new \RuntimeException('Leader submission was not persisted.');
        }

        $members = [];

        foreach ($memberIds as $id) {
            $member = $persisted->get($id);

            if (! $member instanceof FormAnswer) {
                throw new \RuntimeException('Member submission was not persisted.');
            }

            $members[] = $member;
        }

        return ['leader' => $hydratedLeader, 'members' => $members];
    }

    /**
     *
     * Translate a participant-row unique violation into the same field error used by the pre-insert duplicate checks, instead of surfacing a 500.
     * @throws QueryException|ValidationException
     *
     */
    public static function rethrowParticipantWriteFailure(QueryException $exception, int $index): never
    {
        if (! self::isParticipantUniqueViolation($exception)) {
            throw $exception;
        }

        throw ValidationException::withMessages([
            "team_member_emails.{$index}" => __('A participant with this email is already registered for this form.'),
        ]);
    }

    private static function isParticipantUniqueViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'active_guest_email_form_unique')
            || str_contains($message, 'active_user_form_unique')
            || str_contains($message, 'form_answers.invited_email')
            || str_contains($message, 'form_answers.user_id');
    }

    /**
     *
     * Generate one invitation token per member, validating the whole batch with a single collision query and retrying only when a collision is found.
     * @return list<string>
     *
     */
    private function generateUniqueInvitationTokens(int $count): array
    {
        if ($count === 0) {
            return [];
        }

        for ($attempt = 0; $attempt < 32; $attempt++) {
            $tokens = [];

            for ($i = 0; $i < $count; $i++) {
                $tokens[] = Str::random(48);
            }

            $tokens = array_values(array_unique($tokens));

            if (count($tokens) !== $count) {
                continue;
            }

            if (! FormAnswer::query()->whereIn('invitation_token', $tokens)->exists()) {
                return $tokens;
            }
        }

        throw new \RuntimeException('Could not generate a unique invitation token.');
    }
}
