<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ScreeningDecision;
use App\Jobs\Recruitment\SendRecruitmentNotificationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RecruitmentGroupLinkService
{
    public function __construct(
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /** Kirim link grup WA ke applicant lolos screening satu periode (rolling + stagger). */
    public function send(User $actor, RecruitmentPeriod $period): array
    {
        $whatsappUrl = trim((string) ($period->whatsapp_group_url ?? ''));

        if ($whatsappUrl === '') {
            throw ValidationException::withMessages([
                'whatsapp_group_url' => ['Periode ini belum punya link grup WA. Isi dulu di Settings periode.'],
            ]);
        }

        $recipients = RecruitmentApplication::query()
            ->where('recruitment_period_id', $period->id)
            ->whereNull('cancelled_at')
            ->where('result', '!=', ApplicationResult::Rejected->value)
            ->whereHas('screenings', fn ($query) => $query->where('decision', ScreeningDecision::Pass->value))
            ->orderBy('submitted_at')
            ->get();

        $delaySeconds = (int) config('registration.email_send_delay_seconds', 7);

        foreach ($recipients as $index => $application) {
            SendRecruitmentNotificationJob::dispatch($application->id, 'group_link', null, null, null, $whatsappUrl)
                ->delay(now()->addSeconds($index * $delaySeconds));

            $this->activityLogger->log(
                action: 'email.group_link',
                actor: $actor,
                application: $application,
                newValues: [
                    'template' => 'group_link',
                    'recipient_email' => (string) $application->personal_email,
                ],
            );
        }

        return ['dispatched' => $recipients->count()];
    }
}
