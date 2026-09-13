<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\InterviewStatus;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentInterview;
use Illuminate\Support\Carbon;

final class InterviewReminderService
{
    public function sendDueReminders(?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        $interviews = RecruitmentInterview::query()
            ->with('application')
            ->where('status', InterviewStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->get();

        foreach ($interviews as $interview) {
            if ($interview->application === null) {
                continue;
            }

            $scheduledAt = $interview->scheduled_at;

            if ($this->shouldSendH1Reminder($scheduledAt, $now) && $interview->reminder_h1_sent_at === null) {
                SendRecruitmentNotificationJob::dispatch(
                    $interview->application->id,
                    'interview_reminder_h1',
                    $interview->id,
                );

                $interview->update(['reminder_h1_sent_at' => $now]);
                $sent++;
            }

            if ($this->shouldSendH2Reminder($scheduledAt, $now) && $interview->reminder_h2_sent_at === null) {
                SendRecruitmentNotificationJob::dispatch(
                    $interview->application->id,
                    'interview_reminder_h2',
                    $interview->id,
                );

                $interview->update(['reminder_h2_sent_at' => $now]);
                $sent++;
            }
        }

        return $sent;
    }

    private function shouldSendH1Reminder(Carbon $scheduledAt, Carbon $now): bool
    {
        $hoursUntil = $now->diffInHours($scheduledAt, false);

        return $hoursUntil >= 23 && $hoursUntil <= 25;
    }

    private function shouldSendH2Reminder(Carbon $scheduledAt, Carbon $now): bool
    {
        $hoursUntil = $now->diffInHours($scheduledAt, false);

        return $hoursUntil >= 1.5 && $hoursUntil <= 2.5;
    }
}
