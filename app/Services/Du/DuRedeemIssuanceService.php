<?php

namespace App\Services\Du;

use App\Enums\DuRedeemStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\FormPurpose;
use App\Models\DuRedeemIssuance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DuRedeemIssuanceService
{
    /** Columns restored when a forced reissue fails; attempt_count is intentionally kept incremented. */
    private const REISSUE_SNAPSHOT_FIELDS = [
        'status', 'course_uid', 'redeem_code', 'failure_reason', 'du_uid', 'course_title',
        'target_email', 'max_uses', 'used_count', 'is_active', 'expires_at', 'du_created_at',
        'response_payload', 'issued_at',
    ];

    public function __construct(
        private readonly DuRedeemClient $client,
        private readonly FormAnswerRecipientResolver $recipients,
        private readonly DuCourseResolver $courseResolver,
        private readonly DuFormCourseResolver $duForms,
    ) {
    }

    public function issueAccepted(
        FormAnswer $answer,
        ?string $overrideCourseUid = null,
        bool $forceReissue = false,
    ): DuRedeemIssuanceResult {
        $form = $answer->form;
        if (! $form instanceof Form || ! $this->duForms->enabled($form)) {
            return new DuRedeemIssuanceResult(DuRedeemStatus::Failed, message: 'Submission is not DU-enabled.');
        }

        if ($answer->review_status !== FormAnswerReviewStatus::Accepted) {
            return new DuRedeemIssuanceResult(DuRedeemStatus::Failed, message: 'Submission is not accepted.');
        }

        $courseUid = $this->resolvedCourseUid($answer, $overrideCourseUid);
        if ($courseUid === null) {
            return $this->markFailed($answer, $this->missingCourseMessage($form));
        }

        $email = $this->recipients->email($answer);
        if ($email === null) {
            return $this->markFailed($answer, 'Participant email is unavailable.');
        }

        $claim = DB::transaction(function () use ($answer, $courseUid, $forceReissue): ?array {
            $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->lockForUpdate()->first();
            if ($issuance === null) {
                try {
                    $issuance = DuRedeemIssuance::query()->create(['form_answer_id' => $answer->id, 'course_uid' => $courseUid]);
                } catch (QueryException $exception) {
                    if (! $this->isDuplicateIssuanceConflict($exception)) {
                        throw $exception;
                    }
                    $issuance = DuRedeemIssuance::query()
                        ->where('form_answer_id', $answer->id)
                        ->lockForUpdate()
                        ->first();
                    if ($issuance === null) {
                        throw $exception;
                    }
                }
            }
            if ($issuance->status === DuRedeemStatus::Issued) {
                if (! $forceReissue) {
                    return ['issuance' => $issuance, 'snapshot' => null, 'reissue' => false];
                }
                $snapshot = $this->snapshot($issuance);
                $issuance->forceFill([
                    'status' => DuRedeemStatus::Unknown,
                    'attempted_at' => now(),
                    'attempt_count' => $issuance->attempt_count + 1,
                ])->save();
                return ['issuance' => $issuance, 'snapshot' => $snapshot, 'reissue' => true];
            }
            if ($issuance->status === DuRedeemStatus::Unknown) {
                return null;
            }
            $issuance->forceFill([
                'course_uid' => $courseUid, 'status' => DuRedeemStatus::Unknown,
                'attempted_at' => now(), 'attempt_count' => $issuance->attempt_count + 1,
            ])->save();
            return ['issuance' => $issuance, 'snapshot' => null, 'reissue' => false];
        });

        if ($claim === null) {
            return new DuRedeemIssuanceResult(
                DuRedeemStatus::Unknown,
                message: $forceReissue ? 'Status DU belum pasti; rekonsiliasi manual.' : 'Issuance is already claimed.',
            );
        }

        /** @var DuRedeemIssuance $claimRow */
        $claimRow = $claim['issuance'];

        // An existing issued row means a retry: replay the stored outcome so the caller
        // can restore a lost du_issued email intent.
        if ($claimRow->status === DuRedeemStatus::Issued) {
            return new DuRedeemIssuanceResult(DuRedeemStatus::Issued, $claimRow->redeem_code, 'Issuance is already claimed.');
        }

        $result = $this->client->issue($courseUid, $email);

        if ($claim['reissue']) {
            if ($result->status !== DuRedeemStatus::Issued) {
                $this->restoreSnapshot($claimRow, (array) $claim['snapshot']);

                return new DuRedeemIssuanceResult(
                    DuRedeemStatus::Issued,
                    $claimRow->redeem_code,
                    'Penerbitan ulang gagal; kode lama tetap berlaku.',
                );
            }

            $claimRow->forceFill([
                'status' => DuRedeemStatus::Issued,
                'redeem_code' => $result->code,
                'failure_reason' => null,
                'issued_at' => now(),
                ...$this->responseColumns($result->data),
            ])->save();

            return new DuRedeemIssuanceResult(DuRedeemStatus::Issued, $result->code, null, true);
        }

        $claimRow->forceFill([
            'status' => $result->status,
            'redeem_code' => $result->code,
            'failure_reason' => $result->status === DuRedeemStatus::Failed ? 'DU rejected request.' : null,
            'issued_at' => $result->status === DuRedeemStatus::Issued ? now() : null,
            ...$this->responseColumns($result->data),
        ])->save();

        return new DuRedeemIssuanceResult(
            $result->status,
            $result->code,
            $result->status === DuRedeemStatus::Issued ? null : 'Issuance was not completed.',
            $result->status === DuRedeemStatus::Issued,
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(DuRedeemIssuance $issuance): array
    {
        $snapshot = [];
        foreach (self::REISSUE_SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $issuance->getAttribute($field);
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $snapshot */
    private function restoreSnapshot(DuRedeemIssuance $issuance, array $snapshot): void
    {
        $issuance->forceFill($snapshot)->save();
    }

    /**
     * Response columns for a confirmed issuance; empty for any other outcome.
     * On success the response course_uid is authoritative; the locally resolved uid is only the request value.
     *
     * @return array<string, mixed>
     */
    private function responseColumns(?DuRedeemData $data): array
    {
        if ($data === null) {
            return [];
        }

        return [
            'course_uid' => $data->courseUid,
            'du_uid' => $data->uid,
            'course_title' => $data->courseTitle,
            'target_email' => $data->targetEmail,
            'max_uses' => $data->maxUses,
            'used_count' => $data->usedCount,
            'is_active' => $data->isActive,
            'expires_at' => $this->parseDate($data->expiresAt),
            'du_created_at' => $this->parseDate($data->createdAt),
            'response_payload' => $data->payload,
        ];
    }

    /** Parses a DU timestamp into the app timezone; a malformed value stays null instead of failing the issuance. */
    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Exception) {
            return null;
        }
    }

    private function isDuplicateIssuanceConflict(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'unique') && str_contains($message, 'form_answer_id');
    }

    /** Maps an explicit override or the submission's class value to the canonical cached uid. */
    private function resolvedCourseUid(FormAnswer $answer, ?string $overrideCourseUid): ?string
    {
        if ($overrideCourseUid !== null && $overrideCourseUid !== '') {
            return $this->courseResolver->uidForIssuance($overrideCourseUid);
        }

        return $this->duForms->courseUidFor($answer);
    }

    private function missingCourseMessage(Form $form): string
    {
        return $form->purpose() === FormPurpose::DKelas
            ? 'Kelas DU tidak ditemukan. Jalankan sync kelas lalu coba lagi.'
            : 'Kelas belum terhubung ke course DU.';
    }

    private function markFailed(FormAnswer $answer, string $message): DuRedeemIssuanceResult
    {
        $issuance = DuRedeemIssuance::query()->where('form_answer_id', $answer->id)->first();
        if ($issuance !== null && in_array($issuance->status, [DuRedeemStatus::Issued, DuRedeemStatus::Unknown], true)) {
            return new DuRedeemIssuanceResult($issuance->status, $issuance->redeem_code, 'Issuance is already claimed.');
        }
        DuRedeemIssuance::query()->updateOrCreate(
            ['form_answer_id' => $answer->id],
            ['status' => DuRedeemStatus::Failed, 'failure_reason' => $message],
        );
        return new DuRedeemIssuanceResult(DuRedeemStatus::Failed, message: $message);
    }
}
