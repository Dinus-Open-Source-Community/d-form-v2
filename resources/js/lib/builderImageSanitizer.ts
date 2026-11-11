import { isPersistableImageUrl } from '@/lib/urlPolicy';

/**
 * Sanitasi URL gambar builder: DB tidak boleh menerima `data:`/`blob:` URL.
 * Semua fungsi murni (tanpa DOM/network) agar bisa dipakai mapper, hook autosave,
 * dan unit test tanpa browser.
 */

/** Skema URL lokal sementara yang tidak boleh dipersist. */
const LOCAL_IMAGE_SCHEMES: ReadonlyArray<string> = ['data:', 'blob:'];

/** True bila string diawali skema `data:` (base64) — tidak boleh dipersist ke DB. */
export function isDataUrl(value: unknown): boolean {
    return typeof value === 'string' && value.trimStart().startsWith('data:');
}

/** True bila string diawali skema `blob:` (object URL lokal sementara). */
export function isBlobUrl(value: unknown): boolean {
    return typeof value === 'string' && value.trimStart().startsWith('blob:');
}

/** True bila string adalah URL lokal sementara yang tidak boleh dipersist ke DB. */
export function isLocalOnlyImageUrl(value: unknown): boolean {
    if (typeof value !== 'string') return false;

    const trimmed = value.trimStart();

    return LOCAL_IMAGE_SCHEMES.some((scheme: string): boolean => trimmed.startsWith(scheme));
}

/**
 * Nilai imageUrl yang aman disimpan ke metadata/bannerUrl.
 * `data:`/`blob:` URL → string kosong (tidak dipersist); sisanya di-trim.
 */
export function cleanStoredImageUrl(value: unknown): string {
    if (typeof value !== 'string') return '';

    const trimmed = value.trim();

    return isPersistableImageUrl(trimmed) ? trimmed : '';
}

/**
 * Rekursif: ganti setiap string `data:`/`blob:` URL menjadi `''`.
 * Nilai non-JSON (fungsi, `undefined`, `Date`) diteruskan apa adanya.
 */
export function stripLocalImageUrlsDeep(value: unknown): unknown {
    if (typeof value === 'string') return isLocalOnlyImageUrl(value) ? '' : value;

    if (Array.isArray(value)) return value.map((entry: unknown): unknown => stripLocalImageUrlsDeep(entry));

    if (value !== null && typeof value === 'object') {
        const entries = Object.entries(value).map(([key, entry]: [string, unknown]): [string, unknown] => [
            key,
            stripLocalImageUrlsDeep(entry),
        ]);

        return Object.fromEntries(entries);
    }

    return value;
}

/**
 * Sanitasi snapshot autosave (JSON string) — buang `data:`/`blob:` URL agar
 * payload ke DB tidak pernah membawa base64. Gagal parse → kembalikan asal.
 */
export function sanitizeBuilderSnapshot(snapshot: string): string {
    try {
        const parsed: unknown = JSON.parse(snapshot);

        return JSON.stringify(stripLocalImageUrlsDeep(parsed));
    } catch {
        return snapshot;
    }
}
