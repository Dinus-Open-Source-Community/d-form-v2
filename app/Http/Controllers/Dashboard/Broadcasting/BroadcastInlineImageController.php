<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\StoreBroadcastInlineImageRequest;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastInlineImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Unggahan inline image editor broadcast (dipakai BroadcastContentEditor).
 * Pola mengikuti FormImageUploadController, khusus broadcast:
 * multipart field `file` -> { ok, path, url }.
 */
class BroadcastInlineImageController extends Controller
{
    public function __invoke(
        StoreBroadcastInlineImageRequest $request,
        EmailBroadcast $broadcast,
        BroadcastInlineImageService $images,
    ): JsonResponse {
        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Gambar hanya dapat diunggah saat draft.');

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $stored = $images->store($broadcast, $file);

        return response()->json([
            'ok' => true,
            'path' => $stored['path'],
            'url' => $stored['url'],
        ]);
    }
}
