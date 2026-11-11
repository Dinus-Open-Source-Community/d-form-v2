import type { IFormFieldMetadata, TFormFieldMetadataBag, TFormFieldRules } from '@/types/form';
import { isRecord } from '@/lib/isRecord';

/**
 * Baca flag boolean dari metadata API/Laravel tanpa jebakan `Boolean("false") === true`.
 */
export function readMetaBoolean(meta: TFormFieldMetadataBag, key: string): boolean {
    const v = meta[key];
    if (v === true || v === 1) return true;
    if (v === false || v === 0 || v === null || v === undefined) return false;
    if (typeof v === 'string') {
        const s = v.trim().toLowerCase();
        if (s === '' || s === 'false' || s === '0' || s === 'no' || s === 'off') return false;
        if (s === 'true' || s === '1' || s === 'yes' || s === 'on') return true;
    }
    return Boolean(v);
}

/**
 * Normalisasi nilai metadata mentah menjadi objek: string JSON di-parse (gagal → dibiarkan),
 * array → objek pertama, bentuk lain → `{}`.
 */
export function readMetadataBag(value: unknown): TFormFieldMetadataBag {
    let m: unknown = value;
    if (typeof m === 'string' && m.trim()) {
        try {
            m = JSON.parse(m);
        } catch {
            /* not valid JSON, keep as-is */
        }
    }
    if (Array.isArray(m)) {
        m = m.find((item) => isRecord(item)) ?? {};
    }
    return isRecord(m) ? m : {};
}

/** Normalisasi `field.metadata` menjadi objek (lihat `readMetadataBag`). */
export function readFieldMetadata(field: IFormField): TFormFieldMetadataBag {
    return readMetadataBag(field.metadata);
}

/** Type guard seam Inertia: `metadata.rules` harus record JSON agar aman dibaca sebagai rules. */
export function isFormFieldRules(value: unknown): value is TFormFieldRules {
    return isRecord(value);
}

/** Ambil `metadata.rules` dari bag; nilai non-record jatuh ke objek kosong. */
export function readRuleSet(metadata: TFormFieldMetadataBag): TFormFieldRules {
    const raw = metadata.rules;
    return isFormFieldRules(raw) ? raw : {};
}

/** Ambil `metadata.rules` sebuah field bila berbentuk objek; selain itu `{}`. */
export function readFieldRules(field: IFormField): TFormFieldRules {
    return readRuleSet(readFieldMetadata(field));
}

/** Nilai string dari metadata; tipe lain (payload malformed) jatuh ke `fallback`. */
export function readMetaText(metadata: TFormFieldMetadataBag, key: keyof IFormFieldMetadata, fallback = ''): string {
    const value = metadata[key];
    return typeof value === 'string' ? value : fallback;
}

/** Nilai number finite dari metadata; tipe lain (payload malformed) jatuh ke `fallback`. */
export function readMetaNumber(metadata: TFormFieldMetadataBag, key: keyof IFormFieldMetadata, fallback = 0): number {
    const value = metadata[key];
    return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}
