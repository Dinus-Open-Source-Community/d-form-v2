<?php

namespace App\Rules;

use App\Enums\UploadPurpose;
use App\Services\Upload\UploadInspector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/** Rule HTTP untuk unggahan: menerjemahkan hasil {@see UploadInspector} menjadi error validasi per field (422). */
final class SafeUploadFile implements ValidationRule
{
    /** Format yang dipakai bila pemanggil tidak menyebut daftar ekstensi sendiri. */
    public const DEFAULT_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    /** Return the shared required/optional file rules for a purpose (or legacy format list). */
    public static function rules(
        bool $required = true,
        UploadPurpose|array $purposeOrFormats = UploadPurpose::Image,
        ?int $maxSizeKb = null
    ): array {
        return [$required ? 'required' : 'nullable', 'file', new self($purposeOrFormats, $maxSizeKb)];
    }

    private readonly UploadPurpose $purpose;

    /** @var list<string>|null */
    private readonly ?array $allowedExtensions;

    /**
     *
     * @param  UploadPurpose|list<string>  $purposeOrFormats  Purpose atau daftar ekstensi dinamis.
     * @param  int|null  $maxSizeKb  Batas per field (KB); selalu di-clamp ke batas purpose.
     *
     */
    public function __construct(
        UploadPurpose|array $purposeOrFormats = UploadPurpose::Image,
        private readonly ?int $maxSizeKb = null
    ) {
        if ($purposeOrFormats instanceof UploadPurpose) {
            $this->purpose = $purposeOrFormats;
            $this->allowedExtensions = null;
        } else {
            $this->purpose = UploadPurpose::FormAnswerFile;
            $this->allowedExtensions = $purposeOrFormats;
        }
    }

    /** Tolak nama berbahaya, tipe yang tidak cocok dengan isi, konten aktif, dan ukuran/dimensi berlebih. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($value instanceof UploadedFile) === false) {
            $fail('The uploaded file is invalid.');

            return;
        }

        $message = UploadInspector::inspect($value, $this->purpose, $this->allowedExtensions, $this->maxSizeKb);
        if ($message !== null) {
            $fail($message);
        }
    }
}
