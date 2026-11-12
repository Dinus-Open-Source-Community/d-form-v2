<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Exceptions\FormAnswerReviewConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewFormAnswerRequest;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Form\FormAnswerReviewService;
use Illuminate\Http\JsonResponse;

class FormAnswerReviewController extends Controller
{
    public function __invoke(
        ReviewFormAnswerRequest $request,
        Event $event,
        Form $form,
        FormAnswer $formAnswer,
        FormAnswerReviewService $reviewService,
    ): JsonResponse {
        $newStatus = FormAnswerReviewStatus::from($request->validated('review_status'));
        try {
            $payload = $reviewService->reviewOne($event, $form, $formAnswer, $newStatus, $request->user());
        } catch (FormAnswerReviewConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'current_status' => $exception->currentStatus,
            ], $exception->getStatusCode());
        }

        /** @var FormAnswer $answer */
        $answer = $payload['answer'];

        /** @var \Illuminate\Support\Collection<int, string> $rejectedIds */
        $rejectedIds = $payload['rejected_ids'];

        return response()->json([
            'id' => $answer->id,
            'review_status' => $answer->review_status->value,
            'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
            'reviewed_by' => $answer->reviewed_by,
            'registration_code' => $answer->registration_code,
            'cascaded_rejections' => max(0, $rejectedIds->count() - 1),
        ]);
    }
}
