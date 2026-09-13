<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentFeedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FeedbackService
{
    public function canSubmit(RecruitmentApplication $application): bool
    {
        if ($application->stage !== ApplicationStage::Completed) {
            return false;
        }

        return ! RecruitmentFeedback::query()
            ->where('recruitment_application_id', $application->id)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(RecruitmentApplication $application, array $data): RecruitmentFeedback
    {
        if (! $this->canSubmit($application)) {
            throw ValidationException::withMessages([
                'feedback' => 'Feedback hanya dapat dikirim setelah proses recruitment selesai dan belum pernah dikirim sebelumnya.',
            ]);
        }

        return DB::transaction(function () use ($application, $data): RecruitmentFeedback {
            return RecruitmentFeedback::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_period_id' => $application->recruitment_period_id,
                'rating_registration_ease' => (int) $data['rating_registration_ease'],
                'rating_info_clarity' => (int) $data['rating_info_clarity'],
                'rating_tracking_ease' => (int) $data['rating_tracking_ease'],
                'rating_interview_experience' => (int) $data['rating_interview_experience'],
                'rating_staff_service' => (int) $data['rating_staff_service'],
                'feedback_text' => $data['feedback_text'] ?? null,
                'submitted_at' => now(),
            ]);
        });
    }
}
