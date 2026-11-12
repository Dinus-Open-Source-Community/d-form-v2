<?php

namespace App\Support;

use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;

/**
 * Konversi gambar legacy form yang masih berupa data: URL (base64 inline)
 * menjadi file di disk "public". DB hanya menyimpan path relatif storage
 * (mis. `form-images/{formId}/...`), konsisten dengan FormImageUploadController.
 *
 * Cakupan key yang dipindai:
 * - forms.banner_url
 * - form_fields.metadata -> bannerUrl, imageUrl (+ varian image_url)
 * - form_fields.metadata -> optionChoices[].imageUrl (+ varian image_url)
 *
 * Nilai normal (path relatif, /storage/..., http(s) URL, blob: preview)
 * TIDAK PERNAH diubah — fungsi convert hanya menyentuh string berprefix data:.
 */
final class LegacyFormImage
{
    public const FIELD_DIRECTORY_PREFIX = 'form-images';

    public const BANNER_DIRECTORY_PREFIX = 'form-banners';

    /** Batas aman decode (upload baru max 2MB; legacy diberi toleransi 5MB). */
    public const MAX_DECODED_BYTES = 5 * 1024 * 1024;

    /**
     * Allowlist disamakan dengan FormImageUploadController
     * (mimes: png,jpg,jpeg,gif,webp) agar tidak ada tipe file baru
     * yang masuk lewat jalur legacy.
     *
     * @var array<string, string> mime => extension
     */
    private const ALLOWED_MIME_TO_EXTENSION = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public static function isDataUrl(mixed $value): bool
    {
        return is_string($value) && str_starts_with(strtolower(ltrim($value)), 'data:');
    }

    /**
     * Decode data: URL base64 menjadi bytes + extension.
     *
     * @return array{extension: string, bytes: string}|null null bila bukan
     *         base64 valid / mime tidak diizinkan / oversize / bukan gambar.
     */
    public static function decode(string $dataUrl): ?array
    {
        $trimmed = trim($dataUrl);

        if (! preg_match('#^data:([a-zA-Z0-9.+\-/]+);base64,(.+)$#s', $trimmed, $matches)) {
            return null;
        }

        $extension = self::ALLOWED_MIME_TO_EXTENSION[strtolower($matches[1])] ?? null;
        if ($extension === null) {
            return null;
        }

        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        if (strlen($bytes) > self::MAX_DECODED_BYTES) {
            return null;
        }

        $image = @getimagesizefromstring($bytes);
        if ($image === false) {
            return null;
        }

        $decoded = @imagecreatefromstring($bytes);
        if ($decoded === false) {
            return null;
        }

        $maxPixels = (int) config('events.forms.upload_max_pixels', 25_000_000);
        $withinPixelLimit = imagesx($decoded) * imagesy($decoded) <= $maxPixels;
        imagedestroy($decoded);
        if ($withinPixelLimit === false) {
            return null;
        }

        return ['extension' => $extension, 'bytes' => $bytes];
    }

    /**
     * Simpan data: URL ke disk. Mengembalikan path relatif storage
     * (mis. `form-images/{formId}/legacy-uuid.png`) atau null bila gagal/skip.
     */
    public static function store(string $dataUrl, string $directory, string $disk = 'public'): ?string
    {
        $decoded = self::decode($dataUrl);
        if ($decoded === null) {
            return null;
        }

        try {
            $path = app(SafeUploadStorage::class)->storeImageBytes(
                $decoded['bytes'],
                new UploadStorageTarget(trim($directory, '/'), $disk),
            );
        } catch (\Throwable) {
            return null;
        }

        return $path;
    }

    /**
     * Konversi satu nilai URL: data: URL -> path storage, nilai lain utuh.
     *
     * @return array{value: string|null, converted: bool, failed: bool, skipped: bool}
     */
    public static function convertSingle(mixed $value, string $formId, string $directoryPrefix): array
    {
        if ($value === null) {
            return ['value' => null, 'converted' => false, 'failed' => false, 'skipped' => false];
        }

        if (! self::isDataUrl($value)) {
            return ['value' => $value, 'converted' => false, 'failed' => false, 'skipped' => false];
        }

        $path = self::store((string) $value, "{$directoryPrefix}/{$formId}");

        if ($path === null) {
            // Gagal decode / mime ditolak / oversize: pertahankan aslinya
            // agar tidak ada data yang hilang diam-diam.
            return ['value' => $value, 'converted' => false, 'failed' => true, 'skipped' => false];
        }

        return ['value' => $path, 'converted' => true, 'failed' => false, 'skipped' => false];
    }

    /**
     * @param  array<string, mixed> $metadata
     * @return array{metadata: array<string, mixed>, converted: int, failed: int}
     */
    public static function convertFieldMetadata(array $metadata, string $formId): array
    {
        $converted = 0;
        $failed = 0;

        foreach (['bannerUrl', 'imageUrl', 'image_url'] as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }
            $result = self::convertSingle($metadata[$key], $formId, self::FIELD_DIRECTORY_PREFIX);
            $metadata[$key] = $result['value'];
            $converted += $result['converted'] ? 1 : 0;
            $failed += $result['failed'] ? 1 : 0;
        }

        if (isset($metadata['optionChoices']) && is_array($metadata['optionChoices'])) {
            foreach ($metadata['optionChoices'] as $i => $choice) {
                if (! is_array($choice)) {
                    continue;
                }
                foreach (['imageUrl', 'image_url'] as $key) {
                    if (! array_key_exists($key, $choice)) {
                        continue;
                    }
                    $result = self::convertSingle($choice[$key], $formId, self::FIELD_DIRECTORY_PREFIX);
                    $choice[$key] = $result['value'];
                    $converted += $result['converted'] ? 1 : 0;
                    $failed += $result['failed'] ? 1 : 0;
                }
                $metadata['optionChoices'][$i] = $choice;
            }
        }

        return ['metadata' => $metadata, 'converted' => $converted, 'failed' => $failed];
    }

    /**
     * Konversi seluruh rows fields (bentuk validated `fields.*`).
     * Idempoten: rows tanpa data: URL dikembalikan apa adanya.
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, converted: int, failed: int}
     */
    public static function convertFieldRows(array $rows, string $formId): array
    {
        $converted = 0;
        $failed = 0;

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $meta = $row['metadata'] ?? null;
            if (! is_array($meta)) {
                continue;
            }
            $result = self::convertFieldMetadata($meta, $formId);
            $rows[$i]['metadata'] = $result['metadata'];
            $converted += $result['converted'];
            $failed += $result['failed'];
        }

        return ['rows' => $rows, 'converted' => $converted, 'failed' => $failed];
    }

    /**
     * Konversi forms.banner_url. Banner form disimpan di
     * `form-banners/{formId}/...` (paralel dengan pola events/banners).
     *
     * @return array{value: string|null, converted: bool, failed: bool}
     */
    public static function convertFormBanner(mixed $bannerUrl, string $formId): array
    {
        $result = self::convertSingle($bannerUrl, $formId, self::BANNER_DIRECTORY_PREFIX);

        return ['value' => $result['value'], 'converted' => $result['converted'], 'failed' => $result['failed']];
    }
}
