<?php

namespace Database\Seeders;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Enums\Recruitment\EvaluationRecommendation;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoInterviewResultsSeeder extends Seeder
{
    public function run(): void
    {
        $apps = RecruitmentApplication::query()
            ->orderBy('registration_number')
            ->take(12)
            ->with(['primaryInterview.session', 'primaryDivision', 'secondaryDivision'])
            ->get();

        if ($apps->count() < 12) {
            $this->command->error('Butuh minimal 12 aplikasi, ketemu '.$apps->count());

            return;
        }

        // 01-06 recommended primary, 07-12 not recommended + secondary
        $primaryRecommended = $apps->take(6);
        $primaryNotRecommended = $apps->skip(6)->take(6);

        foreach ($primaryRecommended as $i => $app) {
            $this->completePrimary($app, true, 85 + ($i % 3), 84 + ($i % 4), 88 + ($i % 3));
        }

        foreach ($primaryNotRecommended as $i => $app) {
            $this->completePrimary($app, false, 58 + ($i % 5), 52 + ($i % 6), 60 + ($i % 5));
        }

        // Secondary: 07-09 recommended, 10-12 not recommended
        $secondaryRecommended = $primaryNotRecommended->take(3);
        $secondaryNotRecommended = $primaryNotRecommended->skip(3)->take(3);

        foreach ($secondaryRecommended as $i => $app) {
            $this->completeSecondary($app->fresh(['secondaryDivision']), true, 82 + ($i % 3), 80 + ($i % 4), 85 + ($i % 3));
        }

        foreach ($secondaryNotRecommended as $i => $app) {
            $this->completeSecondary($app->fresh(['secondaryDivision']), false, 55 + ($i % 4), 52 + ($i % 4), 60 + ($i % 3));
        }

        $this->command->info('DemoInterviewResultsSeeder done: 6 primary recommended, 6 primary not_recommended + secondary (3 rec, 3 not).');
    }

    private function completePrimary(RecruitmentApplication $app, bool $recommended, int $speaking, int $technical, int $attitude): void
    {
        $interview = $app->primaryInterview;

        if ($interview === null) {
            $this->command->warn("Skip {$app->registration_number}: no primary interview");

            return;
        }

        $interviewerId = $interview->interviewer_id
            ?? $this->interviewerForDivision($app->primary_division_id)?->id;

        // Attendance (syarat eligible secondary + realisme absensi)
        RecruitmentAttendance::query()->firstOrCreate(
            ['recruitment_application_id' => $app->id],
            [
                'recruitment_interview_session_id' => $interview->recruitment_interview_session_id,
                'method' => AttendanceMethod::Qr,
                'checked_in_at' => Carbon::parse($interview->scheduled_at)->subMinutes(30),
                'checked_in_by' => $interviewerId,
            ]
        );

        $interview->update([
            'interviewer_id' => $interviewerId,
            'booked_at' => $interview->booked_at ?? Carbon::parse($interview->scheduled_at)->subHour(),
            'status' => InterviewStatus::Completed,
        ]);

        $evaluatorId = $interviewerId ?? User::query()->first()?->id;

        $notes = $recommended
            ? "Layak direkomendasikan ke divisi primary {$app->primaryDivision?->name}. Komunikasi jelas, teknis memenuhi standar, attitude baik."
            : "Tidak direkomendasikan di primary {$app->primaryDivision?->name}. Diarahkan ikut interview secondary di {$app->secondaryDivision?->name}.";

        RecruitmentEvaluation::query()->updateOrCreate(
            ['recruitment_interview_id' => $interview->id],
            [
                'recruitment_application_id' => $app->id,
                'speaking_score' => $speaking,
                'technical_score' => $technical,
                'attitude_score' => $attitude,
                'recommendation' => $recommended ? EvaluationRecommendation::Recommended : EvaluationRecommendation::NotRecommended,
                'notes' => $notes,
                'save_count' => 3,
                'evaluated_by' => $evaluatorId,
                'evaluated_at' => now()->subHours(2),
                'locked_at' => now()->subHours(2),
            ]
        );

        if ($app->stage === ApplicationStage::Interview) {
            $app->update(['stage' => ApplicationStage::FinalReview]);
        }
    }

    private function completeSecondary(RecruitmentApplication $app, bool $recommended, int $speaking, int $technical, int $attitude): void
    {
        $app->loadMissing(['secondaryDivision']);

        if ($app->secondary_division_id === null) {
            $this->command->warn("Skip secondary {$app->registration_number}: no secondary division");

            return;
        }

        $session = RecruitmentInterviewSession::query()
            ->where('recruitment_period_id', $app->recruitment_period_id)
            ->where('recruitment_division_id', $app->secondary_division_id)
            ->where('is_active', true)
            ->orderBy('session_date')
            ->first();

        if ($session === null) {
            $this->command->warn("Skip secondary {$app->registration_number}: no active session");

            return;
        }

        $interviewer = $this->interviewerForDivision($app->secondary_division_id);

        $scheduledAt = Carbon::parse(
            $session->session_date->format('Y-m-d').' '.substr((string) $session->starts_at, 0, 5),
            config('app.timezone')
        );

        $secondary = RecruitmentInterview::query()->updateOrCreate(
            [
                'recruitment_application_id' => $app->id,
                'interview_kind' => RecruitmentInterview::KIND_SECONDARY,
            ],
            [
                'recruitment_interview_session_id' => $session->id,
                'interviewer_id' => $interviewer?->id,
                'booked_at' => now()->subHour(),
                'scheduled_at' => $scheduledAt,
                'location' => (string) $session->location,
                'room' => (string) $session->room,
                'status' => InterviewStatus::Completed,
            ]
        );

        $notes = $recommended
            ? "Direkomendasikan di divisi secondary {$app->secondaryDivision?->name}. Performa membaik dibanding primary, layak dipertimbangkan."
            : "Tidak direkomendasikan juga di secondary {$app->secondaryDivision?->name}. Skor di bawah standar.";

        RecruitmentEvaluation::query()->updateOrCreate(
            ['recruitment_interview_id' => $secondary->id],
            [
                'recruitment_application_id' => $app->id,
                'speaking_score' => $speaking,
                'technical_score' => $technical,
                'attitude_score' => $attitude,
                'recommendation' => $recommended ? EvaluationRecommendation::Recommended : EvaluationRecommendation::NotRecommended,
                'notes' => $notes,
                'save_count' => 3,
                'evaluated_by' => $interviewer?->id ?? $secondary->interviewer_id ?? User::query()->first()?->id,
                'evaluated_at' => now()->subHour(),
                'locked_at' => now()->subHour(),
            ]
        );
    }

    private function interviewerForDivision(?string $divisionId): ?User
    {
        if ($divisionId === null) {
            return null;
        }

        $userId = RecruitmentInterviewerDivision::query()
            ->where('recruitment_division_id', $divisionId)
            ->value('user_id');

        return $userId ? User::query()->find($userId) : null;
    }
}
