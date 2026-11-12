/** Cek record non-array untuk payload JSON dinamis yang belum bertipe. */
export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
