/**
 * Type guard URL HTTP(S) valid. Dipakai sebagai gerbang sebelum menautkan nilai tak tepercaya,
 * sehingga skema `javascript:`/`data:` otomatis tertolak.
 */
export function isHttpUrl(value: unknown): value is string {
    if (typeof value !== 'string') return false;

    const trimmed: string = value.trim();
    if (trimmed === '') return false;

    let parsed: URL;
    try {
        parsed = new URL(trimmed);
    } catch {
        return false;
    }

    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
}
