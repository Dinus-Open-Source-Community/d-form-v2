<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationAcceptedJob;
use App\Jobs\SendRegistrationRejectedJob;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationCodeIssuer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormAnswerReviewController extends Controller
{
    private const REGISTRATION_CODE_ATTEMPTS = 16;

    public function __invoke(
        Request $request,
        Event $event,
        Form $form,
        FormAnswer $formAnswer,
        RegistrationCodeIssuer $codeIssuer,
    ): JsonResponse {
        $this->authorize('review', $formAnswer);

        abort_unless($form->event_id === $event->id, 404);
        abort_unless($formAnswer->form_id === $form->id, 404);

        $data = $request->validate([
            'review_status' => ['required', Rule::enum(FormAnswerReviewStatus::class)],
        ]);

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

        $payload = DB::transaction(function () use ($request, $form, $formAnswer, $newStatus, $codeIssuer): array {
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

            $locked->forceFill([
                'review_status' => $newStatus,
                'reviewed_at' => now(),
                'reviewed_by' => (string) $request->user()->id,
            ]);

            if ($newStatus === FormAnswerReviewStatus::Accepted) {
                $this->saveAcceptedWithUniqueRegistrationCode($locked, $codeIssuer);
            } else {
                $locked->save();
            }

            return [
                'kind' => 'ok',
                'answer' => $locked->fresh(),
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

        if ($newStatus === FormAnswerReviewStatus::Accepted) {
            SendRegistrationAcceptedJob::dispatch($answer->id)->afterCommit();
        } else {
            SendRegistrationRejectedJob::dispatch($answer->id)->afterCommit();
        }

        return response()->json([
            'id' => $answer->id,
            'review_status' => $answer->review_status->value,
            'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
            'reviewed_by' => $answer->reviewed_by,
            'registration_code' => $answer->registration_code,
        ]);
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
