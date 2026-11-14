<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkFormAnswerReviewRequest;
use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use App\Services\Form\FormAnswerReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Adapts the authorized bulk HTTP contract to the atomic review service. */
final class BulkFormAnswerReviewController extends Controller
{
    public function __invoke(
        BulkFormAnswerReviewRequest $request,
        Event $event,
        Form $form,
        FormAnswerReviewService $reviewService,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validated();
        $status = FormAnswerReviewStatus::from($validated['review_status']);

        try {
            /** @var Collection<int, string> $submissionIds */
            $submissionIds = collect($validated['submission_ids']);
            $result = $reviewService->reviewMany($event, $form, $submissionIds, $status, $actor);
        } catch (HttpExceptionInterface $exception) {
            return response()->json([
                'message' => $this->publicMessage($exception),
            ], $exception->getStatusCode());
        }

        return response()->json([
            'requested' => $submissionIds->count(),
            'processed' => $result->processedCount(),
            'skipped' => 0,
            'conflicts' => 0,
            'queued_intents' => $result->queuedIntentCount(),
        ]);
    }

    private function publicMessage(HttpExceptionInterface $exception): string
    {
        if (in_array($exception->getStatusCode(), [409, 422], true)) {
            return $exception->getMessage();
        }

        return 'Bulk review could not be completed.';
    }
}
