/**
 * Batas `metadata.maxLength` per builder type — satu sumber untuk editor field.
 * Nilai yang sama dipakai validasi backend (`FormFieldsRequestValidation::MAX_LENGTH_LIMITS`),
 * jadi klien dan server tidak bisa berbeda batas.
 */

export type TTextFieldType = 'short_text' | 'long_text';

export interface IFieldMaxLengthLimits {
    min: number;
    max: number;
}

export const FIELD_MAX_LENGTH_LIMITS: Record<TTextFieldType, IFieldMaxLengthLimits> = {
    short_text: { min: 1, max: 1000 },
    long_text: { min: 1, max: 10_000 },
};

/** Tipe teks lain (atau tidak dikenal) memakai batas teks pendek. */
export function fieldMaxLengthLimits(type: string): IFieldMaxLengthLimits {
    return type === 'long_text' ? FIELD_MAX_LENGTH_LIMITS.long_text : FIELD_MAX_LENGTH_LIMITS.short_text;
}

/** Buang semua karakter selain digit (paste "12a3" → "123"). */
export function stripNonDigits(raw: string): string {
    return raw.replace(/[^0-9]/g, '');
}

/** Format angka ribuan gaya Indonesia (1000 → "1.000"). */
export function formatMaxLengthNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

/** Teks bantuan: "Minimal 1, maksimal 1.000 karakter". */
export function maxLengthHint(limits: IFieldMaxLengthLimits): string {
    return `Minimal ${formatMaxLengthNumber(limits.min)}, maksimal ${formatMaxLengthNumber(limits.max)} karakter`;
}

/**
 * Angka `maxLength` dari input teks bebas: digit saja, di-clamp ke batas tipe.
 * `null` bila tidak ada digit sama sekali (pemanggil membiarkan nilai lama).
 */
export function sanitizeMaxLengthInput(raw: string, limits: IFieldMaxLengthLimits): number | null {
    const digits = stripNonDigits(raw);

    if (digits === '') return null;

    const parsed = Number.parseInt(digits, 10);

    if (!Number.isFinite(parsed)) return null;

    return Math.min(Math.max(parsed, limits.min), limits.max);
}

/** Nilai tersimpan di luar batas tipe (data lama) — ditandai, tidak di-clamp otomatis. */
export function isMaxLengthOutOfRange(value: number | null, limits: IFieldMaxLengthLimits): boolean {
    return value !== null && (value < limits.min || value > limits.max);
}

export interface IMaxLengthInputState {
    /** Teks yang ditampilkan di input (nilai mentah, tidak di-clamp). */
    text: string;
    /** Nilai numerik tersimpan, `null` bila kosong/tidak valid. */
    value: number | null;
    /** True bila nilai tersimpan melebihi batas tipe. */
    outOfRange: boolean;
    hint: string;
}

/** Baca `metadata.maxLength` untuk ditampilkan tanpa mengubahnya. */
export function readMaxLengthState(raw: unknown, limits: IFieldMaxLengthLimits): IMaxLengthInputState {
    const parsed = typeof raw === 'number' ? raw : Number.parseInt(stripNonDigits(String(raw ?? '')), 10);
    const value = Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : null;

    return {
        text: value === null ? '' : String(value),
        value,
        outOfRange: isMaxLengthOutOfRange(value, limits),
        hint: maxLengthHint(limits),
    };
}
