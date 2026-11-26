<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\Form\FormPriceService;
use App\Services\Form\ReferralRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pratinjau kode referral di halaman isi form; endpoint ini tidak pernah menulis data. */
final class FormReferralCheckController extends Controller
{
    public function __construct(
        private readonly ReferralRedemptionService $redemptions,
        private readonly FormPriceService $prices,
    ) {
    }

    public function __invoke(Request $request, Event $event, Form $form): JsonResponse
    {
        abort_unless((string) $form->event_id === (string) $event->id, 404);
        abort_if($form->isDraft(), 404);

        $validated = $request->validate(['code' => ['nullable', 'string', 'max:29']]);
        $code = $validated['code'] ?? null;
        $result = $this->redemptions->check($form, is_string($code) ? $code : null);

        return response()->json([
            'valid' => $result['valid'],
            'message' => $result['message'],
            'price' => $this->prices->quote($this->prices->basePrice($form), $result['code']),
        ]);
    }
}
