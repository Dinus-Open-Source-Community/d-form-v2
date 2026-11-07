<?php

namespace App\Services\Registration;

use App\Enums\FormSubmissionMode;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validation seam for the form submission boundary: resolves the mode, builds
 * the Laravel rules per mode, and returns only validated input.
 */
final class FormSubmissionRules
{
    /**
     * Resolve the submission mode from form metadata.
     *
     * Returns `null` when a non-registration form still carries team/bundle
     * metadata so the caller can reject the request before validating.
     */
    public static function modeFor(Form $form): ?FormSubmissionMode
    {
        $isTeam = TeamRegistrationSubmitter::isTeamForm($form);
        $isBundle = BundleRegistrationSubmitter::isBundleForm($form);

        if (! $form->isRegistrationForm()) {
            return $isTeam || $isBundle ? null : FormSubmissionMode::Other;
        }

        if ($isTeam) {
            return FormSubmissionMode::Team;
        }

        return $isBundle ? FormSubmissionMode::Bundle : FormSubmissionMode::Single;
    }

    /**
     * Validate the submitted payload for the given mode.
     *
     * Order is historical and load-bearing: bundle size guard before field
     * validation, team size guard after it.
     *
     * @param  array<string, mixed>  $input
     * @param  Collection<int, FormField>  $fields
     * @return array<string, mixed> validated input only
     *
     * @throws ValidationException
     */
    public static function validate(array $input, Collection $fields, FormSubmissionMode $mode, int $teamSize): array
    {
        if ($mode === FormSubmissionMode::Bundle && $teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This bundle form requires a team size of at least 2 in form settings.'),
            ]);
        }

        $validated = Validator::make($input, self::forMode($fields, $mode, $teamSize))->validate();

        if ($mode === FormSubmissionMode::Team && $teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This team form requires a team size of at least 2 in form settings.'),
            ]);
        }

        return $validated;
    }

    /**
     * Rules for the leader payload of the given mode; bundle also validates one
     * input per duplicatable field for every extra participant slot.
     *
     * @param  Collection<int, FormField>  $fields
     * @return array<string, array<int, mixed>>
     */
    private static function forMode(Collection $fields, FormSubmissionMode $mode, int $teamSize): array
    {
        if ($mode === FormSubmissionMode::Bundle) {
            return BundleSubmissionRules::buildForFill($fields, $teamSize - 1, requireScalarString: true);
        }

        return RulesBuilder::build(
            RulesBuilder::extractRulesFromFields($fields),
            requireScalarString: true,
        );
    }
}
