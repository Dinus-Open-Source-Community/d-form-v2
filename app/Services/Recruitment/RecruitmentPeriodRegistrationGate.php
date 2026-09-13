<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Support\Carbon;

final class RecruitmentPeriodRegistrationGate
{
    public function findOpenPeriod(): ?RecruitmentPeriod
    {
        return RecruitmentPeriod::query()
            ->where('status', RecruitmentPeriodStatus::Open)
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (RecruitmentPeriod $period): bool => $this->isAcceptingApplications($period));
    }

    public function isAcceptingApplications(RecruitmentPeriod $period): bool
    {
        if ($period->status !== RecruitmentPeriodStatus::Open) {
            return false;
        }

        $now = now();

        if ($period->registration_opens_at !== null && $now->lt($period->registration_opens_at)) {
            return false;
        }

        if ($period->registration_closes_at !== null && $now->gt($period->registration_closes_at)) {
            return false;
        }

        return true;
    }

    public function closedMessage(?RecruitmentPeriod $period): string
    {
        if ($period === null) {
            return 'Pendaftaran OpenRecruitment belum dibuka.';
        }

        if ($period->status !== RecruitmentPeriodStatus::Open) {
            return 'Periode pendaftaran saat ini tidak menerima lamaran baru.';
        }

        $now = now();

        if ($period->registration_opens_at !== null && $now->lt($period->registration_opens_at)) {
            return 'Pendaftaran akan dibuka pada '.$this->formatDateTime($period->registration_opens_at).'.';
        }

        if ($period->registration_closes_at !== null && $now->gt($period->registration_closes_at)) {
            return 'Pendaftaran ditutup pada '.$this->formatDateTime($period->registration_closes_at).'.';
        }

        return 'Pendaftaran saat ini tidak tersedia.';
    }

    private function formatDateTime(Carbon $value): string
    {
        return $value->timezone(config('app.timezone'))->translatedFormat('d M Y H:i');
    }
}
