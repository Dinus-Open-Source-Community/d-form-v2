<?php

namespace App\Services\Registration;

use App\Enums\FormSubmissionMode;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates mode-aware form validation, participant preflight, and answer
 * mapping without taking ownership of HTTP responses or submission writes.
 */
final class FormSubmissionOrchestrator
{
    public function __construct(
        private FormAnswerInputMapper $formAnswerInputMapper,
        private BundleGuestDuplicateChecker $bundleGuestDuplicateChecker,
    ) {
    }

    /**
     * Validate and map the submitted payload for the configured mode.
     *
     * Mapping order is intentional: team mapping retains its historical order,
     * while bundle duplicate preflight runs before file mapping so rejected
     * duplicates do not create new upload paths.
     *
     * @throws ValidationException
     */
    public function validateAndMap(FormSubmissionContext $context): FormSubmissionPayload
    {
        $validated = FormSubmissionRules::validate(
            $context->input,
            $context->fields,
            $context->mode,
            $context->teamSize,
        );

        /** @var array{leader: array<string, mixed>, members: list<array<string, mixed>>} $answers */
        $answers = ['leader' => [], 'members' => []];
        $participantEmails = [];
        $resolvedGuestIdsByEmail = null;

        if ($context->mode === FormSubmissionMode::Team) {
            $answers = $this->formAnswerInputMapper->map($validated, $context->fields, $context->form);
        }

        if (in_array($context->mode, [FormSubmissionMode::Bundle, FormSubmissionMode::Team], true)) {
            $participantEmails = MemberEmailListRules::resolve(
                $context->input['team_member_emails'] ?? null,
                $context->user->email,
                $context->teamSize - 1,
            );
        }

        if ($context->mode === FormSubmissionMode::Bundle) {
            $resolvedGuestIdsByEmail = $this->bundleGuestDuplicateChecker
                ->assertEmailsAvailableForForm($context->form, $participantEmails);
            $answers = $this->formAnswerInputMapper->map(
                $validated,
                $context->fields,
                $context->form,
                $context->teamSize - 1,
            );
        }

        if (in_array($context->mode, [FormSubmissionMode::Single, FormSubmissionMode::Other], true)) {
            $answers = $this->formAnswerInputMapper->map($validated, $context->fields, $context->form);
        }

        return new FormSubmissionPayload(
            $context->mode,
            $answers,
            $participantEmails,
            $resolvedGuestIdsByEmail,
        );
    }
}
