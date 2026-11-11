const RETRY_AFTERLESS_MESSAGE = 'Terlalu banyak percobaan. Coba lagi sebentar lagi.';

/**
 * Satu-satunya parser `Retry-After` untuk header HTTP maupun nilai programatik.
 *
 * Semantik yang dipilih mengikuti RFC 9110 bagian "delta-seconds":
 * - string harus berisi digit saja setelah trim; bentuk HTTP-date tidak didukung
 *   karena backend hanya mengirim delta-seconds;
 * - number harus integer >= 0;
 * - `0` adalah nilai sah (retry segera) dan dikembalikan apa adanya, sehingga
 *   pemanggil yang butuh cooldown positif tetap memakai fallback-nya sendiri;
 * - nilai kosong, negatif, pecahan, atau bukan angka menjadi `null`.
 */
export function parseRetryAfterSeconds(value: unknown): number | null {
    if (typeof value === 'number') {
        return Number.isInteger(value) && value >= 0 ? value : null;
    }

    if (typeof value !== 'string') return null;

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
