<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewFormAnswerRequest;
use App\Jobs\SendRegistrationAcceptedJob;
use App\Jobs\SendRegistrationRejectedJob;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Registration\EventRegistrationCounter;
use App\Services\Registration\RegistrationCodeIssuer;
use App\Services\Registration\RegistrationGroupPeers;
use App\Enums\RegistrationRole;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormAnswerReviewController extends Controller
{
    private const REGISTRATION_CODE_ATTEMPTS = 16;

    public function __invoke(
        ReviewFormAnswerRequest $request,
        Event $event,
        Form $form,
        FormAnswer $formAnswer,
        RegistrationCodeIssuer $codeIssuer,
        EventRegistrationCounter $registrationCounter,
        RegistrationGroupPeers $groupPeers,
    ): JsonResponse {
        $data = $request->validated();

        if ($formAnswer->review_status !== FormAnswerReviewStatus::Pending) {
            return response()->json([
                'message' => 'Submission has already been reviewed.',
                'current_status' => $formAnswer->review_status?->value,
            ], 409);
        }

        $newStatus = FormAnswerReviewStatus::from($data['review_status']);
        if ($newStatus === FormAnswerReviewStatus::Pending) {
            return response()->json([
                'message' => 'Invalid review status transition.',
            ], 422);
        }

        // Team / bundle members must accept the invitation before admin can accept their submission.
        if ($newStatus === FormAnswerReviewStatus::Accepted
            && $formAnswer->registration_role === RegistrationRole::Member
            && $formAnswer->member_confirmation_status !== MemberConfirmationStatus::Accepted) {
            return response()->json([
                'message' => __('This participant must confirm their registration before it can be accepted.'),
            ], 422);
        }

        $payload = DB::transaction(function () use (
            $request,
            $form,
            $formAnswer,
            $newStatus,
            $codeIssuer,
            $registrationCounter,
            $groupPeers,
        ): array {
            /** @var FormAnswer|null $locked */
            $locked = FormAnswer::query()
                ->whereKey($formAnswer->id)
                ->where('form_id', $form->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                abort(404);
            }

            if ($locked->review_status !== FormAnswerReviewStatus::Pending) {
                return [
                    'kind' => 'conflict',
                    'current_status' => $locked->review_status->value,
                ];
            }

            if ($newStatus === FormAnswerReviewStatus::Rejected) {
                return $this->rejectWithCascade(
                    $request,
                    $form,
                    $locked,
                    $registrationCounter,
                    $groupPeers,
                );
            }

            $before = $locked->replicate();

            $locked->forceFill([
                'review_status' => $newStatus,
                'reviewed_at' => now(),
                'reviewed_by' => (string) $request->user()->id,
            ]);

            $this->saveAcceptedWithUniqueRegistrationCode($locked, $codeIssuer);

            return [
                'kind' => 'ok',
                'answer' => $locked->fresh(),
                'rejected_ids' => collect(),
            ];
        });

        if ($payload['kind'] === 'conflict') {
            return response()->json([
                'message' => 'Submission has already been reviewed.',
                'current_status' => $payload['current_status'],
            ], 409);
        }

        /** @var FormAnswer $answer */
        $answer = $payload['answer'];

        /** @var Collection<int, string> $rejectedIds */
        $rejectedIds = $payload['rejected_ids'];

        if ($newStatus === FormAnswerReviewStatus::Accepted) {
            SendRegistrationAcceptedJob::dispatch($answer->id)->afterCommit();
        } else {
            foreach ($rejectedIds as $rejectedId) {
                SendRegistrationRejectedJob::dispatch($rejectedId)->afterCommit();
            }
        }

        $cascadedRejections = max(0, $rejectedIds->count() - 1);

        return response()->json([
            'id' => $answer->id,
            'review_status' => $answer->review_status->value,
            'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
            'reviewed_by' => $answer->reviewed_by,
            'registration_code' => $answer->registration_code,
            'cascaded_rejections' => $cascadedRejections,
        ]);
    }

    /**
     * @return array{kind: string, answer: FormAnswer, rejected_ids: Collection<int, string>}
     */
    private function rejectWithCascade(
        Request $request,
        Form $form,
        FormAnswer $locked,
        EventRegistrationCounter $registrationCounter,
        RegistrationGroupPeers $groupPeers,
    ): array {
        $peerIds = $groupPeers->peersNotYetRejected($form, $locked)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $rejectedIds = collect();
        $primaryAnswer = $locked;

        foreach ($peerIds as $peerId) {
            /** @var FormAnswer|null $peer */
            $peer = FormAnswer::query()
                ->whereKey($peerId)
                ->where('form_id', $form->id)
                ->lockForUpdate()
                ->first();

            if ($peer === null || $peer->review_status === FormAnswerReviewStatus::Rejected) {
                continue;
            }

            $before = $peer->replicate();

            $peer->forceFill([
                'review_status' => FormAnswerReviewStatus::Rejected,
                'reviewed_at' => now(),
                'reviewed_by' => (string) $request->user()->id,
            ]);
            $peer->save();

            $registrationCounter->releaseIfStoppedOccupying($before, $peer->fresh() ?? $peer);

            $rejectedIds->push((string) $peer->id);

            if ((string) $peer->id === (string) $locked->id) {
                $primaryAnswer = $peer->fresh() ?? $peer;
            }
        }

        return [
            'kind' => 'ok',
            'answer' => $primaryAnswer->fresh() ?? $primaryAnswer,
            'rejected_ids' => $rejectedIds,
        ];
    }

    private function saveAcceptedWithUniqueRegistrationCode(FormAnswer $answer, RegistrationCodeIssuer $issuer): void
    {
        $lastException = null;

        for ($i = 0; $i < self::REGISTRATION_CODE_ATTEMPTS; $i++) {
            $answer->registration_code = $issuer->generate();

            try {
                $answer->save();

                return;
            } catch (QueryException $e) {
                if ($this->isUniqueRegistrationCodeViolation($e)) {
                    $lastException = $e;

                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException(
            'Could not assign a unique registration code.',
            0,
            $lastException
        );
    }

    private function isUniqueRegistrationCodeViolation(QueryException $e): bool
    {
        $msg = Str::lower($e->getMessage());

        return str_contains($msg, 'unique')
            && str_contains($msg, 'registration_code');
    }
}
