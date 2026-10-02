<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\SaveBroadcastContentRequest;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastAttachmentService;
use App\Services\Broadcasting\BroadcastHtmlSanitizer;
use App\Services\Broadcasting\BroadcastPersonalization;
use Illuminate\Http\RedirectResponse;

class BroadcastContentController extends Controller
{
    public function __invoke(
        SaveBroadcastContentRequest $request,
        EmailBroadcast $broadcast,
        BroadcastHtmlSanitizer $sanitizer,
        BroadcastPersonalization $personalization,
        BroadcastAttachmentService $attachments,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);

        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Konten hanya dapat diubah saat draft.');

        $rawContent = $request->string('content')->toString();
        $rawSubject = $request->string('subject')->toString();
        $sanitized = $sanitizer->sanitize($rawContent);

        $unsupported = array_unique(array_merge(
            $personalization->unsupportedVariables($rawSubject),
            $personalization->unsupportedVariables($sanitized),
        ));

        if ($unsupported !== []) {
            return redirect()->back()->withErrors([
                'subject' => 'Unsupported variable: '.implode(', ', $unsupported),
            ]);
        }

        $inline = $attachments->validateInlineImages($rawContent);

        if (! ($inline['ok'] ?? true)) {
            return redirect()->back()->withErrors(['content' => $inline['message'] ?? 'Inline image tidak valid.']);
        }

        $broadcast->forceFill([
            'subject' => $rawSubject,
            'content' => $sanitized,
            'event_id' => $request->input('event_id'),
        ])->save();

        return redirect()->back()->with('toast', ['message' => 'Email tersimpan.', 'type' => 'success']);
    }
}
