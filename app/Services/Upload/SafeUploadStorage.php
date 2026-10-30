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
        if (! str_starts_with($mime, 'image/')) {
            return $file->store($target->directory, $target->disk);
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
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
        if (! $encoded || ! is_string($contents)) {
            throw new RuntimeException('Uploaded image could not be safely re-encoded.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        };
        $path = $target->directory.'/'.bin2hex(random_bytes(16)).'.'.$extension;
        Storage::disk($target->disk)->put($path, $contents);
        return $path;
    }
}
