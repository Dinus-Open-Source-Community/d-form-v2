/**
 * Sumber tunggal pola input ketat untuk field formulir.
 *
 * Nilai yang tidak cocok tidak pernah masuk ke model: pemanggil memakai `sanitizeFieldInput`
 * pada event input, lalu `isValidFieldInput` untuk menampilkan pesan. Backend memakai aturan
 * padanan di `App\Support\FieldInputPatterns`.
 */

export type TFieldInputSubtype = 'number' | 'tel' | 'email' | 'nik' | 'nim' | 'postal_code';

export interface IFieldInputPattern {
    inputMode: 'numeric' | 'tel' | 'email' | 'text';
    /** Diuji setelah sanitasi, sehingga separator dan spasi tidak ikut menggagalkan nilai sah. */
    pattern: RegExp;
    maxLength?: number;
    message: string;
    sanitize: (value: string) => string;
}

const DIGITS_ONLY = /[^\d]/g;

/** Hanya digit dan satu tanda plus di awal, supaya nomor luar negeri tetap bisa diketik. */
function sanitizeTelephone(value: string): string {
    const hasPlusPrefix = value.trimStart().startsWith('+');
    const digits = value.replace(DIGITS_ONLY, '');

    return hasPlusPrefix ? `+${digits}` : digits;
}

export const FIELD_INPUT_PATTERNS: Record<TFieldInputSubtype, IFieldInputPattern> = {
    number: {
        inputMode: 'numeric',
        pattern: /^\d+$/,
        message: 'Hanya angka yang boleh diisi.',
        sanitize: (value: string): string => value.replace(DIGITS_ONLY, ''),
    },
    tel: {
        inputMode: 'tel',
        pattern: /^\+?\d{9,15}$/,
        message: 'Nomor WhatsApp hanya boleh berisi angka, 9 sampai 15 digit.',
        sanitize: sanitizeTelephone,
    },
    email: {
        inputMode: 'email',
        pattern: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
        message: 'Format email belum benar, contoh: nama@domain.com.',
        sanitize: (value: string): string => value.trim(),
    },
    nik: {
        inputMode: 'numeric',
        pattern: /^\d{16}$/,
        maxLength: 16,
        message: 'NIK harus 16 angka.',
        sanitize: (value: string): string => value.replace(DIGITS_ONLY, ''),
    },
    nim: {
        inputMode: 'numeric',
        pattern: /^\d{5,20}$/,
        maxLength: 20,
        message: 'NIM harus berupa angka, 5 sampai 20 digit.',
        sanitize: (value: string): string => value.replace(DIGITS_ONLY, ''),
    },
    postal_code: {
        inputMode: 'numeric',
        pattern: /^\d{5}$/,
        maxLength: 5,
        message: 'Kode pos harus 5 angka.',
        sanitize: (value: string): string => value.replace(DIGITS_ONLY, ''),
    },
};

const SUBTYPES: readonly string[] = Object.keys(FIELD_INPUT_PATTERNS);

/** Guard untuk metadata yang datang dari server dan belum tentu salah satu subtype ketat. */
export function isFieldInputSubtype(value: string): value is TFieldInputSubtype {
    return SUBTYPES.includes(value);
}

export function sanitizeFieldInput(subtype: TFieldInputSubtype, value: string): string {
    const sanitized = FIELD_INPUT_PATTERNS[subtype].sanitize(value);
    const limit = FIELD_INPUT_PATTERNS[subtype].maxLength;

    return limit === undefined ? sanitized : sanitized.slice(0, limit);
}

/** Nilai kosong dianggap sah di sini karena kewajiban isi ditangani aturan required. */
export function isValidFieldInput(subtype: TFieldInputSubtype, value: string): boolean {
    if (value.trim() === '') return true;

    return FIELD_INPUT_PATTERNS[subtype].pattern.test(value);
}
