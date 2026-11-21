<?php

namespace App\Services\Form;

use App\Enums\FormSubmissionMode;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Registration\FormAnswerFileStorage;
use App\Services\Registration\FormAnswerInputMapper;
use App\Services\Registration\FormAnswerWriter;
use App\Services\Registration\FormSubmissionRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Validates, maps, and persists one anonymous non-registration submission. */
final class GuestFormSubmissionService
{
    public function __construct(
        private FormAnswerInputMapper $inputMapper,
        private FormAnswerWriter $writer,
        private FormAnswerFileStorage $fileStorage,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function submit(GuestFormSubmissionContext $context): FormAnswer
    {
        $mappedAnswers = [];

        try {
            $validated = FormSubmissionRules::validate(
                $context->input,
                $context->fields,
                FormSubmissionMode::Other,
                0,
            );
            $payload = $this->inputMapper->map($validated, $context->fields, $context->form);
            $mappedAnswers = $payload['leader'];

            return DB::transaction(function () use ($context, $mappedAnswers): FormAnswer {
                $form = Form::query()->lockForUpdate()->find($context->form->id);
                if ($form === null) {
                    throw new \RuntimeException('Form not found.');
                }

                FormSubmissionLimit::assertGuestAvailable($form, $context->guestTokenHash);

                return $this->writer->create([
                    'answers' => $mappedAnswers,
                    'form_id' => $form->id,
                    'user_id' => null,
                    'guest_token_hash' => $context->guestTokenHash,
                ]);
            });
        } catch (ValidationException $exception) {
            $this->fileStorage->discardMappedUploads($context->form, $mappedAnswers);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->fileStorage->discardMappedUploads($context->form, $mappedAnswers);
            throw $exception;
        }
    }
}
