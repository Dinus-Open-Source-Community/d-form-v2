<?php

namespace App\Services\Broadcasting;

use App\Models\EmailBroadcast;
use App\Support\PublicStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Inline image broadcast tersimpan di storage Laravel (disk `public`),
 * DB hanya menyimpan path/URL — bukan base64.
 *
 * Alur:
 * - Editor mengunggah file via BroadcastInlineImageController::class
 *   lalu menyisipkan URL storage ke konten.
 * - Konten lama (base64 di DB) dimigrasi oleh migrateHtml() yang
 *   dipanggil otomatis saat save konten + artisan
 *   `broadcasts:migrate-inline-images`.
 */
class BroadcastInlineImageService
{
    public const MAX_UPLOAD_KB = 2048; // 2 MB, selaras validasi endpoint

    public const MAX_UPLOAD_BYTES = 2097152;

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
     * Simpan file unggahan editor ke `broadcasts/{id}/inline/...`.
     *
     * @return array{path:string, url:string}
     */
    public function store(EmailBroadcast $broadcast, UploadedFile $file): array
    {
        $extension = $this->normalizeExtension(
            $file->getClientOriginalExtension() !== ''
                ? $file->getClientOriginalExtension()
                : ($file->guessExtension() ?? 'png')
        );

        $path = $file->storeAs(
            'broadcasts/'.$broadcast->id.'/inline',
            (string) Str::uuid().'.'.$extension,
            'public'
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
     * Pindahkan setiap src="data:image/..." di HTML ke storage lalu
     * tulis ulang src ke URL storage. Gambar yang tidak valid /
     * melebihi batas dibiarkan apa adanya dan dihitung sebagai skipped
     * agar controller bisa menolak dengan pesan yang jelas.
     *
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

    /**
     * Pesan penolakan bila HTML masih mengandung data: URL sesudah
     * migrasi otomatis (tidak valid / melebihi batas). Null bila bersih.
     */
    public function rejectionMessage(?string $html): ?string
    {
        if (! $this->containsDataUrl($html)) {
            return null;
        }

        return 'Konten masih mengandung gambar base64 (data: URL) yang tidak dapat dipindahkan ke storage. '
            .'Silakan unggah ulang gambar lewat tombol unggah gambar (maks 2 MB, PNG/JPG/GIF/WebP) lalu simpan kembali.';
    }

    /**
     * Decode satu data: URL lalu simpan ke storage. Null bila tidak valid.
     *
     * @return array{path:string, url:string}|null
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

        $extension = self::MIME_TO_EXTENSION[$subtype];
        $path = 'broadcasts/'.$broadcast->id.'/inline/'.(string) Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => (string) PublicStorage::url($path),
        ];
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
