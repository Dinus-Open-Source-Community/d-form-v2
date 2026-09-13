<?php

namespace App\Console\Commands;

use App\Services\Recruitment\InterviewReminderService;
use Illuminate\Console\Command;

class SendRecruitmentInterviewRemindersCommand extends Command
{
    protected $signature = 'recruitment:send-interview-reminders';

    protected $description = 'Send H-1 and H-2 interview reminder emails for scheduled OpRec interviews';

    public function handle(InterviewReminderService $reminderService): int
    {
        $sent = $reminderService->sendDueReminders();

        $this->info("Queued {$sent} interview reminder email(s).");

        return self::SUCCESS;
    }
}
