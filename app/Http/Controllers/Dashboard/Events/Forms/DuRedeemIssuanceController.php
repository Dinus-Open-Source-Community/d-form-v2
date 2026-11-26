<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Du\DuRedeemIssuanceResult;
use App\Services\Du\DuRedeemIssuanceService;
use App\Services\Form\FormReviewEmailOutboxIntents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DuRedeemIssuanceController extends Controller
{
    public function __invoke(
        Request $request,
        Event $event,
        Form $form,
        FormAnswer $formAnswer,
        DuRedeemIssuanceService $service,
        FormReviewEmailOutboxIntents $outboxIntents,
    ): JsonResponse {
        abort_unless((string) $form->event_id === (string) $event->id, 404);
        abort_unless((string) $formAnswer->form_id === (string) $form->id, 404);
        $this->authorize('update', $event);
        $this->authorize('review', $formAnswer);
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'in:issue,reissue,resend'],
            'course_uid' => ['nullable', 'string', 'max:255'],
        ]);

        $action = $validated['action'] ?? 'issue';
        $courseUid = $validated['course_uid'] ?? null;

        if ($action === 'resend') {
            if ($outboxIntents->duResend($formAnswer) === null) {
                return response()->json(['message' => 'Kode redeem belum terbit.'], 422);
            }

            return response()->json([
                'status' => 'issued',
                'code' => $formAnswer->duRedeemIssuance()->value('redeem_code'),
                'message' => 'Email dikirim ulang.',
                'created_new_code' => false,
            ]);
        }

        $result = $service->issueAccepted($formAnswer, $courseUid, $action === 'reissue');

        if ($action === 'reissue') {
            if ($result->createdNewCode) {
                $outboxIntents->duIssuedFor($formAnswer, $result, true);
            }
        } else {
            $outboxIntents->duIssuedFor($formAnswer, $result);
        }

        return $this->issuanceResponse($result);
    }

    private function issuanceResponse(DuRedeemIssuanceResult $result): JsonResponse
    {
        return response()->json([
            'status' => $result->status->value,
            'code' => $result->code,
            'message' => $result->message,
            'created_new_code' => $result->createdNewCode,
        ]);
    }
}
