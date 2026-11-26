<?php

namespace App\Services\Form;

use App\Enums\DuRedeemStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Jobs\DispatchFormReviewEmailOutboxJob;
use App\Models\DuRedeemIssuance;
use App\Models\FormAnswer;
use App\Models\FormReviewEmailOutbox;
use App\Services\Du\DuRedeemIssuanceResult;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Single owner of form review email outbox intent creation and its after-commit dispatch. */
final class FormReviewEmailOutboxIntents
{
    public function forReview(FormAnswer $answer, FormAnswerReviewStatus $status): string
    {
        $action = $status === FormAnswerReviewStatus::Accepted
            ? FormReviewEmailOutbox::ACTION_ACCEPTED
            : FormReviewEmailOutbox::ACTION_REJECTED;

        return $this->createForAction($answer, $action, 'form-review-'.$answer->id.'-'.$action);
    }

    public function duIssued(FormAnswer $answer): string
    {
        return $this->createForAction(
            $answer,
            FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'form-review-'.$answer->id.'-du-issued',
        );
    }

    /** Queues the DU-issued email only for a completed issuance; a retry re-creates a lost intent from the stored code. */
    public function duIssuedFor(FormAnswer $answer, DuRedeemIssuanceResult $result, bool $reissue = false): ?string
    {
        if ($result->status !== DuRedeemStatus::Issued || $result->code === null || $result->code === '') {
            return null;
        }

        if (! $reissue) {
            return $this->duIssued($answer);
        }

        $attemptCount = DuRedeemIssuance::query()
            ->where('form_answer_id', $answer->id)
            ->value('attempt_count');

        return $this->createForAction(
            $answer,
            FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'form-review-'.$answer->id.'-du-issued-r'.$attemptCount,
        );
    }

    /** Queues a fresh delivery intent for the stored code; a unique key keeps it distinct from the original send. */
    public function duResend(FormAnswer $answer): ?string
    {
        $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->first();

        if (! $issuance instanceof DuRedeemIssuance
            || $issuance->status !== DuRedeemStatus::Issued
            || ! is_string($issuance->redeem_code)
            || trim($issuance->redeem_code) === '') {
            return null;
        }

        return $this->createForAction(
            $answer,
            FormReviewEmailOutbox::ACTION_DU_ISSUED,
            'du-issued-resend-'.Str::uuid()->toString(),
        );
    }

    /** Existing delivery keys are reused so a retried issue never duplicates the row or the email. */
    private function createForAction(FormAnswer $answer, string $action, string $deliveryKey): string
    {
        $outbox = FormReviewEmailOutbox::query()->firstOrCreate(
            ['delivery_key' => $deliveryKey],
            [
                'form_answer_id' => $answer->id,
                'action' => $action,
                'status' => FormReviewEmailOutbox::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );

        if (! $outbox->wasRecentlyCreated) {
            return (string) $outbox->id;
        }

        $originalAttempts = (int) $outbox->attempts;

        DB::afterCommit(function () use ($outbox, $originalAttempts): void {
            try {
                app(BusDispatcher::class)->dispatch(new DispatchFormReviewEmailOutboxJob((string) $outbox->id));
            } catch (\Throwable $exception) {
                FormReviewEmailOutbox::query()
                    ->whereKey($outbox->id)
                    ->where('status', FormReviewEmailOutbox::STATUS_PENDING)
                    ->where('attempts', $originalAttempts)
                    ->update([
                        'status' => FormReviewEmailOutbox::STATUS_PENDING,
                        'available_at' => now(),
                        'last_error' => 'Outbox dispatch failed: '.$exception::class,
                        'updated_at' => now(),
                    ]);

                Log::error('[FormReviewEmailOutboxIntents] Outbox dispatch failed after commit.', [
                    'outbox_id' => $outbox->id,
                    'delivery_key' => $outbox->delivery_key,
                    'exception_class' => $exception::class,
                ]);
            }
        });

        return (string) $outbox->id;
    }
}
