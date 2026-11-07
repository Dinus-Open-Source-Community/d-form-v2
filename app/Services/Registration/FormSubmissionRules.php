<?php

namespace App\Services\Registration;

use App\Enums\FormSubmissionMode;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * Validation seam for the form submission boundary: resolves the mode, builds
 * the Laravel rules per mode, and returns only validated input.
 */
final class FormSubmissionRules
{
    /**
     * Resolve the submission mode from form metadata.
     *
     * Returns `null` when the configuration is contradictory (team/bundle metadata
     * on a non-registration form, or both team and bundle enabled at once) so the
     * caller can reject the request before validating the payload.
     */
    public static function modeFor(Form $form): ?FormSubmissionMode
    {
        $isTeam = TeamRegistrationSubmitter::isTeamForm($form);
        $isBundle = BundleRegistrationSubmitter::isBundleForm($form);

        if (! $form->isRegistrationForm()) {
            return $isTeam || $isBundle ? null : FormSubmissionMode::Other;
        }

        if ($isTeam && $isBundle) {
            return null;
        }

        if ($isTeam) {
            return FormSubmissionMode::Team;
        }

        return $isBundle ? FormSubmissionMode::Bundle : FormSubmissionMode::Single;
    }

    /**
     * Validate the submitted payload for the given mode.
     *
     * @param  array<string, mixed>  $input
     * @param  Collection<int, FormField>  $fields
     * @return array<string, mixed> validated input only
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(array $input, Collection $fields, FormSubmissionMode $mode, int $teamSize): array
    {
        return Validator::make($input, self::forMode($fields, $mode, $teamSize))->validate();
    }

    /**
     * Rules for the leader payload of the given mode; bundle also validates one
     * input per duplicatable field for every extra participant slot.
     *
     * @param  Collection<int, FormField>  $fields
     * @return array<string, array<int, mixed>>
     */
    public static function forMode(Collection $fields, FormSubmissionMode $mode, int $teamSize): array
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
