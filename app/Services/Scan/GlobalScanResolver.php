<?php

namespace App\Services\Scan;

use App\Enums\FormAnswerReviewStatus;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Registration\FormAnswerRecipientResolver;
use App\Support\RecruitmentQrPayload;
use App\Support\RegistrationQrPayload;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GlobalScanResolver
{
    public function __construct(
        private readonly FormAnswerRecipientResolver $recipientResolver,
    ) {
    }

    /**
     * @return array{kind: string, application: ?RecruitmentApplication, answer: ?FormAnswer, registrationNumber: ?string}
     */
    public function resolve(string $raw): array
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            throw ValidationException::withMessages(['payload' => ['Provide a QR payload or registration code.']]);
        }

        $applicationId = RecruitmentQrPayload::tryDecodeApplicationId($trimmed);
        if ($applicationId !== null) {
            $application = RecruitmentApplication::query()->whereKey($applicationId)->first();
            if ($application === null) {
                throw ValidationException::withMessages(['payload' => ['Applicant not found.']]);
            }

            return ['kind' => 'recruitment', 'application' => $application, 'answer' => null, 'registrationNumber' => null];
        }

        $eventResult = $this->resolveEventAnswer($trimmed);
        if ($eventResult !== null) {
            return $eventResult;
        }

        if (Str::isUuid($trimmed)) {
            $application = RecruitmentApplication::query()->whereKey($trimmed)->first();
            if ($application !== null) {
                return ['kind' => 'recruitment', 'application' => $application, 'answer' => null, 'registrationNumber' => null];
            }

            $answer = FormAnswer::query()->with(['form', 'user'])->whereKey($trimmed)->first();
            if ($answer !== null) {
                return $this->eligibleEventResult($answer);
            }
        }

        if (preg_match('/\AOPREC-[A-Z0-9-]{1,32}\z/', Str::upper($trimmed)) === 1) {
            $application = RecruitmentApplication::query()
                ->where('registration_number', Str::upper($trimmed))
                ->orderByDesc('created_at')
                ->first();
            if ($application === null) {
                throw ValidationException::withMessages(['payload' => ['Registration number not found.']]);
            }

            return ['kind' => 'recruitment', 'application' => $application, 'answer' => null, 'registrationNumber' => Str::upper($trimmed)];
        }

        $byCode = FormAnswer::query()
            ->whereRaw('LOWER(registration_code) = ?', [mb_strtolower($trimmed)])
            ->with(['form', 'user'])
            ->first();
        if ($byCode !== null) {
            return $this->eligibleEventResult($byCode);
        }

        throw ValidationException::withMessages(['payload' => ['Unable to read a submission ID from the QR text or registration code.']]);
    }

    private function resolveEventAnswer(string $trimmed): ?array
    {
        $submissionId = RegistrationQrPayload::tryDecodeSubmissionId($trimmed);
        if ($submissionId === null) {
            return null;
        }

        $answer = FormAnswer::query()->with(['form', 'user'])->whereKey($submissionId)->first();
        if ($answer === null) {
            throw ValidationException::withMessages(['payload' => ['Registration not found.']]);
        }

        return $this->eligibleEventResult($answer);
    }

    private function eligibleEventResult(FormAnswer $answer): array
    {
        if ($answer->review_status !== FormAnswerReviewStatus::Accepted) {
            throw ValidationException::withMessages(['payload' => ['Only accepted registrations can check in.']]);
        }

        if ($answer->user === null && $this->recipientResolver->email($answer) === null) {
            throw ValidationException::withMessages(['payload' => ['Registration has no participant account.']]);
        }

        return ['kind' => 'event', 'application' => null, 'answer' => $answer, 'registrationNumber' => null];
    }
}
