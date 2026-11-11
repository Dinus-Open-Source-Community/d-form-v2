<?php

namespace App\Services\Registration;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class BundleGuestDuplicateChecker
{
    /**
     * Reject member emails already registered (active) on the form.
     *
     * Uses two queries regardless of member count: one user lookup by normalized
     * email and one active form-answer match on invited_email or user_id.
     * Throws on the first offending index in the caller's order.
     *
     * Returns the resolved account ids so callers can reuse them without another
     * lookup (normalized email => first matching user id).
     *
     * @param  array<array-key, string>  $emails
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function assertEmailsAvailableForForm(Form $form, array $emails): array
    {
        $normalized = $this->normalizeByIndex($emails);

        if ($normalized === []) {
            return [];
        }

        $userIdsByEmail = $this->resolveUserIdsByEmail($normalized);

        $this->assertNoneTaken($form, $normalized, $userIdsByEmail);

        return $userIdsByEmail;
    }

    /**
     * Race-guard re-check inside the submit transaction.
     *
     * Only the active-answer predicate runs here. The user lookup is skipped by
     * reusing the ids resolved by {@see assertEmailsAvailableForForm()}, so a
     * submit keeps one lookup regardless of how many guards re-run.
     *
     * @param  array<array-key, string>  $emails
     * @param  array<string, string>  $userIdsByEmail  output of assertEmailsAvailableForForm()
     *
     * @throws ValidationException
     */
    public function assertEmailsStillAvailable(Form $form, array $emails, array $userIdsByEmail): void
    {
        $normalized = $this->normalizeByIndex($emails);

        if ($normalized === []) {
            return;
        }

        $this->assertNoneTaken($form, $normalized, $userIdsByEmail);
    }

    /**
     * @param  array<array-key, string>  $emails
     * @return array<array-key, string>
     */
    private function normalizeByIndex(array $emails): array
    {
        $normalized = [];

        foreach ($emails as $index => $email) {
            $value = mb_strtolower(trim((string) $email));

            if ($value === '') {
                continue;
            }

            $normalized[$index] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<array-key, string>  $normalized
     * @return array<string, string>
     */
    private function resolveUserIdsByEmail(array $normalized): array
    {
        $userIdsByEmail = [];

        foreach (User::resolveByNormalizedEmails($normalized) as $email => $user) {
            // Mirror the old User::...->first() semantics: keep the first user id
            // per input email, but still pass every resolved id to the user_id branch.
            $userIdsByEmail[$email] = (string) $user->id;
        }

        return $userIdsByEmail;
    }

    /**
     * @param  array<array-key, string>  $normalized
     * @param  array<string, string>  $userIdsByEmail
     *
     * @throws ValidationException
     */
    private function assertNoneTaken(Form $form, array $normalized, array $userIdsByEmail): void
    {
        $uniqueEmails = array_values(array_unique($normalized));
        $userIds = array_values($userIdsByEmail);

        $takenEmails = [];
        $takenUserIds = [];

        $rows = FormAnswer::query()
            ->where('form_id', $form->id)
            ->excludeRejectedSubmissions()
            ->where(fn (Builder $query) => $query
                // `invited_email` is `utf8mb4_unicode_ci` (case/accent-insensitive)
                // on the production engines and the bundle write path stores it
                // lowercased, so the plain predicate is equivalent to the old
                // LOWER(invited_email) form while staying index-usable.
                ->whereIn('invited_email', $uniqueEmails)
                ->when($userIds !== [], fn (Builder $query) => $query->orWhereIn('user_id', $userIds)))
            ->get(['invited_email', 'user_id']);

        foreach ($rows as $row) {
            // The DB collation is looser than this map (accent-insensitive and
            // PAD SPACE), so a rare accent/space-only duplicate is only caught by
            // the unique index, not here. Keep the PHP comparison simple.
            if ($row->invited_email !== null) {
                $takenEmails[mb_strtolower(trim((string) $row->invited_email))] = true;
            }

            if ($row->user_id !== null) {
                $takenUserIds[(string) $row->user_id] = true;
            }
        }

        foreach ($normalized as $index => $email) {
            $userId = $userIdsByEmail[$email] ?? null;

            if (isset($takenEmails[$email]) || ($userId !== null && isset($takenUserIds[$userId]))) {
                throw ValidationException::withMessages([
                    "team_member_emails.{$index}" => __('A participant with this email is already registered for this form.'),
                ]);
            }
        }
    }
}
