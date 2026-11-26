<?php

namespace App\Services\Form;

use App\Enums\FormPurpose;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Resolves and enforces the active per-user submission cap for a form. */
final class FormSubmissionLimit
{
    /** Return the total active submissions permitted for this form. */
    public static function for(Form $form): int
    {
        if (in_array($form->purpose(), [FormPurpose::Registration, FormPurpose::DKelas], true)) {
            return 1;
        }

        $metadata = is_array($form->metadata) ? $form->metadata : [];
        if (($metadata['allow_multiple_submissions'] ?? false) !== true) {
            return 1;
        }

        $limit = $metadata['max_submissions'] ?? 2;

        return is_int($limit) && $limit >= 2 ? $limit : 2;
    }

    /** Count non-rejected submissions for one user and form. */
    public static function activeCount(Form $form, User $user): int
    {
        return FormAnswer::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->excludeRejectedSubmissions()
            ->count();
    }

    /** Return whether the actor can submit another active response. */
    public static function canSubmitAgain(Form $form, User|string $actor): bool
    {
        $count = $actor instanceof User
            ? self::activeCount($form, $actor)
            : self::activeGuestCount($form, $actor);

        return $count < self::for($form);
    }

    /** Count active guest submissions for one form and browser token. */
    public static function activeGuestCount(Form $form, string $guestTokenHash): int
    {
        return FormAnswer::query()
            ->where('form_id', $form->id)
            ->whereNull('user_id')
            ->where('guest_token_hash', $guestTokenHash)
            ->where('is_active', 1)
            ->count();
    }

    /** Reject a guest attempt when the active count has reached the effective cap. */
    public static function assertGuestAvailable(Form $form, string $guestTokenHash): void
    {
        if (self::activeGuestCount($form, $guestTokenHash) >= self::for($form)) {
            throw ValidationException::withMessages([
                'form' => __('You have already submitted this form.'),
            ]);
        }
    }

    /** Reject the authenticated attempt after the caller locks the form row in its transaction. */
    public static function assertAvailable(Form $form, User $user): void
    {
        if (self::activeCount($form, $user) >= self::for($form)) {
            throw ValidationException::withMessages([
                'form' => __('You have already submitted this form.'),
            ]);
        }
    }
}
