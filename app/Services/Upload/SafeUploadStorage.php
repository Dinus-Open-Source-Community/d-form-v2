<?php

namespace App\Services\Upload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Stores validated uploads and re-encodes raster images to discard appended payloads and metadata. */
final class SafeUploadStorage
{
    /** Store an image as a fresh raster or a non-image file after shared validation. */
    public function store(UploadedFile $file, UploadStorageTarget $target): string
    {
        $mime = (string) $file->getMimeType();
        if (str_starts_with($mime, 'image/') === false) {
            $path = $file->store($target->directory, $target->disk);
            if (is_string($path) === false) {
                throw new RuntimeException('Uploaded file could not be stored.');
            }

            return $path;
        }

        $sourceContents = file_get_contents($file->getRealPath());
        if ($sourceContents === false) {
            throw new RuntimeException('Uploaded image could not be read.');
        }

        return $this->storeImageBytes($sourceContents, $target);
    }

    /** Decode, constrain, re-encode, and store trusted raster bytes from any input boundary. */
    public function storeImageBytes(string $contents, UploadStorageTarget $target): string
    {
        $maxBytes = (int) config('events.forms.upload_max_size_kb', 5120) * 1024;
        if (strlen($contents) > $maxBytes) {
            throw new RuntimeException('Image exceeds the upload size limit.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('Image type could not be detected.');
        }

        $mime = finfo_buffer($finfo, $contents);
        finfo_close($finfo);
        if (is_string($mime) === false || in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) === false) {
            throw new RuntimeException('Image type is not allowed.');
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('Uploaded image could not be decoded.');
        }

        $maxPixels = (int) config('events.forms.upload_max_pixels', 25_000_000);
        if (imagesx($source) * imagesy($source) > $maxPixels) {
            imagedestroy($source);
            throw new RuntimeException('Image dimensions are not allowed.');
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

        $maxBytes = (int) config('events.forms.upload_max_size_kb', 5120) * 1024;
        if (strlen($contents) > $maxBytes) {
            throw new RuntimeException('Re-encoded image exceeds the upload size limit.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        };
        $path = $target->directory.'/'.bin2hex(random_bytes(16)).'.'.$extension;
        $stored = Storage::disk($target->disk)->put($path, $contents);
        if ($stored === false) {
            throw new RuntimeException('Re-encoded image could not be stored.');
        }

        return $path;
    }
}
