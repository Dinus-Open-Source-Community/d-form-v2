<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentEvaluation;
use App\Models\Recruitment\RecruitmentInterview;

final class InterviewerApplicationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(RecruitmentApplication $application): array
    {
        $application->loadMissing([
            'primaryDivision',
            'secondaryDivision',
            'document',
            'interview.session.division',
            'interview.interviewer:id,name',
            'attendance',
            'evaluation.evaluator:id,name',
            'finalDecision.finalDivision',
            'attendance',
        ]);

        return [
            'application' => $this->applicationBlock($application),
            'documents' => $this->documentsBlock($application),
            'interview' => $this->interviewBlock($application->interview),
            'attendance' => $this->attendanceBlock($application),
            'evaluation' => $this->evaluationBlock($application->evaluation),
            'final' => $this->finalBlock($application),
        ];
    }

    /**
     * Sajikan satu interview spesifik (primary atau secondary) beserta
     * evaluasinya sendiri.
     *
     * @return array<string, mixed>
     */
    public function presentForInterview(RecruitmentInterview $interview): array
    {
        $interview->loadMissing([
            'application.primaryDivision',
            'application.secondaryDivision',
            'application.document',
            'application.attendance',
            'application.finalDecision.finalDivision',
            'session.division',
            'interviewer:id,name',
            'evaluation.evaluator:id,name',
        ]);

        $application = $interview->application;

        abort_if($application === null, 404);

        return [
            'application' => $this->applicationBlock($application),
            'documents' => $this->documentsBlock($application),
            'interview' => $this->interviewBlock($interview),
            'attendance' => $this->attendanceBlock($application),
            'evaluation' => $this->evaluationBlock($interview->evaluation),
            'final' => $this->finalBlock($application),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationBlock(RecruitmentApplication $application): array
    {
        return [
            'id' => $application->id,
            'registration_number' => $application->registration_number,
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'primary_division' => $application->primaryDivision?->name,
            'secondary_division' => $application->secondaryDivision?->name,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentsBlock(RecruitmentApplication $application): array
    {
        return [
            'has_cv' => filled($application->document?->cv_path),
            'has_portfolio' => filled($application->document?->portfolio_path)
                || filled($application->document?->portfolio_url),
            'portfolio_is_url' => filled($application->document?->portfolio_url),
            'portfolio_url' => $application->document?->portfolio_url,
            'has_instagram_follow' => filled($application->document?->instagram_follow_path),
            'twibbon_url' => $application->document?->twibbon_url,
            'cv_download_url' => filled($application->document?->cv_path)
                ? route('dashboard.recruitment.applications.documents.download', [
                    'application' => $application->id,
                    'type' => 'cv',
                ])
                : null,
            'cv_original_name' => $application->document?->cv_original_name,
            'cv_size_bytes' => $application->document?->cv_size_bytes,
            'cv_preview_url' => filled($application->document?->cv_path)
                ? route('dashboard.recruitment.applications.documents.download', [
                    'application' => $application->id,
                    'type' => 'cv',
                    'preview' => 1,
                ])
                : null,
            'portfolio_download_url' => filled($application->document?->portfolio_path)
                ? route('dashboard.recruitment.applications.documents.download', [
                    'application' => $application->id,
                    'type' => 'portfolio',
                ])
                : null,
            'portfolio_original_name' => $application->document?->portfolio_original_name,
            'portfolio_size_bytes' => $application->document?->portfolio_size_bytes,
            'portfolio_preview_url' => filled($application->document?->portfolio_path)
                ? route('dashboard.recruitment.applications.documents.download', [
                    'application' => $application->id,
                    'type' => 'portfolio',
                    'preview' => 1,
                ])
                : null,
            'instagram_follow_download_url' => filled($application->document?->instagram_follow_path)
                ? route('dashboard.recruitment.applications.documents.download', [
                    'application' => $application->id,
                    'type' => 'instagram_follow',
                ])
                : null,
            'instagram_follow_preview_url' => filled($application->document?->instagram_follow_path)
                ? route('dashboard.recruitment.applications.documents.download', [
                    'application' => $application->id,
                    'type' => 'instagram_follow',
                    'preview' => 1,
                ])
                : null,
            'instagram_follow_original_name' => $application->document?->instagram_follow_original_name,
            'instagram_follow_size_bytes' => $application->document?->instagram_follow_size_bytes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function interviewBlock(?RecruitmentInterview $interview): ?array
    {
        if ($interview === null) {
            return null;
        }

        return [
            'id' => $interview->id,
            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            'location' => $interview->location,
            'room' => $interview->room,
            'status' => $interview->status->value,
            'status_label' => $interview->status->label(),
            'booked_at' => $interview->booked_at?->toIso8601String(),
            'interviewer' => $interview->interviewer ? [
                'id' => $interview->interviewer->id,
                'name' => $interview->interviewer->name,
            ] : null,
            'session' => $interview->session ? [
                'id' => $interview->session->id,
                'session_date' => $interview->session->session_date?->toDateString(),
                'division' => $interview->session->division?->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceBlock(RecruitmentApplication $application): array
    {
        return [
            'has_attendance' => $application->attendance !== null,
            'checked_in_at' => $application->attendance?->checked_in_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluationBlock(?RecruitmentEvaluation $evaluation): array
    {
        if ($evaluation === null) {
            return [
                'can_edit' => true,
            ];
        }

        return [
            'speaking_score' => $evaluation->speaking_score,
            'technical_score' => $evaluation->technical_score,
            'attitude_score' => $evaluation->attitude_score,
            'recommendation' => $evaluation->recommendation->value,
            'recommendation_label' => $evaluation->recommendation->label(),
            'notes' => $evaluation->notes,
            'evaluated_at' => $evaluation->evaluated_at?->toIso8601String(),
            'evaluated_by' => $evaluation->evaluator?->name,
            'locked_at' => $evaluation->locked_at?->toIso8601String(),
            'is_locked' => $evaluation->isLocked(),
            'can_edit' => ! $evaluation->isLocked(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function finalBlock(RecruitmentApplication $application): ?array
    {
        $finalDecision = $application->finalDecision;

        if ($finalDecision !== null) {
            return [
                'membership_type' => $finalDecision->membership_type,
                'final_division' => $finalDecision->finalDivision?->name,
                'public_message' => $finalDecision->public_message,
            ];
        }

        if ($application->result->value === 'pending') {
            return null;
        }

        return [
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
        ];
    }
}
