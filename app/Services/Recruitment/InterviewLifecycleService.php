<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InterviewLifecycleService
{
    public function createWaitingInterview(
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
    ): RecruitmentInterview {
        $scheduledAt = $this->buildScheduledAt($session);

        return RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'interviewer_id' => null,
            'scheduled_at' => $scheduledAt,
            'location' => $session->location,
            'room' => $session->room,
            'status' => InterviewStatus::Waiting,
        ]);
    }

    public function syncOrCreateWaitingInterview(
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
    ): RecruitmentInterview {
        $application->loadMissing('primaryInterview');
        $interview = $application->primaryInterview;

        if ($interview === null) {
            return $this->createWaitingInterview($application, $session);
        }

        $scheduledAt = $this->buildScheduledAt($session);

        // Sinkronisasi hanya menyelaraskan sesi (jadwal/lokasi bisa berubah);
        // pemilik, booking, dan status yang ada dipertahankan agar scan ulang
        // tidak men-stranded interview (tanpa booking flow, reset = hilang
        // dari tab interviewer selamanya).
        $interview->update([
            'recruitment_interview_session_id' => $session->id,
            'scheduled_at' => $scheduledAt,
            'location' => $session->location,
            'room' => $session->room,
        ]);

        return $interview->fresh();
    }

    public function secondaryEligible(RecruitmentApplication $application): bool
    {
        if ($application->secondary_division_id === null) {
            return false;
        }

        if ($application->stage === ApplicationStage::Completed) {
            return false;
        }

        $application->loadMissing(['primaryInterview.evaluation', 'secondaryInterview']);

        $primaryEvaluation = $application->primaryInterview?->evaluation;

        if ($primaryEvaluation === null || $primaryEvaluation->save_count < 1) {
            return false;
        }

        return $application->secondaryInterview === null;
    }

    public function createSecondaryInterview(
        User $actor,
        RecruitmentApplication $application,
        RecruitmentInterviewSession $session,
    ): RecruitmentInterview {
        if (! $actor->can('recruitment.evaluations.submit')) {
            throw new AuthorizationException('Tidak berhak menilai interview.');
        }

        if (! $this->secondaryEligible($application)) {
            throw ValidationException::withMessages([
                'application_id' => ['Applicant tidak eligible untuk interview secondary.'],
            ]);
        }

        $inSecondaryDivision = $application->secondary_division_id !== null
            && RecruitmentInterviewerDivision::query()
                ->where('user_id', $actor->id)
                ->where('recruitment_division_id', $application->secondary_division_id)
                ->exists();

        if (! $inSecondaryDivision) {
            throw new AuthorizationException('Hanya interviewer divisi secondary yang boleh mengambil interview ini.');
        }

        if ($session->recruitment_period_id !== $application->recruitment_period_id
            || $session->recruitment_division_id !== $application->secondary_division_id
            || ! $session->is_active) {
            throw ValidationException::withMessages([
                'session_id' => ['Sesi tidak valid untuk divisi secondary applicant.'],
            ]);
        }

        try {
            return DB::transaction(fn (): RecruitmentInterview => RecruitmentInterview::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_session_id' => $session->id,
                'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
                'interviewer_id' => $actor->id,
                'booked_at' => now(),
                'scheduled_at' => $this->buildScheduledAt($session),
                'location' => $session->location,
                'room' => $session->room,
                'status' => InterviewStatus::InProgress,
            ]));
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages([
                    'application_id' => ['Interview secondary sudah ada untuk applicant ini.'],
                ]);
            }

            throw $e;
        }
    }

    public function markCompleted(RecruitmentInterview $interview): void
    {
        $interview->update(['status' => InterviewStatus::Completed]);

        $application = $interview->application;

        if ($application === null) {
            return;
        }

        $this->advanceApplicationToFinalReview($application);
    }

    public function advanceApplicationToFinalReview(RecruitmentApplication $application): void
    {
        if ($application->stage === ApplicationStage::Interview
            && $application->result === ApplicationResult::Pending) {
            $application->update(['stage' => ApplicationStage::FinalReview]);
        }
    }

    private function buildScheduledAt(RecruitmentInterviewSession $session): Carbon
    {
        $date = $session->session_date?->format('Y-m-d');
        $time = substr((string) $session->starts_at, 0, 8);

        return Carbon::parse($date.' '.$time, config('app.timezone'));
    }
}
