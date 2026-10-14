<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\SaveBroadcastContentRequest;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastAttachmentService;
use App\Services\Broadcasting\BroadcastHtmlSanitizer;
use App\Services\Broadcasting\BroadcastInlineImageService;
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
        BroadcastInlineImageService $inlineImages,
    ): RedirectResponse {
        $this->authorize('update', $broadcast);

        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Konten hanya dapat diubah saat draft.');

        $rawContent = $request->string('content')->toString();
        $rawSubject = $request->string('subject')->toString();

        // DB tidak menyimpan base64: pindahkan data: URL ke storage dulu.
        // Gambar valid otomatis termigrasi; sisanya ditolak dengan pesan jelas.
        $migration = $inlineImages->migrateHtml($broadcast, $rawContent);

        if ($migration['skipped'] > 0 || $inlineImages->containsDataUrl($migration['html'])) {
            $message = $inlineImages->rejectionMessage($migration['html'])
                ?? 'Inline image tidak valid (maks 2 MB, PNG/JPG/GIF/WebP).';

            return redirect()->back()->withErrors(['content' => $message]);
        }

        $sanitized = $sanitizer->sanitize($migration['html']);

        $unsupported = array_unique(array_merge(
            $personalization->unsupportedVariables($rawSubject),
            $personalization->unsupportedVariables($sanitized),
        ));

        if ($unsupported !== []) {
            return redirect()->back()->withErrors([
                'subject' => 'Unsupported variable: '.implode(', ', $unsupported),
            ]);
        }

        // Pertahanan berlapis: setelah migrasi tidak boleh ada data: URL tersisa.
        $inline = $attachments->validateInlineImages($migration['html']);

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
