<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\AttendanceMethod;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Support\RecruitmentQrPayload;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AttendanceCheckInResolver
{
    /**
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public function resolveApplication(
        RecruitmentInterviewSession $session,
        ?string $registrationNumber,
        ?string $applicationId,
        ?string $rawPayload,
    ): RecruitmentApplication {
        $resolvedApplicationId = $applicationId;

        if ($resolvedApplicationId === null && filled($rawPayload)) {
            $resolvedApplicationId = RecruitmentQrPayload::tryDecodeApplicationId($rawPayload);
        }

        if ($resolvedApplicationId !== null) {
            $application = RecruitmentApplication::query()
                ->whereKey($resolvedApplicationId)
                ->where('recruitment_period_id', $session->recruitment_period_id)
                ->first();

            if ($application === null) {
                throw new ModelNotFoundException('Applicant not found for this session.');
            }

            return $application;
        }

        $normalizedNumber = Str::upper(trim((string) $registrationNumber));

        if ($normalizedNumber === '') {
            throw new InvalidArgumentException('Provide a registration number or QR payload.');
        }

        $application = RecruitmentApplication::query()
            ->where('registration_number', $normalizedNumber)
            ->where('recruitment_period_id', $session->recruitment_period_id)
            ->first();

        if ($application === null) {
            throw new ModelNotFoundException('Registration number not found for this period.');
        }

        return $application;
    }

    public function resolveMethod(
        ?string $registrationNumber,
        ?string $applicationId,
        ?string $rawPayload,
    ): AttendanceMethod {
        if (filled($rawPayload) || filled($applicationId)) {
            return AttendanceMethod::Qr;
        }

        return AttendanceMethod::RegistrationNumber;
    }

    public function resolveSessionForApplication(RecruitmentApplication $application): RecruitmentInterviewSession
    {
        $application->loadMissing('interview.session', 'attendance.session');

        if ($application->attendance?->session !== null) {
            return $application->attendance->session;
        }

        if ($application->interview?->session !== null) {
            return $application->interview->session;
        }

        $sessions = RecruitmentInterviewSession::query()
            ->where('recruitment_period_id', $application->recruitment_period_id)
            ->where('recruitment_division_id', $application->primary_division_id)
            ->where('is_active', true)
            ->whereDate('session_date', today())
            ->orderBy('starts_at')
            ->get();

        if ($sessions->count() === 1) {
            return $sessions->first();
        }

        if ($sessions->isEmpty()) {
            throw new InvalidArgumentException('No active interview session today for this applicant division.');
        }

        throw new InvalidArgumentException(
            'Multiple active interview sessions today. Select the session on the scan screen before check-in.',
        );
    }
}
