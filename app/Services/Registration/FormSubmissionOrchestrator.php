<?php

namespace App\Services\Registration;

use App\Enums\FormSubmissionMode;
use Illuminate\Support\Facades\Validator;
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
        private FormAnswerFileStorage $formAnswerFileStorage,
    ) {
    }

    /**
     * Validate and map the submitted payload for the configured mode.
     *
     * Team mapping retains its existing order and cleans files if participant
     * validation fails; bundle duplicate preflight runs before file mapping so
     * rejected guests do not create new upload paths.
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

        try {
            if (in_array($context->mode, [FormSubmissionMode::Bundle, FormSubmissionMode::Team], true)) {
                $validatedParticipantInput = Validator::make(
                    ['team_member_emails' => $context->input['team_member_emails'] ?? null],
                    MemberEmailListRules::validationRules($context->teamSize - 1),
                )->validate();

                $participantEmails = MemberEmailListRules::resolve(
                    $validatedParticipantInput['team_member_emails'],
                    $context->user->email,
                    $context->teamSize - 1,
                );
            }
        } catch (ValidationException $exception) {
            $this->formAnswerFileStorage->discardMappedUploads($context->form, $answers['leader']);

            throw $exception;
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
