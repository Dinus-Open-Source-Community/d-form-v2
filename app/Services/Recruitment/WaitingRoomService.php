<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WaitingRoomService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return list<string>
     */
    public function divisionIdsForInterviewer(User $interviewer): array
    {
        return RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->pluck('recruitment_division_id')
            ->all();
    }

    public function hasActiveInProgressBooking(User $interviewer): bool
    {
        return RecruitmentInterview::query()
            ->where('interviewer_id', $interviewer->id)
            ->where('status', InterviewStatus::InProgress)
            ->whereHas('application', fn ($q) => $q->whereDoesntHave('evaluation'))
            ->exists();
    }

    /**
     * @return Collection<int, RecruitmentApplication>
     */
    public function waitingApplications(
        User $interviewer,
        ?string $sessionId = null,
        ?string $search = null,
    ): Collection {
        $divisionIds = $this->divisionIdsForInterviewer($interviewer);

        if ($divisionIds === []) {
            return collect();
        }

        $query = RecruitmentApplication::query()
            ->whereHas('attendance')
            ->whereHas('interview', function ($q) use ($divisionIds, $sessionId): void {
                $q->where('status', InterviewStatus::Waiting)
                    ->whereNull('interviewer_id')
                    ->whereHas('session', function ($sq) use ($divisionIds, $sessionId): void {
                        $sq->whereIn('recruitment_division_id', $divisionIds);
                        if ($sessionId !== null && $sessionId !== '') {
                            $sq->where('id', $sessionId);
                        }
                    });
            })
            ->whereDoesntHave('evaluation')
            ->with([
                'attendance',
                'interview.session.division',
                'primaryDivision',
            ]);

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($q) use ($term): void {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('nim', 'like', "%{$term}%")
                    ->orWhere('registration_number', 'like', "%{$term}%");
            });
        }

        return $query
            ->join('recruitment_attendances', 'recruitment_attendances.recruitment_application_id', '=', 'recruitment_applications.id')
            ->orderBy('recruitment_attendances.checked_in_at')
            ->select('recruitment_applications.*')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function waitingPoolSnapshot(
        User $interviewer,
        ?string $sessionId = null,
        ?string $search = null,
    ): array {
        return $this->waitingApplications($interviewer, $sessionId, $search)
            ->map(fn (RecruitmentApplication $application): array => $this->applicationToPoolArray($application))
            ->values()
            ->all();
    }

    public function book(User $interviewer, RecruitmentApplication $application, ?Request $request = null): RecruitmentInterview
    {
        $this->assertInterviewerAssignedToApplicationDivision($interviewer, $application);

        if ($this->hasActiveInProgressBooking($interviewer)) {
            throw ValidationException::withMessages([
                'booking' => ['Selesaikan penilaian applicant saat ini sebelum booking yang baru.'],
            ]);
        }

        return DB::transaction(function () use ($interviewer, $application, $request): RecruitmentInterview {
            $interview = RecruitmentInterview::query()
                ->where('recruitment_application_id', $application->id)
                ->lockForUpdate()
                ->first();

            if ($interview === null) {
                throw ValidationException::withMessages([
                    'application' => ['Applicant belum absen atau tidak memiliki sesi interview.'],
                ]);
            }

            if ($interview->status !== InterviewStatus::Waiting || $interview->interviewer_id !== null) {
                throw ValidationException::withMessages([
                    'booking' => ['Applicant sudah dibooking interviewer lain atau tidak lagi di ruang tunggu.'],
                ]);
            }

            $application->loadMissing('evaluation');
            if ($application->evaluation !== null) {
                throw ValidationException::withMessages([
                    'booking' => ['Applicant sudah selesai dinilai.'],
                ]);
            }

            $interview->update([
                'interviewer_id' => $interviewer->id,
                'status' => InterviewStatus::InProgress,
                'booked_at' => now(),
            ]);

            $this->activityLogger->log(
                'interview.booked',
                $interviewer,
                $application,
                [],
                [
                    'interview_id' => $interview->id,
                    'interviewer_id' => $interviewer->id,
                ],
                'recruitment_interview',
                $interview->id,
                $request,
            );

            return $interview->fresh(['application', 'session']);
        });
    }

    public function release(User $interviewer, RecruitmentApplication $application, ?Request $request = null): RecruitmentInterview
    {
        return DB::transaction(function () use ($interviewer, $application, $request): RecruitmentInterview {
            $interview = RecruitmentInterview::query()
                ->where('recruitment_application_id', $application->id)
                ->lockForUpdate()
                ->first();

            if ($interview === null) {
                throw ValidationException::withMessages([
                    'application' => ['Interview tidak ditemukan.'],
                ]);
            }

            if ($interview->interviewer_id !== $interviewer->id || $interview->status !== InterviewStatus::InProgress) {
                throw ValidationException::withMessages([
                    'booking' => ['Hanya interviewer yang mem-booking dapat membatalkan.'],
                ]);
            }

            $application->loadMissing('evaluation');
            if ($application->evaluation !== null) {
                throw ValidationException::withMessages([
                    'booking' => ['Tidak dapat membatalkan setelah penilaian disimpan.'],
                ]);
            }

            $interview->update([
                'interviewer_id' => null,
                'status' => InterviewStatus::Waiting,
                'booked_at' => null,
            ]);

            $this->activityLogger->log(
                'interview.booking_released',
                $interviewer,
                $application,
                ['interviewer_id' => $interviewer->id],
                [],
                'recruitment_interview',
                $interview->id,
                $request,
            );

            return $interview->fresh(['application', 'session']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationToPoolArray(RecruitmentApplication $application): array
    {
        return [
            'application_id' => $application->id,
            'full_name' => $application->full_name,
            'registration_number' => $application->registration_number,
            'nim' => $application->nim,
            'checked_in_at' => $application->attendance?->checked_in_at?->toIso8601String(),
            'session' => $application->interview?->session ? [
                'id' => $application->interview->session->id,
                'division' => $application->interview->session->division?->name,
            ] : null,
        ];
    }

    private function assertInterviewerAssignedToApplicationDivision(User $interviewer, RecruitmentApplication $application): void
    {
        $application->loadMissing('interview.session');
        $divisionId = $application->interview?->session?->recruitment_division_id
            ?? $application->primary_division_id;

        $allowed = RecruitmentInterviewerDivision::query()
            ->where('user_id', $interviewer->id)
            ->where('recruitment_division_id', $divisionId)
            ->exists();

        if (! $allowed) {
            throw ValidationException::withMessages([
                'booking' => ['Kamu tidak ditugaskan pada divisi applicant ini.'],
            ]);
        }
    }
}
