<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string laporan kehadiran event.
 *
 * `per_page` dibatasi allowlist agar paginator tidak bisa dipaksa memuat ribuan
 * relasi sekaligus. Nilai tidak valid/tidak ada jatuh ke default 20, bukan 422:
 * laporan harus tetap render (lihat BE-P3-018).
 *
 * `page` sengaja tidak divalidasi di sini: `LengthAwarePaginator` sudah
 * menormalkan 0/negatif/non-numerik menjadi halaman 1, dan
 * `EventReportingQuery::paginateAttendanceLog()` meng-clamp halaman di luar
 * `last_page` ke halaman terakhir (BE-P3-019).
 */
class ShowEventReportRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [8, 20, 25, 50, 100];

    public const DEFAULT_PER_PAGE = 20;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Satu-satunya sumber allowlist: nilai yang tidak ada di
     * {@see self::PER_PAGE_OPTIONS} diganti default sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        $requested = filter_var($this->input('per_page'), FILTER_VALIDATE_INT);

        $this->merge([
            'per_page' => in_array($requested, self::PER_PAGE_OPTIONS, true)
                ? $requested
                : self::DEFAULT_PER_PAGE,
        ]);
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', self::DEFAULT_PER_PAGE);
    }
}
