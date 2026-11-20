<?php

namespace App\Services\Form;

use App\Enums\FormPurpose;
use Illuminate\Validation\ValidationException;

/**
 * Aturan tujuan form di sisi server.
 *
 * Metadata datang dari klien, jadi semua nilai di sini divalidasi ulang dengan allowlist.
 * Query string di halaman hanya prefill; keputusan akhir selalu di sini.
 */
final class FormPurposeRules
{
    /** Mode pendaftaran yang sah. */
    private const REGISTRATION_MODES = ['single', 'team', 'bundle'];

    /**
     * Nilai awal untuk halaman create. Query string tidak pernah dipercaya:
     * nilai asing atau D-Kelas jatuh kembali ke Pendaftaran single.
     *
     * @return array{purpose: string, mode: string}
     */
    public static function prefill(mixed $purpose, mixed $mode): array
    {
        $value = is_string($purpose) ? FormPurpose::tryFrom($purpose) : null;

        if ($value === null || $value === FormPurpose::DKelas) {
            return ['purpose' => FormPurpose::Registration->value, 'mode' => 'single'];
        }

        $registrationMode = is_string($mode) && in_array($mode, self::REGISTRATION_MODES, true) ? $mode : 'single';

        return [
            'purpose' => $value->value,
            'mode' => $value === FormPurpose::Registration ? $registrationMode : 'single',
        ];
    }

    /**
     * Normalisasi dan tegakkan aturan tujuan pada metadata yang akan disimpan.
     *
     * @param  array<string, mixed>  $metadata
     * @param  string|null  $currentPurpose  Tujuan tersimpan; `null` saat pembuatan form baru.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function apply(array $metadata, ?string $currentPurpose = null): array
    {
        $purpose = is_string($metadata['purpose'] ?? null) ? FormPurpose::tryFrom($metadata['purpose']) : null;

        if ($purpose === null) {
            throw ValidationException::withMessages([
                'metadata.purpose' => 'Tujuan form tidak dikenal.',
            ]);
        }

        if ($currentPurpose === null && $purpose === FormPurpose::DKelas) {
            throw ValidationException::withMessages([
                'metadata.purpose' => 'Form D-Kelas belum tersedia.',
            ]);
        }

        if ($currentPurpose === FormPurpose::DKelas->value && $purpose !== FormPurpose::DKelas) {
            throw ValidationException::withMessages([
                'metadata.purpose' => 'Tujuan D-Kelas tidak dapat diubah.',
            ]);
        }

        $mode = is_string($metadata['registration_mode'] ?? null) ? $metadata['registration_mode'] : 'single';

        if (! in_array($mode, self::REGISTRATION_MODES, true)) {
            throw ValidationException::withMessages([
                'metadata.registration_mode' => 'Mode pendaftaran tidak dikenal.',
            ]);
        }

        if ($purpose !== FormPurpose::Registration && $mode !== 'single') {
            throw ValidationException::withMessages([
                'metadata.registration_mode' => 'Tujuan ini hanya mendukung mode single.',
            ]);
        }

        $metadata['purpose'] = $purpose->value;
        $metadata['registration_mode'] = $mode;

        return $metadata;
    }
}
