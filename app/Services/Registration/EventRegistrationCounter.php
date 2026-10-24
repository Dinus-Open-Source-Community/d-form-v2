<?php

namespace App\Services\Registration;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Exceptions\QuotaExceededException;
use App\Models\Event;
use App\Models\FormAnswer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps events.registered_count aligned with submissions that occupy quota slots.
 *
 * All reserve/release paths lock the parent event row (SELECT … FOR UPDATE) so
 * concurrent submissions and rejections cannot over- or under-count capacity.
 */
final class EventRegistrationCounter
{
    public function occupiesQuotaSlot(FormAnswer $submission): bool
    {
        if ($submission->review_status === FormAnswerReviewStatus::Rejected) {
            return false;
        }

        if ($submission->registration_role === RegistrationRole::Member
            && in_array($submission->member_confirmation_status, [
                MemberConfirmationStatus::Rejected,
                MemberConfirmationStatus::Expired,
            ], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<FormAnswer>  $query
     */
    public function applyOccupyingQuotaSlotScope(Builder $query): Builder
    {
        return $query->where(function (Builder $w): void {
            $w->whereNull('review_status')
                ->orWhere('review_status', '!=', FormAnswerReviewStatus::Rejected->value);
        })->where(function (Builder $w): void {
            $w->whereNull('registration_role')
                ->orWhere('registration_role', '!=', RegistrationRole::Member->value)
                ->orWhereNull('member_confirmation_status')
                ->orWhereNotIn('member_confirmation_status', [
                    MemberConfirmationStatus::Rejected->value,
                    MemberConfirmationStatus::Expired->value,
                ]);
        });
    }

    public function assertCanReserve(Event $event, int $slots, bool $adminExempt): void
    {
        if ($adminExempt || $event->quota === null || $event->quota <= 0) {
            return;
        }

        if ((int) $event->registered_count + $slots > (int) $event->quota) {
            throw new QuotaExceededException();
        }
    }

    public function reserveLocked(Event $event, int $slots): void
    {
        if ($slots <= 0) {
            return;
        }

        $event->increment('registered_count', $slots);
    }

    public function releaseLocked(Event $event, int $slots): void
    {
        if ($slots <= 0) {
            return;
        }

        $updated = Event::query()
            ->whereKey($event->id)
            ->where('registered_count', '>=', $slots)
            ->decrement('registered_count', $slots);

        if ($updated === 0) {
            Event::query()
                ->whereKey($event->id)
                ->update(['registered_count' => 0]);
        }

        // Query builder tidak memicu EventObserver; invalidasi manual.
        \App\Services\Event\EventCache::bump(\App\Services\Event\EventCache::BUSTER_LIST);
        \App\Services\Event\EventCache::bump(\App\Services\Event\EventCache::BUSTER_REGISTRANTS);
    }

    /**
     * Release one slot when a submission stops occupying quota (reject / decline / expiry).
     */
    public function releaseIfStoppedOccupying(FormAnswer $before, FormAnswer $after): void
    {
        if ($this->occupiesQuotaSlot($before) && ! $this->occupiesQuotaSlot($after)) {
            $this->releaseForFormAnswer($after, 1);
        }
    }

    /**
     * Release slots when a counted row is deleted (e.g. declined invitation cleanup).
     */
    public function releaseOnDeletion(FormAnswer $submission): void
    {
        if (! $this->occupiesQuotaSlot($submission)) {
            return;
        }

        $this->releaseForFormAnswer($submission, 1);
    }

    public function countOccupyingSlotsForEvent(Event $event): int
    {
        return (int) FormAnswer::query()
            ->join('forms', 'form_answers.form_id', '=', 'forms.id')
            ->whereNull('forms.deleted_at')
            ->where('forms.event_id', $event->id)
            ->where(fn (Builder $q) => $this->applyOccupyingQuotaSlotScope($q))
            ->count('form_answers.id');
    }

    /**
     * Reconcile denormalized counter with live submission rows (admin / repair).
     */
    public function reconcile(Event $event): int
    {
        return (int) DB::transaction(function () use ($event): int {
            $locked = Event::query()->lockForUpdate()->find($event->id);

            if ($locked === null) {
                return 0;
            }

            $actual = $this->countOccupyingSlotsForEvent($locked);
            $locked->forceFill(['registered_count' => $actual])->save();

            return $actual;
        });
    }

    private function releaseForFormAnswer(FormAnswer $submission, int $slots): void
    {
        $form = $submission->relationLoaded('form')
            ? $submission->form
            : $submission->form()->first();

        if ($form === null) {
            return;
        }

        DB::transaction(function () use ($form, $slots): void {
            $locked = Event::query()->lockForUpdate()->find($form->event_id);

            if ($locked === null) {
                return;
            }

            $this->releaseLocked($locked, $slots);
        });
    }
}
