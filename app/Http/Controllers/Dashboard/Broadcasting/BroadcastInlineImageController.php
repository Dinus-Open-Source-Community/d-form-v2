<?php

namespace App\Http\Controllers\Dashboard\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Controller;
use App\Models\EmailBroadcast;
use App\Services\Broadcasting\BroadcastInlineImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Unggahan inline image editor broadcast (dipakai BroadcastContentEditor).
 * Pola mengikuti FormImageUploadController, khusus broadcast:
 * multipart field `file` -> { ok, path, url }.
 */
class BroadcastInlineImageController extends Controller
{
    public function __invoke(
        Request $request,
        EmailBroadcast $broadcast,
        BroadcastInlineImageService $images,
    ): JsonResponse {
        $this->authorize('update', $broadcast);

        abort_unless($broadcast->status === EmailBroadcastStatus::Draft, 422, 'Gambar hanya dapat diunggah saat draft.');

        $request->validate(
            [
                'file' => [
                    'required',
                    'image',
                    'mimes:png,jpg,jpeg,gif,webp',
                    'max:'.BroadcastInlineImageService::MAX_UPLOAD_KB,
                ],
            ],
            [
                'file.required' => 'Pilih gambar terlebih dahulu.',
                'file.image' => 'File harus berupa gambar.',
                'file.mimes' => 'Gambar harus PNG, JPG, GIF, atau WebP.',
                'file.max' => 'Gambar inline melebihi 2 MB.',
            ]
        );

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
