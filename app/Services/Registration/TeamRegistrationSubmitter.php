<?php

namespace App\Services\Registration;

use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
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

    /**
     * Total participants including leader (from form metadata).
     */
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
     * @param  list<User>  $memberUsers
     * @return array{leader: FormAnswer, members: list<FormAnswer>}
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

            foreach ($memberUsers as $memberUser) {
                $memberRows[] = $this->formAnswerWriter->create([
                    'answers' => $memberAnswers,
                    'form_id' => $form->id,
                    'user_id' => (string) $memberUser->id,
                    'leader_form_answer_id' => $leader->id,
                    'registration_role' => RegistrationRole::Member,
                    'member_confirmation_status' => MemberConfirmationStatus::Pending,
                    'invitation_token' => $this->generateUniqueInvitationToken(),
                    'invited_email' => $memberUser->email,
                    'invitation_expired_at' => $inviteExpiresAt,
                ]);
            }

            $this->registrationCounter->reserveLocked($lockedEvent, $teamSize);

            return [
                'leader' => $leader->fresh(),
                'members' => array_map(fn (FormAnswer $r) => $r->fresh(), $memberRows),
            ];
        });
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
