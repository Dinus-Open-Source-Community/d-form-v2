<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\SendBroadcastTestRequest;
use App\Mail\BroadcastMail;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastPersonalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

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

        Mail::to($request->string('email')->toString())->send(
            new BroadcastMail($subject, $html, $text, $broadcast->id)
        );

        return redirect()->back()->with('toast', ['message' => 'Test email dikirim (tidak memengaruhi statistik).', 'type' => 'success']);
    }
}
