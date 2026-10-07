<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\User;
use App\Support\Database\UniqueConstraintViolation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class AttendanceService
{
    public function __construct(
        private readonly AttendanceCheckInResolver $resolver,
        private readonly RecruitmentActivityLogger $activityLogger,
        private readonly InterviewLifecycleService $interviewLifecycle,
    ) {
    }

    /**
     * @return array{
     *     duplicate: bool,
     *     attendance: RecruitmentAttendance,
     *     interview: \App\Models\Recruitment\RecruitmentInterview|null,
     *     application: RecruitmentApplication
     * }
     */
    public function checkIn(
        RecruitmentInterviewSession $session,
        RecruitmentApplication $application,
        AttendanceMethod $method,
        ?User $operator = null,
        ?Request $request = null,
    ): array {
        $this->assertEligibleForCheckIn($session, $application);

        return DB::transaction(function () use ($session, $application, $method, $operator, $request): array {
            try {
                $attendance = RecruitmentAttendance::query()->create([
                    'recruitment_application_id' => $application->id,
                    'recruitment_interview_session_id' => $session->id,
                    'method' => $method,
                    'checked_in_at' => now(),
                    'checked_in_by' => $operator?->id,
                ]);
            } catch (QueryException $exception) {
                if (! UniqueConstraintViolation::isViolation($exception)) {
                    throw $exception;
                }

                $existing = RecruitmentAttendance::query()
                    ->where('recruitment_application_id', $application->id)
                    ->first();

                if ($existing === null) {
                    throw $exception;
                }

                $application->loadMissing('primaryInterview');

                return [
                    'duplicate' => true,
                    'attendance' => $existing,
                    'interview' => $application->primaryInterview,
                    'application' => $application,
                ];
            }

            $interview = $this->interviewLifecycle->syncOrCreateWaitingInterview($application, $session);

            if ($operator !== null) {
                $this->activityLogger->log(
                    'attendance.check_in',
                    $operator,
                    $application,
                    [],
                    [
                        'method' => $method->value,
                        'session_id' => $session->id,
                    ],
                    'recruitment_attendance',
                    $attendance->id,
                    $request,
                );
            } else {
                $this->activityLogger->logApplicant(
                    'attendance.check_in',
                    $application,
                    [],
                    [
                        'method' => $method->value,
                        'session_id' => $session->id,
                    ],
                    'recruitment_attendance',
                    $attendance->id,
                    $request,
                );
            }

            return [
                'duplicate' => false,
                'attendance' => $attendance,
                'interview' => $interview,
                'application' => $application->fresh(['primaryInterview']),
            ];
        });
    }

    /**
     * @return array{
     *     duplicate: bool,
     *     attendance: RecruitmentAttendance,
     *     interview: \App\Models\Recruitment\RecruitmentInterview|null,
     *     application: RecruitmentApplication
     * }
     */
    public function checkInFromInput(
        RecruitmentInterviewSession $session,
        ?string $registrationNumber,
        ?string $applicationId,
        ?string $rawPayload,
        ?User $operator = null,
        ?Request $request = null,
    ): array {
        try {
            $application = $this->resolver->resolveApplication(
                $session,
                $registrationNumber,
                $applicationId,
                $rawPayload,
            );
        } catch (ModelNotFoundException $exception) {
            throw ValidationException::withMessages([
                'payload' => [$exception->getMessage()],
            ]);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'payload' => [$exception->getMessage()],
            ]);
        }

        $method = $this->resolver->resolveMethod($registrationNumber, $applicationId, $rawPayload);

        return $this->checkIn($session, $application, $method, $operator, $request);
    }

    private function assertEligibleForCheckIn(
        RecruitmentInterviewSession $session,
        RecruitmentApplication $application,
    ): void {
        if (! $session->is_active) {
            throw ValidationException::withMessages([
                'session' => ['Interview session is not active.'],
            ]);
        }

        if ($application->stage !== ApplicationStage::Interview) {
            throw ValidationException::withMessages([
                'application' => ['Applicant is not in the interview stage.'],
            ]);
        }

        if ($application->recruitment_period_id !== $session->recruitment_period_id) {
            throw ValidationException::withMessages([
                'application' => ['Applicant is not in the same recruitment period as this session.'],
            ]);
        }

        if ($application->primary_division_id !== $session->recruitment_division_id) {
            throw ValidationException::withMessages([
                'application' => ['Applicant primary division does not match this interview session.'],
            ]);
        }

        $application->loadMissing('primaryInterview');

        $interview = $application->primaryInterview;

        if ($interview !== null) {
            if ($interview->status === InterviewStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => ['Interview already completed for this applicant.'],
                ]);
            }

            if ($interview->status === InterviewStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => ['Applicant is currently in an interview.'],
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function applicantPayload(RecruitmentApplication $application): array
    {
        $application->loadMissing('primaryInterview');

        return [
            'name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'application_id' => $application->id,
            'interview_status' => $application->primaryInterview?->status->value,
            'interview_status_label' => $application->primaryInterview?->status?->label(),
        ];
    }
}
