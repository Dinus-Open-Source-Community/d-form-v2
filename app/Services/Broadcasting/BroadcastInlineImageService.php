<?php

namespace App\Services\Broadcasting;

use App\Models\EmailBroadcast;
use App\Enums\UploadPurpose;
use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;
use App\Support\LogRedaction;
use App\Support\PublicStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Inline image broadcast tersimpan di storage Laravel (disk `public`), DB hanya menyimpan path/URL — bukan base64. */
class BroadcastInlineImageService
{
    public const MAX_UPLOAD_KB = 5120; // Shared global upload limit: 5 MB.

    public const MAX_UPLOAD_BYTES = 5242880; // Shared global upload limit: 5 MB.

    /** @var array<int, string> */
    public const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** @var array<string, string> mime subtype => ekstensi */
    private const MIME_TO_EXTENSION = [
        'png' => 'png',
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'gif' => 'gif',
        'webp' => 'webp',
    ];

    /**
     *
     * Simpan file unggahan editor ke `broadcasts/{id}/inline/...`.
     *
     * @return array{path:string, url:string}
     *
     */
    public function store(EmailBroadcast $broadcast, UploadedFile $file): array
    {
        $path = app(SafeUploadStorage::class)->store(
            $file,
            UploadStorageTarget::public('broadcasts/'.$broadcast->id.'/inline'),
            UploadPurpose::Image,
        );

        return [
            'path' => $path,
            'url' => (string) PublicStorage::url($path),
        ];
    }

    public function containsDataUrl(?string $html): bool
    {
        if ($html === null || $html === '') {
            return false;
        }

        return str_contains($html, 'data:image/');
    }

    /**
     * Migrate inline images; invalid or storage-failed images remain unchanged and are counted as skipped.
     * @return array{html:string, migrated:int, skipped:int}
     */
    public function migrateHtml(EmailBroadcast $broadcast, ?string $html): array
    {
        $html = (string) $html;
        $migrated = 0;
        $skipped = 0;

        if ($html === '' || ! $this->containsDataUrl($html)) {
            return ['html' => $html, 'migrated' => 0, 'skipped' => 0];
        }

        $result = preg_replace_callback(
            '#src=(["\'])data:image/([^"\']+)\1#i',
            function (array $matches) use ($broadcast, &$migrated, &$skipped): string {
                $stored = $this->storeDataUrl($broadcast, 'data:image/'.$matches[2]);

                if ($stored === null) {
                    $skipped++;
                    $quote = $matches[1];

                    return 'src='.$quote.'data:image/'.$matches[2].$quote;
                }

                $migrated++;
                $quote = $matches[1];

                return 'src='.$quote.$stored['url'].$quote;
            },
            $html
        );

        return ['html' => $result ?? $html, 'migrated' => $migrated, 'skipped' => $skipped];
    }

    /** Pesan penolakan bila HTML masih mengandung data: URL sesudah migrasi otomatis (tidak valid / melebihi batas). */
    public function rejectionMessage(?string $html): ?string
    {
        if (! $this->containsDataUrl($html)) {
            return null;
        }

        return 'Konten masih mengandung gambar base64 (data: URL) yang tidak dapat dipindahkan ke storage. '
            .'Silakan unggah ulang gambar lewat tombol unggah gambar (maks 5 MB, PNG/JPG/GIF/WebP) lalu simpan kembali.';
    }

    /**
     *
     * Decode satu data: URL lalu simpan ke storage. Null bila tidak valid.
     *
     * @return array{path:string, url:string}|null
     *
     */
    private function storeDataUrl(EmailBroadcast $broadcast, string $dataUrl): ?array
    {
        $compact = (string) preg_replace('/\s+/', '', $dataUrl);

        if (! preg_match('#^data:image/([a-zA-Z]+);base64,([a-zA-Z0-9+/=]+)$#', $compact, $parts)) {
            return null;
        }

        $subtype = strtolower($parts[1]);

        if (! isset(self::MIME_TO_EXTENSION[$subtype])) {
            return null;
        }

        $binary = base64_decode($parts[2], true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_UPLOAD_BYTES) {
            return null;
        }

        if (@getimagesizefromstring($binary) === false) {
            return null;
        }

        try {
            $path = app(SafeUploadStorage::class)->storeImageBytes(
                $binary,
                UploadStorageTarget::public('broadcasts/'.$broadcast->id.'/inline'),
            );
        } catch (Throwable $exception) {
            $this->reportStorageFailure($broadcast, $exception);

            return null;
        }

        return [
            'path' => $path,
            'url' => (string) PublicStorage::url($path),
        ];
    }

    /** Log a storage failure with a correlation id so it is distinguishable from invalid image input in operations. */
    private function reportStorageFailure(EmailBroadcast $broadcast, Throwable $exception): void
    {
        $requestId = request()->header('X-Request-ID');
        $correlationId = is_string($requestId) && Str::isUuid($requestId)
            ? $requestId
            : (string) Str::uuid();

        Log::error('broadcast_inline_image.storage_failed', [
            'correlation_id' => $correlationId,
            'broadcast_id' => (string) $broadcast->id,
            'disk' => 'public',
            'exception_class' => $exception::class,
            'message' => LogRedaction::exception($exception),
        ]);
    }

    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return in_array($extension, ['png', 'jpg', 'gif', 'webp'], true) ? $extension : 'png';
    }
}
