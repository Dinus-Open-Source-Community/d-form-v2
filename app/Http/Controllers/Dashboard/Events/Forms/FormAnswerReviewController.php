<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormAnswerReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormAnswerReviewController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form, FormAnswer $formAnswer): JsonResponse
    {
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

        $newStatus = $data['review_status'];
        if ($newStatus === FormAnswerReviewStatus::Pending) {
            return response()->json([
                'message' => 'Invalid review status transition.',
            ], 422);
        }

        $formAnswer->forceFill([
            'review_status' => $newStatus,
            'reviewed_at' => now(),
            'reviewed_by' => (string) $request->user()->id,
        ])->save();

        return response()->json([
            'id' => $formAnswer->id,
            'review_status' => $formAnswer->review_status->value,
            'reviewed_at' => $formAnswer->reviewed_at?->toIso8601String(),
            'reviewed_by' => $formAnswer->reviewed_by,
        ]);
    }
}

