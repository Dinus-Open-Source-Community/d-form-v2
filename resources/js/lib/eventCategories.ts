/**
 * Normalisasi kategori event dari array atau string berkoma menjadi daftar trim non-kosong.
 * Nilai di luar kedua bentuk itu dianggap kosong.
 */
export function toCategoryList(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map((v) => String(v).trim()).filter(Boolean);
    }
    if (typeof value === 'string') {
        return value
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
    }
    return [];
}

/** Kategori pertama hasil {@link toCategoryList}; string kosong bila tidak ada. */
export function primaryCategory(value: unknown): string {
    return toCategoryList(value)[0] ?? '';
}
