/**
 * Pemilihan kolom tabel jawaban.
 *
 * Empat kolom inti (pilih, nomor, pengirim, status, aksi) selalu tampil. Kolom field
 * dibatasi agar tabel tetap terbaca walau form punya ratusan field.
 */

export const ANSWER_COLUMN_LIMIT = 6;

/** Pilih maksimal `limit` kolom dari daftar yang tersedia; default ke kolom pertama. */
export function normalizeAnswerColumnSelection(
    stored: string | null,
    available: readonly string[],
    limit: number = ANSWER_COLUMN_LIMIT
): string[] {
    let parsed: unknown = null;

    if (stored !== null) {
        try {
            parsed = JSON.parse(stored);
        } catch {
            parsed = null;
        }
    }

    const picked = Array.isArray(parsed)
        ? parsed.filter((key): key is string => typeof key === 'string' && available.includes(key))
        : [];

    const unique = [...new Set(picked)];

    return unique.length > 0 ? unique.slice(0, limit) : available.slice(0, limit);
}

/** Tambah atau buang satu kolom; penambahan diabaikan saat batas sudah tercapai. */
export function toggleAnswerColumnSelection(
    current: readonly string[],
    key: string,
    limit: number = ANSWER_COLUMN_LIMIT
): string[] {
    if (current.includes(key)) return current.filter((entry) => entry !== key);
    if (current.length >= limit) return [...current];

    return [...current, key];
}
