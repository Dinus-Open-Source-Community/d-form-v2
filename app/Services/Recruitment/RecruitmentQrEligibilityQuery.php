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

/** Single source for QR eligibility predicates and their supporting indexed EXISTS queries. */
final class RecruitmentQrEligibilityQuery
{
    /**
     *
     * Dasar pool QR: applicant periode ini, tahap interview, belum absen, tidak dibatalkan.
     *
     * @return Builder<RecruitmentApplication>
     *
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
     *
     * Saring query ke applicant yang tidak memiliki QR terkirim atau masih queued.
     *
     * @param  Builder<RecruitmentApplication>  $query
     * @return Builder<RecruitmentApplication>
     *
     */
    private function withoutInFlight(Builder $query): Builder
    {
        return $query->whereNotExists($this->inFlightEmailExists());
    }

    /**
     *
     * Exclude applicants with a queued QR while leaving sent and failed applicants eligible for explicit resend.
     *
     * @param  Builder<RecruitmentApplication>  $query
     * @return Builder<RecruitmentApplication>
     *
     */
    private function withoutQueued(Builder $query): Builder
    {
        return $query->whereNotExists($this->queuedEmailExists());
    }

    /**
     *
     * Saring query ke applicant yang SUDAH pernah menerima email QR interview.
     *
     * @param  Builder<RecruitmentApplication>  $query
     * @return Builder<RecruitmentApplication>
     *
     */
    private function sent(Builder $query): Builder
    {
        return $query->whereExists($this->sentEmailExists());
    }

    /** Return applicants eligible for a first QR send; callers add ordering when needed.
     * @return Builder<RecruitmentApplication>
     */
    public function eligible(RecruitmentPeriod $period): Builder
    {
        return $this->withoutInFlight($this->base($period));
    }

    /**
     *
     * Applicant di pool dasar yang tidak memiliki QR queued; sent dan failed tetap dapat dikirim ulang.
     *
     * @return Builder<RecruitmentApplication>
     *
     */
    public function resendable(RecruitmentPeriod $period): Builder
    {
        return $this->withoutQueued($this->base($period));
    }

    /**
     *
     * Applicant yang sudah menerima QR dan masih bisa dikirimi ulang.
     *
     * @return Builder<RecruitmentApplication>
     *
     */
    public function alreadySent(RecruitmentPeriod $period): Builder
    {
        return $this->sent($this->base($period));
    }

    /**
     *
     * Applicant dengan QR terkirim atau masih queued dan belum absen.
     *
     * @return Builder<RecruitmentApplication>
     *
     */
    public function inFlight(RecruitmentPeriod $period): Builder
    {
        return $this->inFlightQuery($this->base($period));
    }

    /** Keep queued QR separate from sent email in tab counts.
     * @return array{eligible:int,sent:int}
     */
    public function counts(RecruitmentPeriod $period): array
    {
        $sent = $this->countAlreadySent($period);
        $inFlight = $this->countInFlight($period);

        return [
            'eligible' => $this->base($period)->count() - $inFlight,
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

    /** Jumlah applicant yang QR-nya terkirim atau masih queued. */
    public function countInFlight(RecruitmentPeriod $period): int
    {
        return $this->inFlight($period)->count();
    }

    /**
     *
     * Apply the matching QR email EXISTS predicate to an application query.
     *
     * @param  Builder<RecruitmentApplication>  $query
     * @return Builder<RecruitmentApplication>
     *
     */
    private function inFlightQuery(Builder $query): Builder
    {
        return $query->whereExists($this->inFlightEmailExists());
    }

    /**
     *
     * Subquery EXISTS email QR interview berstatus sent.
     *
     * @return Closure(QueryBuilder): void
     *
     */
    private function sentEmailExists(): Closure
    {
        return $this->emailLogExistsWithStatuses([EmailLogStatus::Sent->value]);
    }

    /**
     *
     * Subquery EXISTS for queued QR interview emails.
     *
     * @return Closure(QueryBuilder): void
     *
     */
    private function queuedEmailExists(): Closure
    {
        return $this->emailLogExistsWithStatuses([EmailLogStatus::Queued->value]);
    }

    /**
     *
     * Subquery EXISTS for sent and queued QR interview emails.
     *
     * @return Closure(QueryBuilder): void
     *
     */
    private function inFlightEmailExists(): Closure
    {
        return $this->emailLogExistsWithStatuses([
            EmailLogStatus::Sent->value,
            EmailLogStatus::Queued->value,
        ]);
    }


    /**
     *
     * @param  non-empty-list<string>  $statuses
     * @return Closure(QueryBuilder): void
     *
     */
    private function emailLogExistsWithStatuses(array $statuses): Closure
    {
        return static function (QueryBuilder $query) use ($statuses): void {
            $query->selectRaw('1')
                ->from('email_logs')
                ->whereColumn('email_logs.recruitment_application_id', 'recruitment_applications.id')
                ->where('email_logs.notification_type', EmailNotificationType::RecruitmentInterviewScheduled->value);

            if (count($statuses) === 1) {
                $query->where('email_logs.status', $statuses[0]);

                return;
            }

            $query->whereIn('email_logs.status', $statuses);
        };
    }
}
