const RETRY_AFTERLESS_MESSAGE = 'Terlalu banyak percobaan. Coba lagi sebentar lagi.';

/**
 * Ubah header `Retry-After` (detik) menjadi angka bulat; null bila kosong, negatif, atau bukan angka.
 */
export function parseRetryAfterSeconds(value: string | null | undefined): number | null {
    if (value == null) return null;

    const trimmed: string = value.trim();
    if (!/^\d+$/.test(trimmed)) return null;

    const seconds: number = Number.parseInt(trimmed, 10);
    return Number.isFinite(seconds) ? seconds : null;
}

/**
 * Susun pesan 429 berbahasa Indonesia; hitung mundur hanya muncul bila detik > 0.
 */
export function formatRetryAfterMessage(seconds: number): string {
    if (!Number.isFinite(seconds) || seconds <= 0) return RETRY_AFTERLESS_MESSAGE;

    return `Terlalu banyak percobaan. Coba lagi dalam ${Math.ceil(seconds)} detik.`;
}
