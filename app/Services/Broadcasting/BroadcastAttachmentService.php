<?php

namespace App\Services\Broadcasting;

use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BroadcastAttachmentService
{
    public const MAX_FILE_BYTES = 1572864; // 1.5 MB

    public const MAX_INLINE_IMAGE_BYTES = 1572864; // 1.5 MB ukuran file asli

    public function store(EmailBroadcast $broadcast, UploadedFile $file): EmailBroadcastAttachment
    {
        $size = $file->getSize() ?? 0;

        abort_if($size > self::MAX_FILE_BYTES, 422, 'Attachment melebihi 1.5 MB.');

        $path = $file->storeAs(
            'broadcasts/'.$broadcast->id,
            Str::uuid()->toString().'_'.$file->getClientOriginalName(),
            'local',
        );

        return $broadcast->attachments()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $size,
        ]);
    }

    public function delete(EmailBroadcastAttachment $attachment): void
    {
        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();
    }

    public function deleteAllFor(EmailBroadcast $broadcast): void
    {
        foreach ($broadcast->attachments()->get() as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        Storage::disk('local')->deleteDirectory('broadcasts/'.$broadcast->id);
    }

    /**
     * Validasi inline image base64 dari Tiptap (data:image/...).
     * @return array{ok:bool, message?:string}
     */
    public function validateInlineImages(?string $html): array
    {
        if ($html === null || $html === '') {
            return ['ok' => true];
        }

        preg_match_all('#src="(data:image/[^"]+)"#', $html, $matches);

        foreach ($matches[1] ?? [] as $dataUrl) {
            $compact = preg_replace('/\s+/', '', $dataUrl);
            $parts = explode(',', $compact, 2);

            if (count($parts) !== 2) {
                return ['ok' => false, 'message' => 'Inline image tidak valid.'];
            }

            $binary = base64_decode($parts[1], true);

            if ($binary === false) {
                return ['ok' => false, 'message' => 'Inline image tidak valid (base64).'];
            }

            if (strlen($binary) > self::MAX_INLINE_IMAGE_BYTES) {
                return ['ok' => false, 'message' => 'Inline image melebihi 1.5 MB.'];
            }
        }

        return ['ok' => true];
    }
}
