/**
 * Parse kategori event dari prop Inertia (array atau string berkoma) menjadi daftar
 * trim non-kosong; nilai lain → daftar kosong.
 */
export function parseEventCategories(raw: unknown): string[] {
    if (Array.isArray(raw)) return raw.map((s) => String(s).trim()).filter(Boolean);
    if (typeof raw === 'string')
        return raw
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
    return [];
}
