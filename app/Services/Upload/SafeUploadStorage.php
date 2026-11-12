<?php

namespace App\Services\Upload;

use App\Enums\UploadPurpose;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Menyimpan unggahan yang sudah tervalidasi isi.
 *
 * - Validasi isi memakai {@see UploadInspector} (satu implementasi untuk rule HTTP
 *   dan penyimpanan): sniff `finfo`, magic byte, kecocokan ekstensi/MIME klien,
 *   batas ukuran, dimensi gambar sebelum decode.
 * - Gambar raster selalu di-decode lalu di-re-encode agar payload tambahan dan
 *   metadata ikut hilang.
 * - Nama file selalu dibuat server dan ekstensinya berasal dari MIME hasil sniff,
 *   bukan dari nama klien.
 */
final class SafeUploadStorage
{
    /**
     * Simpan satu unggahan tervalidasi pada target dan purpose yang diberikan.
     *
     * Melempar `ValidationException` (422) bila isi file tidak lolos kebijakan
     * purpose — pertahanan terakhir untuk jalur yang tidak lewat rule request.
     */
    public function store(UploadedFile $file, UploadStorageTarget $target, UploadPurpose $purpose): string
    {
        if ($target->disk !== $purpose->disk()) {
            throw new \InvalidArgumentException(
                "Upload target disk [{$target->disk}] does not match purpose [{$purpose->value}]."
            );
        }

        $error = UploadInspector::inspect($file, $purpose);
        if ($error !== null) {
            throw ValidationException::withMessages(['file' => $error]);
        }

        $mime = UploadInspector::sniffedMime($file);
        if ($mime === null) {
            throw ValidationException::withMessages(['file' => 'The uploaded file type could not be detected.']);
        }

        if ($this->isRasterMime($mime)) {
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                throw new RuntimeException('Uploaded image could not be read.');
            }

            return $this->storeImageBytes($contents, $target, $purpose);
        }

        $extension = UploadInspector::extensionForUpload($file);
        if ($extension === null) {
            throw ValidationException::withMessages(['file' => 'The uploaded file type is not allowed.']);
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Uploaded file could not be read.');
        }

        $path = $this->generatedPath($target, $extension);
        if (Storage::disk($target->disk)->put($path, $contents) === false) {
            throw new RuntimeException('Uploaded file could not be stored.');
        }

        return $path;
    }

    /**
     * Decode, batasi, re-encode, dan simpan byte raster terpercaya dari boundary
     * mana pun (mis. base64 inline editor atau konversi gambar legacy).
     *
     * Pemeriksaan dimensi memakai header (`getimagesizefromstring`) sebelum
     * `imagecreatefromstring`, sehingga decompression bomb tidak pernah di-decode.
     */
    public function storeImageBytes(
        string $contents,
        UploadStorageTarget $target,
        UploadPurpose $purpose = UploadPurpose::Image,
    ): string {
        $maxBytes = $purpose->maxBytes();
        if (strlen($contents) > $maxBytes) {
            throw new RuntimeException('Image exceeds the upload size limit.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('Image type could not be detected.');
        }

        $mime = finfo_buffer($finfo, $contents);
        finfo_close($finfo);
        if (is_string($mime) === false || in_array($mime, $purpose->mimes(), true) === false || $this->isRasterMime($mime) === false) {
            throw new RuntimeException('Image type is not allowed.');
        }

        $header = @getimagesizefromstring($contents);
        $width = is_array($header) ? (int) ($header[0] ?? 0) : 0;
        $height = is_array($header) ? (int) ($header[1] ?? 0) : 0;
        $maxPixels = (int) config('events.forms.upload_max_pixels', 12_000_000);
        $maxDimension = (int) config('events.forms.upload_max_dimension', 8000);
        if ($width < 1 || $height < 1 || $width > $maxDimension || $height > $maxDimension || $width * $height > $maxPixels) {
            throw new RuntimeException('Image dimensions are not allowed.');
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('Uploaded image could not be decoded.');
        }

        ob_start();
        $encoded = match ($mime) {
            'image/jpeg' => imagejpeg($source, null, 90),
            'image/png' => imagepng($source, null, 6),
            'image/webp' => imagewebp($source, null, 90),
            'image/gif' => imagegif($source),
            default => false,
        };
        $contents = ob_get_clean();
        imagedestroy($source);
        if ($encoded === false || is_string($contents) === false) {
            throw new RuntimeException('Uploaded image could not be safely re-encoded.');
        }

        if (strlen($contents) > $maxBytes) {
            throw new RuntimeException('Re-encoded image exceeds the upload size limit.');
        }

        $extension = UploadInspector::extensionForMime($mime);
        if ($extension === null) {
            throw new RuntimeException('Image type is not allowed.');
        }

        $path = $this->generatedPath($target, $extension);
        if (Storage::disk($target->disk)->put($path, $contents) === false) {
            throw new RuntimeException('Re-encoded image could not be stored.');
        }

        return $path;
    }

    private function generatedPath(UploadStorageTarget $target, string $extension): string
    {
        return $target->directory.'/'.bin2hex(random_bytes(16)).'.'.$extension;
    }

    private function isRasterMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }
}
