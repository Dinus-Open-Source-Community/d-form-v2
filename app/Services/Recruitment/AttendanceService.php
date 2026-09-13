<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentQueueEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class AttendanceService
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly AttendanceCheckInResolver $resolver,
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return array{
     *     duplicate: bool,
     *     attendance: RecruitmentAttendance,
     *     queue: RecruitmentQueueEntry|null,
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

        $existing = RecruitmentAttendance::query()
            ->where('recruitment_application_id', $application->id)
            ->first();

        if ($existing !== null) {
            $application->loadMissing('queueEntry');

            return [
                'duplicate' => true,
                'attendance' => $existing,
                'queue' => $application->queueEntry,
                'application' => $application,
            ];
        }

        return DB::transaction(function () use ($session, $application, $method, $operator, $request): array {
            $attendance = RecruitmentAttendance::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_session_id' => $session->id,
                'method' => $method,
                'checked_in_at' => now(),
                'checked_in_by' => $operator?->id,
            ]);

            $queue = $this->queueService->createFromAttendance($attendance);

            $application->loadMissing('interview');
            $interview = $application->interview;

            if ($interview !== null) {
                $interview->update(['status' => InterviewStatus::Queued]);
            }

            if ($operator !== null) {
                $this->activityLogger->log(
                    'attendance.check_in',
                    $operator,
                    $application,
                    [],
                    [
                        'method' => $method->value,
                        'queue_number' => $queue->queue_number,
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
                        'queue_number' => $queue->queue_number,
                    ],
                    'recruitment_attendance',
                    $attendance->id,
                    $request,
                );
            }

            return [
                'duplicate' => false,
                'attendance' => $attendance,
                'queue' => $queue,
                'application' => $application->fresh(['queueEntry']),
            ];
        });
    }

    /**
     * @return array{
     *     duplicate: bool,
     *     attendance: RecruitmentAttendance,
     *     queue: RecruitmentQueueEntry|null,
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

    public function markNoShow(RecruitmentApplication $application, User $staff, ?Request $request = null): void
    {
        $application->loadMissing(['interview', 'attendance']);

        $interview = $application->interview;

        if ($interview === null) {
            throw ValidationException::withMessages([
                'interview' => ['Applicant has no scheduled interview.'],
            ]);
        }

        if ($application->attendance !== null) {
            throw ValidationException::withMessages([
                'attendance' => ['Applicant has already checked in.'],
            ]);
        }

        if ($interview->status === InterviewStatus::NoShow) {
            return;
        }

        if (! in_array($interview->status, [InterviewStatus::Scheduled, InterviewStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => ['Interview cannot be marked as no-show from current status.'],
            ]);
        }

        $oldStatus = $interview->status->value;

        $interview->update(['status' => InterviewStatus::NoShow]);

        $this->activityLogger->log(
            'interview.no_show',
            $staff,
            $application,
            ['status' => $oldStatus],
            ['status' => InterviewStatus::NoShow->value],
            'recruitment_interview',
            $interview->id,
            $request,
        );
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

        $application->loadMissing('interview');

        $interview = $application->interview;

        if ($interview === null) {
            throw ValidationException::withMessages([
                'application' => ['Applicant has no scheduled interview.'],
            ]);
        }

        if ($interview->recruitment_interview_session_id !== $session->id) {
            throw ValidationException::withMessages([
                'application' => ['Applicant is not scheduled for this interview session.'],
            ]);
        }

        if (! in_array($interview->status, [InterviewStatus::Scheduled, InterviewStatus::CheckedIn, InterviewStatus::Queued], true)) {
            throw ValidationException::withMessages([
                'status' => ['Interview is not eligible for check-in.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function applicantPayload(RecruitmentApplication $application): array
    {
        $application->loadMissing('queueEntry');

        return [
            'name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'application_id' => $application->id,
            'queue_number' => $application->queueEntry?->queue_number,
        ];
    }
}
