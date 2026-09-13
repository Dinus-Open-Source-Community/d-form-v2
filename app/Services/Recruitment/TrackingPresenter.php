<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Models\Recruitment\RecruitmentApplication;

final class TrackingPresenter
{
    public function __construct(
        private readonly ApplicationEditGate $editGate,
        private readonly RecruitmentQrPngGenerator $qrGenerator,
        private readonly FeedbackService $feedbackService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(RecruitmentApplication $application): array
    {
        $application->loadMissing([
            'period',
            'primaryDivision',
            'secondaryDivision',
            'interview',
            'queueEntry',
            'attendance',
            'finalDecision.finalDivision',
            'correctionRequests',
            'feedback',
        ]);

        $latestCorrection = $application->correctionRequests
            ->sortByDesc('created_at')
            ->first();

        return [
            'application' => $this->presentApplication($application),
            'period' => [
                'name' => $application->period?->name,
            ],
            'timeline' => $this->presentTimeline($application),
            'interview' => $this->presentInterview($application),
            'attendance' => $this->presentAttendance($application),
            'attendance_qr_base64' => $this->presentAttendanceQrBase64($application),
            'queue' => $this->presentQueue($application),
            'final' => $this->presentFinal($application),
            'edit' => [
                'can_edit' => $this->editGate->canEdit($application),
                'can_request_correction' => $this->editGate->canRequestCorrection($application),
                'latest_correction' => $latestCorrection ? [
                    'id' => $latestCorrection->id,
                    'status' => $latestCorrection->status->value,
                    'status_label' => $latestCorrection->status->label(),
                    'request_message' => $latestCorrection->request_message,
                    'review_notes' => $latestCorrection->review_notes,
                ] : null,
            ],
            'feedback' => [
                'can_submit' => $this->feedbackService->canSubmit($application),
                'submitted' => $application->feedback !== null,
                'submitted_at' => $application->feedback?->submitted_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentApplication(RecruitmentApplication $application): array
    {
        return [
            'registration_number' => $application->registration_number,
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'phone' => $application->phone,
            'personal_email' => $application->personal_email,
            'student_email' => $application->student_email,
            'instagram_username' => $application->instagram_username,
            'primary_division' => $application->primaryDivision?->name,
            'secondary_division' => $application->secondaryDivision?->name,
            'stage' => $application->stage->value,
            'stage_label' => $application->stage->label(),
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
            'revision_required' => $application->revision_required,
            'is_verified' => $application->is_verified,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentTimeline(RecruitmentApplication $application): array
    {
        $currentStage = $application->stage;
        $currentIndex = array_search($currentStage, ApplicationStage::timelineOrder(), true);

        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $items = [];

        foreach (ApplicationStage::timelineOrder() as $index => $stage) {
            $status = match (true) {
                $index < $currentIndex => 'completed',
                $index === $currentIndex => 'current',
                default => 'upcoming',
            };

            if ($stage === ApplicationStage::Completed && $application->result->value !== 'pending') {
                $status = 'completed';
            }

            $items[] = [
                'key' => $stage->value,
                'label' => $stage->label(),
                'status' => $status,
                'note' => $stage === ApplicationStage::Screening && $application->revision_required
                    ? 'Perlu revisi data — tim akan menghubungi kamu.'
                    : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentInterview(RecruitmentApplication $application): ?array
    {
        $interview = $application->interview;

        if ($interview === null) {
            return null;
        }

        return [
            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            'location' => $interview->location,
            'room' => $interview->room,
            'status' => $interview->status instanceof \App\Enums\Recruitment\InterviewStatus
                ? $interview->status->value
                : (string) $interview->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentAttendance(RecruitmentApplication $application): ?array
    {
        $attendance = $application->attendance;

        if ($attendance === null) {
            return null;
        }

        return [
            'checked_in_at' => $attendance->checked_in_at?->toIso8601String(),
            'method' => $attendance->method instanceof \App\Enums\Recruitment\AttendanceMethod
                ? $attendance->method->value
                : (string) $attendance->method,
        ];
    }

    private function presentAttendanceQrBase64(RecruitmentApplication $application): ?string
    {
        if ($application->attendance !== null) {
            return null;
        }

        $interview = $application->interview;

        if ($interview === null) {
            return null;
        }

        $status = $interview->status instanceof InterviewStatus
            ? $interview->status
            : InterviewStatus::tryFrom((string) $interview->status);

        if ($status !== InterviewStatus::Scheduled) {
            return null;
        }

        try {
            return base64_encode($this->qrGenerator->pngForApplication($application->id));
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentQueue(RecruitmentApplication $application): ?array
    {
        $queue = $application->queueEntry;

        if ($queue === null) {
            return null;
        }

        return [
            'queue_number' => $queue->queue_number,
            'status' => $queue->status instanceof \App\Enums\Recruitment\QueueStatus
                ? $queue->status->value
                : (string) $queue->status,
            'status_label' => $queue->status instanceof \App\Enums\Recruitment\QueueStatus
                ? $queue->status->label()
                : (string) $queue->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentFinal(RecruitmentApplication $application): ?array
    {
        if ($application->stage !== ApplicationStage::Completed) {
            return null;
        }

        $decision = $application->finalDecision;

        if ($decision === null && $application->result->value === 'rejected') {
            return [
                'membership_type' => null,
                'final_division' => null,
                'public_message' => null,
                'result' => $application->result->value,
                'result_label' => $application->result->label(),
            ];
        }

        if ($decision === null) {
            return null;
        }

        return [
            'membership_type' => $decision->membership_type,
            'final_division' => $decision->finalDivision?->name,
            'public_message' => $decision->public_message,
            'result' => $application->result->value,
            'result_label' => $application->result->label(),
        ];
    }
}
