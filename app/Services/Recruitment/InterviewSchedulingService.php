<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Jobs\Recruitment\SendRecruitmentInterviewerNotificationJob;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InterviewSchedulingService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /**
     * @param  list<string>  $applicationIds
     * @return list<RecruitmentInterview>
     */
    public function scheduleApplicants(
        User $staff,
        RecruitmentInterviewSession $session,
        array $applicationIds,
        ?Request $request = null,
    ): array {
        if ($applicationIds === []) {
            throw ValidationException::withMessages([
                'application_ids' => 'Pilih minimal satu applicant.',
            ]);
        }

        $applications = RecruitmentApplication::query()
            ->whereIn('id', $applicationIds)
            ->get();

        if ($applications->count() !== count($applicationIds)) {
            throw ValidationException::withMessages([
                'application_ids' => 'Applicant tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($staff, $session, $applications, $request): array {
            $scheduled = [];

            foreach ($applications as $application) {
                $scheduled[] = $this->scheduleApplication($staff, $session, $application, null, $request, false);
            }

            return $scheduled;
        });
    }

    public function scheduleApplication(
        User $staff,
        RecruitmentInterviewSession $session,
        RecruitmentApplication $application,
        ?string $interviewerId = null,
        ?Request $request = null,
        bool $wrapTransaction = true,
    ): RecruitmentInterview {
        $callback = function () use ($staff, $session, $application, $interviewerId, $request): RecruitmentInterview {
            $this->assertSchedulable($application, $session);

            if ($application->interview()->exists()) {
                throw ValidationException::withMessages([
                    'application' => 'Applicant sudah memiliki jadwal interview.',
                ]);
            }

            $interviewer = $this->resolveInterviewer($application, $interviewerId);
            $scheduledAt = $this->buildScheduledAt($session);

            $interview = RecruitmentInterview::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_session_id' => $session->id,
                'interviewer_id' => $interviewer->id,
                'scheduled_at' => $scheduledAt,
                'location' => $session->location,
                'room' => $session->room,
                'status' => InterviewStatus::Scheduled,
            ]);

            $this->activityLogger->log(
                action: 'interview.scheduled',
                actor: $staff,
                application: $application,
                newValues: [
                    'interview_id' => $interview->id,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                    'interviewer_id' => $interviewer->id,
                ],
                entityType: 'recruitment_interview',
                entityId: $interview->id,
                request: $request,
            );

            SendRecruitmentNotificationJob::dispatch(
                $application->id,
                'interview_scheduled',
                $interview->id,
            );

            SendRecruitmentInterviewerNotificationJob::dispatch(
                $interview->id,
                'interview_assignment',
            );

            return $interview;
        };

        return $wrapTransaction ? DB::transaction($callback) : $callback();
    }

    public function reschedule(
        User $staff,
        RecruitmentInterview $interview,
        RecruitmentInterviewSession $session,
        ?Request $request = null,
    ): RecruitmentInterview {
        if ($interview->status === InterviewStatus::Cancelled) {
            throw ValidationException::withMessages([
                'interview' => 'Interview yang dibatalkan tidak dapat dijadwalkan ulang.',
            ]);
        }

        return DB::transaction(function () use ($staff, $interview, $session, $request): RecruitmentInterview {
            $application = $interview->application;
            $oldValues = [
                'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
                'session_id' => $interview->recruitment_interview_session_id,
            ];

            if ($application !== null) {
                $this->assertSchedulable($application, $session, allowExistingInterview: true);
            }

            $scheduledAt = $this->buildScheduledAt($session);

            $interview->update([
                'recruitment_interview_session_id' => $session->id,
                'scheduled_at' => $scheduledAt,
                'location' => $session->location,
                'room' => $session->room,
                'status' => InterviewStatus::Scheduled,
                'reminder_h1_sent_at' => null,
                'reminder_h2_sent_at' => null,
            ]);

            $this->activityLogger->log(
                action: 'interview.rescheduled',
                actor: $staff,
                application: $application,
                oldValues: $oldValues,
                newValues: [
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                    'session_id' => $session->id,
                ],
                entityType: 'recruitment_interview',
                entityId: $interview->id,
                request: $request,
            );

            if ($application !== null) {
                SendRecruitmentNotificationJob::dispatch(
                    $application->id,
                    'interview_rescheduled',
                    $interview->id,
                );
            }

            return $interview->fresh(['application', 'interviewer', 'session']);
        });
    }

    public function reassign(
        User $staff,
        RecruitmentInterview $interview,
        User $interviewer,
        ?Request $request = null,
    ): RecruitmentInterview {
        if ($interview->status === InterviewStatus::Cancelled) {
            throw ValidationException::withMessages([
                'interview' => 'Interview yang dibatalkan tidak dapat ditugaskan ulang.',
            ]);
        }

        $application = $interview->application;

        if ($application === null) {
            throw ValidationException::withMessages([
                'interview' => 'Applicant tidak ditemukan.',
            ]);
        }

        $this->assertInterviewerForDivision($interviewer->id, $application->primary_division_id);

        return DB::transaction(function () use ($staff, $interview, $interviewer, $application, $request): RecruitmentInterview {
            $oldInterviewerId = $interview->interviewer_id;

            $interview->update([
                'interviewer_id' => $interviewer->id,
            ]);

            $this->activityLogger->log(
                action: 'interview.reassigned',
                actor: $staff,
                application: $application,
                oldValues: ['interviewer_id' => $oldInterviewerId],
                newValues: ['interviewer_id' => $interviewer->id],
                entityType: 'recruitment_interview',
                entityId: $interview->id,
                request: $request,
            );

            SendRecruitmentInterviewerNotificationJob::dispatch(
                $interview->id,
                'interview_assignment',
            );

            return $interview->fresh(['application', 'interviewer', 'session']);
        });
    }

    public function cancel(User $staff, RecruitmentInterview $interview, ?Request $request = null): RecruitmentInterview
    {
        return DB::transaction(function () use ($staff, $interview, $request): RecruitmentInterview {
            $interview->update([
                'status' => InterviewStatus::Cancelled,
            ]);

            $this->activityLogger->log(
                action: 'interview.cancelled',
                actor: $staff,
                application: $interview->application,
                entityType: 'recruitment_interview',
                entityId: $interview->id,
                request: $request,
            );

            return $interview->fresh();
        });
    }

    /**
     * @return Collection<int, RecruitmentApplication>
     */
    public function eligibleApplicants(RecruitmentInterviewSession $session): Collection
    {
        return RecruitmentApplication::query()
            ->where('recruitment_period_id', $session->recruitment_period_id)
            ->where('primary_division_id', $session->recruitment_division_id)
            ->where('stage', ApplicationStage::Interview)
            ->whereDoesntHave('interview')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'registration_number', 'nim']);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function interviewerOptionsForDivision(string $divisionId): array
    {
        return RecruitmentInterviewerDivision::query()
            ->where('recruitment_division_id', $divisionId)
            ->with('user:id,name,email')
            ->get()
            ->map(fn (RecruitmentInterviewerDivision $assignment): array => [
                'id' => $assignment->user->id,
                'name' => $assignment->user->name,
            ])
            ->values()
            ->all();
    }

    private function assertSchedulable(
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
        bool $allowExistingInterview = false,
    ): void {
        if ($application->stage !== ApplicationStage::Interview) {
            throw ValidationException::withMessages([
                'application' => 'Applicant belum lolos tahap screening.',
            ]);
        }

        if ($application->recruitment_period_id !== $session->recruitment_period_id) {
            throw ValidationException::withMessages([
                'application' => 'Applicant tidak berada pada periode yang sama dengan sesi interview.',
            ]);
        }

        if ($application->primary_division_id !== $session->recruitment_division_id) {
            throw ValidationException::withMessages([
                'application' => 'Divisi utama applicant tidak sesuai dengan sesi interview.',
            ]);
        }

        if (! $allowExistingInterview && $application->interview()->exists()) {
            throw ValidationException::withMessages([
                'application' => 'Applicant sudah memiliki jadwal interview.',
            ]);
        }
    }

    private function resolveInterviewer(RecruitmentApplication $application, ?string $interviewerId): User
    {
        if ($interviewerId !== null) {
            $this->assertInterviewerForDivision($interviewerId, $application->primary_division_id);

            return User::query()->findOrFail($interviewerId);
        }

        $assignment = RecruitmentInterviewerDivision::query()
            ->where('recruitment_division_id', $application->primary_division_id)
            ->with('user')
            ->orderBy('created_at')
            ->first();

        if ($assignment?->user === null) {
            throw ValidationException::withMessages([
                'interviewer' => 'Belum ada interviewer untuk divisi ini.',
            ]);
        }

        return $assignment->user;
    }

    private function assertInterviewerForDivision(string $interviewerId, string $divisionId): void
    {
        $exists = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewerId)
            ->where('recruitment_division_id', $divisionId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'interviewer_id' => 'Interviewer tidak ditugaskan pada divisi applicant.',
            ]);
        }
    }

    private function buildScheduledAt(RecruitmentInterviewSession $session): Carbon
    {
        $date = $session->session_date?->format('Y-m-d');
        $time = substr((string) $session->starts_at, 0, 8);

        return Carbon::parse($date.' '.$time, config('app.timezone'));
    }
}
