<?php

namespace App\Services\Attendance;

use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Support\RegistrationQrPayload;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceScanSubmissionResolver
{
    /**
     * Resolve the scanned submission for this event.
     *
     * Priority: {@see $submissionId} → {@see $registrationCode} → {@see $rawPayload}.
     *
     * @throws ValidationException
     */
    public function resolve(
        Event $event,
        ?string $submissionId,
        ?string $registrationCode,
        ?string $rawPayload,
    ): FormAnswer {
        $resolvedId = $this->resolveSubmissionUuid($submissionId, $registrationCode, $rawPayload);

        $validator = Validator::make([], []);
        if ($resolvedId === null) {
            $validator->errors()->add(
                'payload',
                __('Unable to read a submission ID from the QR text or registration code.'),
            );
            throw new ValidationException($validator);
        }

        $answer = FormAnswer::query()
            ->with(['form', 'user'])
            ->whereKey($resolvedId)
            ->first();

        if ($answer === null) {
            $validator->errors()->add('payload', __('Registration not found.'));
            throw new ValidationException($validator);
        }

        if ($answer->form === null || $answer->form->event_id !== $event->id) {
            $validator->errors()->add('payload', __('This registration is not for this event.'));
            throw new ValidationException($validator);
        }

        if ($answer->review_status !== FormAnswerReviewStatus::Accepted) {
            $validator->errors()->add('payload', __('Only accepted registrations can check in.'));
            throw new ValidationException($validator);
        }

        if ($answer->user === null) {
            $validator->errors()->add('payload', __('Registration has no participant account.'));
            throw new ValidationException($validator);
        }

        return $answer;
    }

    private function resolveSubmissionUuid(
        ?string $submissionId,
        ?string $registrationCode,
        ?string $rawPayload,
    ): ?string {
        $sid = $submissionId !== null ? trim($submissionId) : '';
        if ($sid !== '' && Str::isUuid($sid)) {
            return $sid;
        }

        $code = $registrationCode !== null ? trim($registrationCode) : '';
        if ($code !== '') {
            $answerId = FormAnswer::query()
                ->whereRaw('LOWER(registration_code) = ?', [mb_strtolower($code)])
                ->value('id');

            return is_string($answerId) ? $answerId : null;
        }

        $raw = $rawPayload !== null ? trim($rawPayload) : '';
        if ($raw === '') {
            return null;
        }

        $fromQr = RegistrationQrPayload::tryDecodeSubmissionId($raw);
        if ($fromQr !== null) {
            return $fromQr;
        }

        if (Str::isUuid($raw)) {
            return $raw;
        }

        return null;
    }
}
