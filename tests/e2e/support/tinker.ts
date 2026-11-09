/**
 * Parser murni untuk output `artisan tinker --execute`. Tinker mencetak JSON
 * mentah; helper ini menjaga spec tetap bebas `any`/cast tanpa guard.
 */

/** Parse stdout tinker sebagai objek JSON; `label` dipakai pada pesan error. */
export function parseTinkerJsonRecord(raw: string, label: string): Record<string, unknown> {
    let parsed: unknown;

    try {
        parsed = JSON.parse(raw);
    } catch (error: unknown) {
        const reason = error instanceof Error ? error.message : String(error);

        throw new Error(`${label}: stdout tinker bukan JSON (${reason}): ${raw.slice(0, 200)}`);
    }

    if (!isTinkerRecord(parsed)) {
        throw new Error(`${label}: stdout tinker bukan objek JSON: ${raw.slice(0, 200)}`);
    }

    return parsed;
}

/** Guard objek non-array. */
export function isTinkerRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Ambil field string tidak kosong dari record tinker. */
export function requireTinkerString(record: Record<string, unknown>, field: string, label: string): string {
    const value = record[field];

    if (typeof value !== 'string' || value === '') {
        throw new Error(`${label}: field "${field}" harus string tidak kosong.`);
    }

    return value;
}

/** Ambil field angka dari record tinker. */
export function requireTinkerNumber(record: Record<string, unknown>, field: string, label: string): number {
    const value = record[field];

    if (typeof value !== 'number' || Number.isNaN(value)) {
        throw new Error(`${label}: field "${field}" harus angka.`);
    }

    return value;
}
