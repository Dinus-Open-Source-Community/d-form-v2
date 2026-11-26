<?php

namespace App\Services\Form;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Services\Du\DuFormCourseResolver;
use App\Services\Du\DuRedeemIssuanceService;
use App\Enums\RegistrationRole;
use App\Exceptions\FormAnswerReviewConflictException;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Registration\EventRegistrationCounter;
use App\Services\Registration\RegistrationCodeIssuer;
use App\Services\Registration\RegistrationGroupPeers;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class FormAnswerReviewService
{
    private const REGISTRATION_CODE_ATTEMPTS = 16;

    private const MAX_BULK_IDS = 100;

    public function __construct(
        private readonly RegistrationCodeIssuer $codeIssuer,
        private readonly EventRegistrationCounter $registrationCounter,
        private readonly RegistrationGroupPeers $groupPeers,
        private readonly DuRedeemIssuanceService $duIssuance,
        private readonly DuFormCourseResolver $duForms,
        private readonly FormReviewEmailOutboxIntents $outboxIntents,
    ) {
    }

    /**
     *
     * Review one submission while using the same parent-first, sorted-row lock order as bulk review.
     *
     * @return array{answer: FormAnswer, rejected_ids: Collection<int, string>, du_issuance?: \App\Services\Du\DuRedeemIssuanceResult}
     *
     */
    public function reviewOne(
        Event $event,
        Form $form,
        FormAnswer $formAnswer,
        FormAnswerReviewStatus $status,
        User $actor,
    ): array {
        if ($status === FormAnswerReviewStatus::Pending) {
            throw new HttpException(422, 'Invalid review status transition.');
        }

        $payload = DB::transaction(function () use ($event, $form, $formAnswer, $status, $actor): array {
            $lockedEvent = $this->lockEvent($event, $form);
            $lockIds = [$formAnswer->id];

            if ($status === FormAnswerReviewStatus::Rejected) {
                $lockIds = $this->groupPeers
                    ->peersNotYetRejected($form, $formAnswer)
                    ->pluck('id')
                    ->push($formAnswer->id)
                    ->map(static fn ($id): string => (string) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            }

            /** @var Collection<int, FormAnswer> $lockedAnswers */
            $lockedAnswers = FormAnswer::query()
                ->where('form_id', $form->id)
                ->whereIn('id', $lockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var FormAnswer|null $locked */
            $locked = $lockedAnswers->get((string) $formAnswer->id);
            if ($locked === null) {
                abort(404);
            }

            if ($locked->review_status !== FormAnswerReviewStatus::Pending) {
                throw new FormAnswerReviewConflictException($locked->review_status);
            }

            if ($status === FormAnswerReviewStatus::Accepted
                && $locked->registration_role === RegistrationRole::Member
                && $locked->member_confirmation_status !== MemberConfirmationStatus::Accepted) {
                throw new HttpException(422, __('This participant must confirm their registration before it can be accepted.'));
            }

            $rejectedIds = collect();
            $primaryAnswer = $locked;
            $releasedSlots = 0;

            if ($status === FormAnswerReviewStatus::Rejected) {
                foreach ($lockedAnswers as $candidate) {
                    if ($candidate->review_status === FormAnswerReviewStatus::Rejected) {
                        continue;
                    }

                    $before = $candidate->replicate();
                    $candidate->forceFill([
                        'review_status' => FormAnswerReviewStatus::Rejected,
                        'reviewed_at' => now(),
                        'reviewed_by' => (string) $actor->id,
                    ]);
                    $candidate->save();
                    $releasedSlots += $this->releasedSlotCount($before, $candidate);
                    $rejectedIds->push((string) $candidate->id);

                    if ((string) $candidate->id === (string) $formAnswer->id) {
                        $primaryAnswer = $candidate;
                    }
                }

                if ($releasedSlots > 0) {
                    $this->registrationCounter->releaseLocked($lockedEvent, $releasedSlots);
                }
            } else {
                $locked->forceFill([
                    'review_status' => $status,
                    'reviewed_at' => now(),
                    'reviewed_by' => (string) $actor->id,
                ]);
                $this->saveAcceptedWithUniqueRegistrationCode($locked);
                $primaryAnswer = $locked;
                $this->outboxIntents->forReview($locked, $status);
            }

            if ($status === FormAnswerReviewStatus::Rejected) {
                foreach ($rejectedIds as $rejectedId) {
                    $rejected = $lockedAnswers->get($rejectedId);
                    if ($rejected instanceof FormAnswer) {
                        $this->outboxIntents->forReview($rejected, $status);
                    }
                }
            }

            return [
                'answer' => $primaryAnswer->fresh() ?? $primaryAnswer,
                'rejected_ids' => $rejectedIds,
            ];
        });

        if ($status === FormAnswerReviewStatus::Accepted && $this->duForms->enabled($form)) {
            $issuance = $this->duIssuance->issueAccepted($payload['answer']);
            $payload['du_issuance'] = $issuance;
            $this->outboxIntents->duIssuedFor($payload['answer'], $issuance);
        }

        return $payload;
    }

    /**
     *
     * Atomically review explicitly selected single-mode submissions.
     *
     * @param  Collection<int, string>  $ids
     *
     */
    public function reviewMany(
        Event $event,
        Form $form,
        Collection $ids,
        FormAnswerReviewStatus $status,
        User $actor,
    ): BulkReviewResult {
        if ($status === FormAnswerReviewStatus::Pending) {
            throw new HttpException(422, 'Invalid review status transition.');
        }
        if ($this->duForms->enabled($form)) {
            throw new HttpException(422, 'DU-enabled submissions cannot use bulk review.');
        }

        $selectedIds = $this->normalizeSelectedIds($ids);

        return DB::transaction(function () use ($event, $form, $selectedIds, $status, $actor): BulkReviewResult {
            $lockedEvent = $this->lockEvent($event, $form);
            $answers = FormAnswer::query()
                ->where('form_id', $form->id)
                ->whereIn('id', $selectedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($answers->count() !== count($selectedIds)) {
                throw new HttpException(422, 'One or more selected submissions are not eligible for bulk review.');
            }

            foreach ($answers as $answer) {
                if ($answer->review_status !== FormAnswerReviewStatus::Pending) {
                    throw new ConflictHttpException('One or more selected submissions have already been reviewed.');
                }
            }

            if ($this->registrationMode($form) !== 'single' || $answers->contains(
                static fn (FormAnswer $answer): bool => $answer->registration_role !== null
                    || ($answer->group_token !== null && $answer->group_token !== '')
                    || $answer->member_confirmation_status !== null,
            )) {
                throw new HttpException(422, 'Only single-mode submissions without confirmation are eligible for bulk review.');
            }

            $outboxIds = collect();
            $releasedSlots = 0;
            $reviewedAt = now();

            foreach ($answers as $answer) {
                $before = $answer->replicate();
                $answer->forceFill([
                    'review_status' => $status,
                    'reviewed_at' => $reviewedAt,
                    'reviewed_by' => (string) $actor->id,
                ]);

                if ($status === FormAnswerReviewStatus::Accepted) {
                    $this->saveAcceptedWithUniqueRegistrationCode($answer);
                } else {
                    $answer->save();
                    $releasedSlots += $this->releasedSlotCount($before, $answer);
                }

                $outboxIds->push($this->outboxIntents->forReview($answer, $status));
            }

            if ($releasedSlots > 0) {
                $this->registrationCounter->releaseLocked($lockedEvent, $releasedSlots);
            }

            return new BulkReviewResult($answers->fresh(), $outboxIds);
        });
    }

    private function lockEvent(Event $event, Form $form): Event
    {
        /** @var Event|null $lockedEvent */
        $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();
        if ($lockedEvent === null) {
            abort(404);
        }

        if ((string) $form->event_id !== (string) $lockedEvent->id) {
            abort(404);
        }

        return $lockedEvent;
    }

    /**
     *
     * @param  Collection<int, string>  $ids
     * @return list<string>
     *
     */
    private function normalizeSelectedIds(Collection $ids): array
    {
        $selectedIds = $ids
            ->map(static fn ($id): string => is_string($id) || is_int($id) ? (string) $id : '')
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values();

        if ($selectedIds->isEmpty() || $selectedIds->count() > self::MAX_BULK_IDS || $selectedIds->count() !== $ids->count()) {
            throw new HttpException(422, 'The selected submission list is invalid.');
        }

        return $selectedIds->sort()->values()->all();
    }

    private function registrationMode(Form $form): ?string
    {
        $metadata = $form->metadata;
        if (! is_array($metadata) || ! is_string($metadata['registration_mode'] ?? null)) {
            return null;
        }

        return strtolower($metadata['registration_mode']);
    }

    private function releasedSlotCount(FormAnswer $before, FormAnswer $after): int
    {
        return $this->registrationCounter->occupiesQuotaSlot($before)
            && ! $this->registrationCounter->occupiesQuotaSlot($after) ? 1 : 0;
    }

    private function saveAcceptedWithUniqueRegistrationCode(FormAnswer $answer): void
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::REGISTRATION_CODE_ATTEMPTS; $attempt++) {
            $answer->registration_code = $this->codeIssuer->generate();

            try {
                $answer->save();

                return;
            } catch (QueryException $exception) {
                if (! $this->isUniqueRegistrationCodeViolation($exception)) {
                    throw $exception;
                }

                $lastException = $exception;
            }
        }

        throw new \RuntimeException('Could not assign a unique registration code.', 0, $lastException);
    }

    private function isUniqueRegistrationCodeViolation(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'unique') && str_contains($message, 'registration_code');
    }
}
