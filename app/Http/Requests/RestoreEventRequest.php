<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Restore event soft-deleted. Autorisasi resource tetap di controller
 * (`authorize('restore', $event)`); request ini hanya memvalidasi tujuan redirect.
 */
class RestoreEventRequest extends FormRequest
{
    /** Halaman tujuan setelah restore, default halaman detail event. */
    public const DEFAULT_RETURN_TO = 'show';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Isi default `return_to` bila field tidak dikirim, sehingga klien tanpa body
     * tidak perlu menyertakan tujuan redirect. Nilai `null` eksplisit dibiarkan
     * agar ditangani oleh fallback di controller.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['return_to' => self::DEFAULT_RETURN_TO]);
    }

    /**
     * `nullable` disengaja: `null` eksplisit bukan nilai tak dikenal, jadi harus
     * jatuh ke default (`show`) di controller, bukan menjadi 422.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'return_to' => ['sometimes', 'nullable', 'string', Rule::in(['show', 'index', 'archived'])],
        ];
    }
}
