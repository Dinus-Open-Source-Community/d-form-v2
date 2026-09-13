<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;

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
            'queueEntry',
            'evaluation.evaluator:id,name',
            'finalDecision.finalDivision',
        ]);

        $evaluation = $application->evaluation;
        $finalDecision = $application->finalDecision;

        return [
            'application' => [
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
            ],
            'documents' => [
                'has_cv' => filled($application->document?->cv_path),
                'has_portfolio' => filled($application->document?->portfolio_path)
                    || filled($application->document?->portfolio_url),
                'portfolio_is_url' => filled($application->document?->portfolio_url),
                'portfolio_url' => $application->document?->portfolio_url,
                'cv_download_url' => filled($application->document?->cv_path)
                    ? route('dashboard.recruitment.applications.documents.download', [
                        'application' => $application->id,
                        'type' => 'cv',
                    ])
                    : null,
                'portfolio_download_url' => filled($application->document?->portfolio_path)
                    ? route('dashboard.recruitment.applications.documents.download', [
                        'application' => $application->id,
                        'type' => 'portfolio',
                    ])
                    : null,
            ],
            'interview' => $application->interview ? [
                'id' => $application->interview->id,
                'scheduled_at' => $application->interview->scheduled_at?->toIso8601String(),
                'location' => $application->interview->location,
                'room' => $application->interview->room,
                'status' => $application->interview->status->value,
                'status_label' => $application->interview->status->label(),
                'session' => $application->interview->session ? [
                    'id' => $application->interview->session->id,
                    'session_date' => $application->interview->session->session_date?->toDateString(),
                    'division' => $application->interview->session->division?->name,
                ] : null,
            ] : null,
            'queue' => $application->queueEntry ? [
                'queue_number' => $application->queueEntry->queue_number,
                'status' => $application->queueEntry->status->value,
                'status_label' => $application->queueEntry->status->label(),
            ] : null,
            'evaluation' => $evaluation ? [
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
            ] : [
                'can_edit' => true,
            ],
            'final' => $finalDecision ? [
                'membership_type' => $finalDecision->membership_type,
                'final_division' => $finalDecision->finalDivision?->name,
                'public_message' => $finalDecision->public_message,
            ] : ($application->result->value !== 'pending' ? [
                'result' => $application->result->value,
                'result_label' => $application->result->label(),
            ] : null),
        ];
    }
}
