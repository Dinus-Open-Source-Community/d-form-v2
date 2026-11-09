<?php

namespace App\Services\Recruitment;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use App\Enums\Recruitment\ApplicationStage;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentPeriod;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Satu sumber kebenaran untuk predikat eligibility QR interview OpRec.
 *
 * Sebelumnya predikat ini disalin di controller periode dan bulk service, jadi
 * hitungan di UI bisa berbeda dari penerima yang benar-benar dikirimi. Semua
 * pemakai sekarang memakai kelas ini.
 *
 * Index pendukung (dibuat migrasi 2026_11_09_000002):
 * - `email_logs_rec_app_type_status_idx` untuk subquery EXISTS/NOT EXISTS
 * - `rec_apps_period_stage_cancelled_idx` untuk filter dasar applicant
 */
final class RecruitmentQrEligibilityQuery
{
    /**
     * Dasar pool QR: applicant periode ini, tahap interview, belum absen, tidak dibatalkan.
     *
     * @return Builder<RecruitmentApplication>
     */
    public function base(RecruitmentPeriod $period): Builder
    {
        return RecruitmentApplication::query()
            ->where('recruitment_period_id', $period->id)
            ->whereNull('cancelled_at')
            ->where('stage', ApplicationStage::Interview->value)
            ->whereDoesntHave('attendance');
    }

    /**
     * Saring query ke applicant yang BELUM pernah menerima email QR interview.
     *
     * @param  Builder<RecruitmentApplication>  $query
     * @return Builder<RecruitmentApplication>
     */
    private function notSent(Builder $query): Builder
    {
        return $query->whereNotExists($this->sentEmailExists());
    }

    /**
     * Saring query ke applicant yang SUDAH pernah menerima email QR interview.
     *
     * @param  Builder<RecruitmentApplication>  $query
     * @return Builder<RecruitmentApplication>
     */
    private function sent(Builder $query): Builder
    {
        return $query->whereExists($this->sentEmailExists());
    }

    /**
     * Applicant yang perlu dikirimi QR. Urutan tidak ditentukan di sini; pemanggil
     * menambahkan `orderBy('submitted_at')` sendiri (bulk service butuh urutan,
     * count tidak).
     *
     * @return Builder<RecruitmentApplication>
     */
    public function eligible(RecruitmentPeriod $period): Builder
    {
        return $this->notSent($this->base($period));
    }

    /**
     * Applicant yang sudah menerima QR dan masih bisa dikirimi ulang.
     *
     * @return Builder<RecruitmentApplication>
     */
    public function alreadySent(RecruitmentPeriod $period): Builder
    {
        return $this->sent($this->base($period));
    }

    /**
     * Hitungan tab QR dalam satu pass setengah biaya.
     *
     * `sent` memakai probe EXISTS; `eligible` = base - sent karena base
     * (interview, belum absen, tidak dibatalkan) tepat terbagi menjadi "pernah
     * terkirim" dan "belum terkirim". Menghindari NOT EXISTS kedua yang jauh
     * lebih mahal (~1,3 s vs ~51 ms base count pada 1 juta baris).
     *
     * @return array{eligible:int,sent:int}
     */
    public function counts(RecruitmentPeriod $period): array
    {
        $sent = $this->countAlreadySent($period);

        return [
            'eligible' => $this->base($period)->count() - $sent,
            'sent' => $sent,
        ];
    }

    /** Jumlah applicant yang perlu dikirimi QR (alias dari `counts()['eligible']`). */
    public function countEligible(RecruitmentPeriod $period): int
    {
        return $this->counts($period)['eligible'];
    }

    /** Jumlah applicant yang sudah menerima QR (pool kirim ulang). */
    public function countAlreadySent(RecruitmentPeriod $period): int
    {
        return $this->alreadySent($period)->count();
    }

    /**
     * Subquery EXISTS email QR interview berstatus sent.
     *
     * @return Closure(QueryBuilder): void
     */
    private function sentEmailExists(): Closure
    {
        return static function (QueryBuilder $query): void {
            $query->selectRaw('1')
                ->from('email_logs')
                ->whereColumn('email_logs.recruitment_application_id', 'recruitment_applications.id')
                ->where('email_logs.notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value)
                ->where('email_logs.status', EmailLogStatus::Sent->value);
        };
    }
}
