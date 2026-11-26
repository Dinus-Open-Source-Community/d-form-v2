/** Batas atas per bagian waktu 24 jam. */
const MAXIMUM_BY_PART = { hour: 23, minute: 59 } as const;

/**
 * Bersihkan satu bagian waktu (jam atau menit) dari ketikan bebas.
 *
 * Dua digit langsung dijepit ke rentang, jadi "25" tidak pernah menjadi jam.
 * `padSingleDigit` dipakai saat fokus lepas agar "5" menjadi "05".
 * String kosong dikembalikan apa adanya supaya kolom bisa dikosongkan.
 */
export function clampTimePart(part: 'hour' | 'minute', raw: string, padSingleDigit = false): string {
    const maximum = MAXIMUM_BY_PART[part];
    const digits = raw.replace(/\D/g, '').slice(0, 2);

    if (digits === '') return '';

    const bounded = String(Math.min(Number(digits), maximum));

    // Dua digit asli dipertahankan dua digit, supaya "00" tidak berubah menjadi "0".
    return padSingleDigit || digits.length === 2 ? bounded.padStart(2, '0') : bounded;
}
