<?php

namespace App\Services\Registration;

use App\Enums\FormSubmissionMode;
use App\Enums\FormPurpose;
use App\Services\Du\DuCourseFieldService;
use App\Services\Du\DuCourseRepository;
use App\Services\Du\DuCourseResolver;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Coordinates mode-aware form validation, participant preflight, and answer mapping without taking ownership of HTTP responses or submission writes. */
final class FormSubmissionOrchestrator
{
    public function __construct(
        private FormAnswerInputMapper $formAnswerInputMapper,
        private BundleGuestDuplicateChecker $bundleGuestDuplicateChecker,
        private FormAnswerFileStorage $formAnswerFileStorage,
        private DuCourseRepository $duCourseRepository,
        private DuCourseResolver $duCourseResolver,
        private DuCourseFieldService $duCourseFields,
    ) {
    }

    /** Validate and map the payload; bundle duplicate checks run before file mapping and failed team validation cleans files.
     * @throws ValidationException
     */
    public function validateAndMap(FormSubmissionContext $context): FormSubmissionPayload
    {
        $isDKelas = $context->form->purpose() === FormPurpose::DKelas;
        if ($isDKelas) {
            $this->assertSubmittedCoursesSelectable($context);
        }

        // Per-form selection narrows the catalog at the acceptance boundary too, not only
        // in the fill payload; absent selection keeps the full active catalog.
        $allowedCourseValues = $isDKelas
            ? ($this->duCourseFields->selectableValues($context->form)
                ?? $this->duCourseRepository->activeSelectableValues())
            : null;

        $validated = FormSubmissionRules::validate(
            $context->input,
            $context->fields,
            $context->mode,
            $context->teamSize,
            $allowedCourseValues,
            $context->form,
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
            isset($validated['referral_code']) && is_string($validated['referral_code'])
                ? $validated['referral_code']
                : null,
        );
    }

    /** Rejects submitted D-Kelas course values that the cached catalog cannot resolve to one active course.
     * @throws ValidationException
     */
    private function assertSubmittedCoursesSelectable(FormSubmissionContext $context): void
    {
        $submitted = [];
        foreach ($this->submittedCourseKeys($context) as $key) {
            $value = $context->input[$key];
            if (is_string($value)) {
                $submitted[] = ['key' => $key, 'value' => $value];
            }
        }

        if ($submitted === []) {
            return;
        }

        $resolved = $this->duCourseResolver->activeUidsFor(array_column($submitted, 'value'));
        $errors = [];
        foreach ($submitted as $slot) {
            if (($resolved[$slot['value']] ?? null) === null) {
                $errors[$slot['key']] = __('Kelas DU yang dipilih tidak tersedia.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return list<string> course input keys actually present in the payload */
    private function submittedCourseKeys(FormSubmissionContext $context): array
    {
        $keys = ['du_course_uid'];

        if ($context->mode === FormSubmissionMode::Bundle) {
            for ($index = 0; $index < $context->teamSize - 1; $index++) {
                $keys[] = BundleSubmissionRules::slotInputName('du_course_uid', $index);
            }
        }

        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => array_key_exists($key, $context->input),
        ));
    }
}
