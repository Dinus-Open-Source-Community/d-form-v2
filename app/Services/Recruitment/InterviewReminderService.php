<?php

namespace App\Services\Recruitment;

/**
 * Reminder per-jadwal interview tidak dipakai pada model ruang tunggu.
 */
final class InterviewReminderService
{
    public function sendDueReminders(): int
    {
        return 0;
    }
}
