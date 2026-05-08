<?php

namespace App\Services\Registration;

use App\Enums\RegistrationRole;
use App\Exceptions\QuotaExceededException;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TeamRegistrationSubmitter
{
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

        $participants = array_merge([$leaderUser], $memberUsers);
        foreach ($participants as $participant) {
            if (FormAnswer::query()
                ->where('form_id', $form->id)
                ->where('user_id', $participant->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'team_member_emails' => __('A participant is already registered for this form.'),
                ]);
            }
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

            if (! $adminExemptFromQuota
                && $lockedEvent->quota !== null
                && $lockedEvent->quota > 0
                && $lockedEvent->registered_count + $teamSize > $lockedEvent->quota) {
                throw new QuotaExceededException();
            }

            $leader = FormAnswer::query()->create([
                'answers' => $answers,
                'form_id' => $form->id,
                'user_id' => (string) $leaderUser->id,
                'leader_form_answer_id' => null,
                'registration_role' => RegistrationRole::Leader,
                'status_confirmation_member' => true,
                'invitation_token' => null,
            ]);

            $memberRows = [];

            foreach ($memberUsers as $memberUser) {
                $memberRows[] = FormAnswer::query()->create([
                    'answers' => $memberAnswers,
                    'form_id' => $form->id,
                    'user_id' => (string) $memberUser->id,
                    'leader_form_answer_id' => $leader->id,
                    'registration_role' => RegistrationRole::Member,
                    'status_confirmation_member' => false,
                    'invitation_token' => $this->generateUniqueInvitationToken(),
                ]);
            }

            $lockedEvent->increment('registered_count', $teamSize);

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
