<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Exceptions\RedactedMailDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\SendBroadcastTestRequest;
use App\Mail\BroadcastMail;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastPersonalization;
use App\Support\LogRedaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BroadcastTestController extends Controller
{
    public function __invoke(
        SendBroadcastTestRequest $request,
        EmailBroadcast $broadcast,
        BroadcastPersonalization $personalization,
    ): RedirectResponse {
        $this->authorize('view', $broadcast);

        $eventName = $personalization->eventNameFor($broadcast);
        $subject = $personalization->render($broadcast->subject ?? '(tanpa subject)', 'Nafan', $eventName);
        $html = $personalization->renderHtml($broadcast->content ?? '', 'Nafan', $eventName);
        $text = $personalization->render(BroadcastMail::textFallback($broadcast->content ?? ''), 'Nafan', $eventName);

        try {
            Mail::to($request->string('email')->toString())->send(
                new BroadcastMail($subject, $html, $text, $broadcast->id)
            );
        } catch (Throwable $exception) {
            report(RedactedMailDeliveryException::fromThrowable($exception));

            return redirect()->back()->with('toast', [
                'message' => LogRedaction::MAIL_DELIVERY_FAILURE_MESSAGE,
                'type' => 'error',
                'error_category' => LogRedaction::MAIL_DELIVERY_FAILURE_CATEGORY,
            ]);
        }

        return redirect()->back()->with('toast', ['message' => 'Test email dikirim (tidak memengaruhi statistik).', 'type' => 'success']);
    }
}
