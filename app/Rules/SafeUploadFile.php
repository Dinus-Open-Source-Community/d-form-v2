<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/** Validates upload size and decoded content against the shared safe format allowlist. */
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

    /** Rejects mismatched extensions, invalid image payloads, and files above the global limit. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The uploaded file is invalid.');
            return;
        }
        if ($value->getSize() > (int) config('events.forms.upload_max_size_kb', 5120) * 1024) {
            $fail('The file may not be greater than 5 MB.');
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mime = $value->getMimeType();
        if (! is_string($mime)) {
            $fail('The uploaded file type could not be detected.');
            return;
        }
        if (! in_array($extension, $this->formats, true)) {
            $fail('The uploaded file format is not allowed.');
            return;
        }

        if ($extension === 'pdf') {
            $handle = fopen($value->getRealPath(), 'rb');
            $signature = $handle === false ? false : fread($handle, 5);
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
                $fail('The file content does not match an allowed format.');
            }
            return;
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            $contents = file_get_contents($value->getRealPath());
            if ($contents === false || str_contains($contents, "\0") || ! mb_check_encoding($contents, 'UTF-8')) {
                $fail('The text file content is invalid.');
            }
            return;
        }

        $image = @getimagesize($value->getRealPath());
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if ($image === false || ! in_array($image['mime'], $allowedImageMimes, true) || $image['mime'] !== $mime) {
            $fail('The image content is invalid or does not match its detected format.');
        }
    }
}
