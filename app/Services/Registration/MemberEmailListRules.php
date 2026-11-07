<?php

namespace App\Services\Registration;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Single implementation for the team/bundle participant email list: validation
 * rules, normalization, and the self/duplicate guard.
 */
final class MemberEmailListRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function validationRules(int $memberSlots): array
    {
        return [
            'team_member_emails'   => ['required', 'array', 'list', "size:{$memberSlots}"],
            'team_member_emails.*' => ['required', 'string', 'email:rfc'],
        ];
    }

    /**
     * Validate, normalize, and guard the participant email list.
     *
     * @return array<array-key, string> normalized lowercase emails
     *
     * @throws ValidationException when the list is invalid, lists the leader, or repeats an email.
     */
    public static function resolve(mixed $rawEmails, string $leaderEmail, int $memberSlots): array
    {
        $validated = Validator::make(
            ['team_member_emails' => $rawEmails],
            self::validationRules($memberSlots),
        )->validate();

        $list = $validated['team_member_emails'] ?? [];
        $emails = self::normalize(is_array($list) ? $list : []);

        self::assertNotSelf($leaderEmail, $emails);
        self::assertUnique($emails);

        return $emails;
    }

    /**
     * Lowercase and trim every email while preserving the submitted indexes.
     *
     * @param  array<array-key, mixed>  $rawEmails
     * @return array<array-key, string>
     */
    public static function normalize(array $rawEmails): array
    {
        $emails = [];

        foreach ($rawEmails as $index => $value) {
            $emails[$index] = mb_strtolower(trim((string) $value));
        }

        return $emails;
    }

    /**
     * @param  array<array-key, string>  $emails
     *
     * @throws ValidationException when the leader is listed as a participant.
     */
    public static function assertNotSelf(string $leaderEmail, array $emails): void
    {
        $leaderLower = mb_strtolower(trim($leaderEmail));

        foreach ($emails as $index => $email) {
            if ($email === $leaderLower) {
                throw ValidationException::withMessages([
                    "team_member_emails.{$index}" => __('You cannot list yourself as a team member.'),
                ]);
            }
        }
    }

    /**
     * @param  array<array-key, string>  $emails
     *
     * @throws ValidationException when the same email is listed twice.
     */
    public static function assertUnique(array $emails): void
    {
        if (count(array_unique($emails)) !== count($emails)) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('Each team member email must be unique.'),
            ]);
        }
    }
}
