<?php

namespace App\Services\Registration;

use App\Enums\FormPurpose;
use App\Enums\FormSubmissionMode;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

/** Validation seam for the form submission boundary: resolves the mode, builds the Laravel rules per mode, and returns only validated input. */
final class FormSubmissionRules
{
    /** Return null for non-registration forms carrying team/bundle metadata so callers can reject early. */
    public static function modeFor(Form $form): ?FormSubmissionMode
    {
        $isTeam = TeamRegistrationSubmitter::isTeamForm($form);
        $isBundle = BundleRegistrationSubmitter::isBundleForm($form);

        $isRegistrationPurpose = $form->isRegistrationForm() || $form->purpose() === FormPurpose::DKelas;
        if (! $isRegistrationPurpose) {
            return $isTeam || $isBundle ? null : FormSubmissionMode::Other;
        }

        $metadata = is_array($form->metadata) ? $form->metadata : [];
        $rawMode = $metadata['registration_mode'] ?? FormSubmissionMode::Single->value;
        $mode = is_string($rawMode) && $rawMode !== '' ? strtolower($rawMode) : FormSubmissionMode::Single->value;

        return match ($mode) {
            FormSubmissionMode::Single->value => FormSubmissionMode::Single,
            FormSubmissionMode::Team->value => $isTeam ? FormSubmissionMode::Team : null,
            FormSubmissionMode::Bundle->value => $isBundle ? FormSubmissionMode::Bundle : null,
            default => null,
        };
    }

    /** Validate the payload in load-bearing order: bundle size before fields, team size after fields.
     * @param  array<string, mixed>  $input
     * @param  Collection<int, FormField>  $fields
     * @return array<string, mixed> validated input only
     * @throws ValidationException
     */
    public static function validate(
        array $input,
        Collection $fields,
        FormSubmissionMode $mode,
        int $teamSize,
        ?array $allowedCourseValues = null,
        ?Form $form = null,
    ): array {
        if ($mode === FormSubmissionMode::Bundle && $teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This bundle form requires a team size of at least 2 in form settings.'),
            ]);
        }

        $rules = self::forMode($fields, $mode, $teamSize);
        $courseField = $fields->first(fn (FormField $field): bool => $field->name === 'du_course_uid');
        if ($courseField !== null && $allowedCourseValues !== null) {
            // Defense in depth: the orchestrator pre-check is the stricter gate (unique resolution);
            // Rule::in alone would still accept an ambiguous title listed in the catalog.
            $courseRule = ['required', 'string', Rule::in($allowedCourseValues)];
            $rules['du_course_uid'] = $courseRule;
            if ($mode === FormSubmissionMode::Bundle) {
                for ($index = 0; $index < $teamSize - 1; $index++) {
                    $rules[BundleSubmissionRules::slotInputName('du_course_uid', $index)] = $courseRule;
                }
            }
        }

        if ($form?->isReferralActive() === true) {
            $rules['referral_code'] = ['nullable', 'string', 'max:29'];
        } else {
            $rules['referral_code'] = ['prohibited'];
        }

        $validated = Validator::make($input, $rules)->validate();

        if ($mode === FormSubmissionMode::Team && $teamSize < 2) {
            throw ValidationException::withMessages([
                'team_member_emails' => __('This team form requires a team size of at least 2 in form settings.'),
            ]);
        }

        return $validated;
    }

    /**
     *
     * Rules for the leader payload of the given mode; bundle also validates one
     * input per duplicatable field for every extra participant slot.
     *
     * @param  Collection<int, FormField>  $fields
     * @return array<string, array<int, mixed>>
     *
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
