<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Http\Controllers\Controller;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastPersonalization;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastPreviewController extends Controller
{
    public function __invoke(EmailBroadcast $broadcast, BroadcastPersonalization $personalization): Response
    {
        $this->authorize('view', $broadcast);

        $eventName = $personalization->eventNameFor($broadcast);
        $sampleName = $broadcast->recipients()->first()?->name ?? 'Nafan';

        return Inertia::render('Dashboard/Broadcasts/Show', [
            'preview' => [
                'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                'subject' => $personalization->render($broadcast->subject, $sampleName, $eventName),
                'content' => $personalization->renderHtml($broadcast->content, $sampleName, $eventName),
                'sample_name' => $sampleName,
                'event_name' => $eventName,
                'attachments' => $broadcast->attachments->pluck('file_name')->all(),
            ],
        ]);
    }
}
