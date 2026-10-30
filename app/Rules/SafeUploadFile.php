<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/** Validates upload names, bytes, structure, and decoded content without third-party packages. */
final class SafeUploadFile implements ValidationRule
{
    /** Format yang dipakai bila pemanggil tidak menyebut daftar ekstensi sendiri. */
    public const DEFAULT_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    /** Return the shared required/optional file rules for a known format group. */
    public static function rules(bool $required = true, array $formats = self::DEFAULT_FORMATS): array
    {
        return [$required ? 'required' : 'nullable', 'file', new self($formats)];
    }

    /** @param list<string> $formats */
    public function __construct(private readonly array $formats = self::DEFAULT_FORMATS)
    {
    }

    /** Rejects hostile names, malformed structures, active PDF content, and oversized decoded images. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($value instanceof UploadedFile) === false || $value->isValid() === false) {
            $fail('The uploaded file is invalid.');
            return;
        }

        $size = $value->getSize();
        $maxBytes = (int) config('events.forms.upload_max_size_kb', 5120) * 1024;
        if ($size === null || $size > $maxBytes) {
            $fail('The file may not be greater than 5 MB.');
            return;
        }

        if ($this->hasUnsafeName($value)) {
            $fail('The uploaded file name is invalid.');
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        if ($extension === '' || in_array($extension, $this->formats, true) === false) {
            $fail('The uploaded file format is not allowed.');
            return;
        }

        $mime = $this->detectMime($value);
        if ($mime === null) {
            $fail('The uploaded file type could not be detected.');
            return;
        }

        $message = match (true) {
            $extension === 'pdf' => $this->validatePdf($value, $mime),
            in_array($extension, ['csv', 'txt'], true) => $this->validateText($value),
            default => $this->validateImage($value, $extension, $mime),
        };

        if ($message !== null) {
            $fail($message);
        }
    }

    /** Rejects path traversal, control characters, alternate stream syntax, and hidden names. */
    private function hasUnsafeName(UploadedFile $file): bool
    {
        $name = $file->getClientOriginalName();

        return $name === ''
            || basename($name) !== $name
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, ':')
            || str_starts_with($name, '.')
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1;
    }

    /** Detects content from the temporary file rather than trusting the request MIME header. */
    private function detectMime(UploadedFile $file): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        return is_string($mime) ? $mime : null;
    }

    /** Checks PDF header, EOF marker, and active features that must never enter this application. */
    private function validatePdf(UploadedFile $file, string $mime): ?string
    {
        if ($mime !== 'application/pdf') {
            return 'The PDF content does not match its detected format.';
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || str_starts_with($contents, '%PDF-') === false) {
            return 'The PDF header is invalid.';
        }

        if (str_contains(substr($contents, -1024), '%%EOF') === false) {
            return 'The PDF is incomplete.';
        }

        if (preg_match('/\/(?:JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|RichMedia|SubmitForm|ImportData|GoToR|XFA|Encrypt)\b/i', $contents) === 1) {
            return 'The PDF contains active or embedded content that is not allowed.';
        }

        return null;
    }

    /** Accepts only UTF-8 text without binary control bytes. */
    private function validateText(UploadedFile $file): ?string
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || mb_check_encoding($contents, 'UTF-8') === false) {
            return 'The text file content is invalid.';
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $contents) === 1) {
            return 'The text file contains binary control bytes.';
        }

        return null;
    }

    /** Decodes raster metadata and enforces MIME/extension and decompression-bomb limits. */
    private function validateImage(UploadedFile $file, string $extension, string $mime): ?string
    {
        $image = @getimagesize($file->getRealPath());
        if ($image === false || is_string($image['mime'] ?? null) === false) {
            return 'The image content is invalid.';
        }

        $expectedMimes = match ($extension) {
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
            default => [],
        };

        if (in_array($image['mime'], $expectedMimes, true) === false || $image['mime'] !== $mime) {
            return 'The image content does not match its detected format.';
        }

        $width = $image[0] ?? 0;
        $height = $image[1] ?? 0;
        $maxPixels = (int) config('events.forms.upload_max_pixels', 25_000_000);
        if (is_int($width) === false || is_int($height) === false || $width < 1 || $height < 1 || $width * $height > $maxPixels) {
            return 'The image dimensions are not allowed.';
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            return 'The image content could not be read.';
        }

        $decoded = @imagecreatefromstring($contents);
        if ($decoded === false) {
            return 'The image content could not be fully decoded.';
        }

        imagedestroy($decoded);

        return null;
    }
}
