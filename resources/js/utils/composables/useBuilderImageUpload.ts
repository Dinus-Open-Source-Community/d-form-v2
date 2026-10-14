import { computed, ref, type Ref } from 'vue';
import axios from 'axios';

/**
 * Upload gambar builder (banner, blok image, opsi image) ke storage backend.
 *
 * Endpoint backend: `POST /admin/events/{event}/forms/{form}/images`
 * multipart field `file` → `{ ok, path, url }`.
 * (`resolveBuilderImageUploadUrl` membangun URL-nya; bila backend berubah,
 * cukup arahkan `uploadUrl` / konstanta tersebut.)
 *
 * Aturan keras: DB tidak boleh menerima `data:` URL (base64). Alur di semua
 * komponen: preview lokal via `URL.createObjectURL` → upload multipart →
 * simpan `path`/`url` server ke metadata/bannerUrl → revoke object URL.
 * `blob:` URL yang belum ter-upload ikut dibersihkan oleh sanitizer sebelum save.
 */

/** Counter global: jumlah upload gambar builder yang sedang berjalan. */
export const builderPendingUploads = ref(0);

/** True bila ada upload gambar builder yang sedang berjalan. */
export const isBuilderUploading = computed(() => builderPendingUploads.value > 0);

export interface BuilderImageUploadResult {
    path: string;
    url: string;
}

/** Default URL endpoint upload bila backend mengikuti konvensi `{form}/images`. */
export function resolveBuilderImageUploadUrl(eventId: string | number, formId: string | number): string {
    return `/admin/events/${eventId}/forms/${formId}/images`;
}

function readXsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

export function isDataUrl(value: unknown): boolean {
    return typeof value === 'string' && value.trimStart().startsWith('data:');
}

export function isBlobUrl(value: unknown): boolean {
    return typeof value === 'string' && value.trimStart().startsWith('blob:');
}

/** True bila string adalah URL lokal sementara yang tidak boleh dipersist ke DB. */
export function isLocalOnlyImageUrl(value: unknown): boolean {
    return isDataUrl(value) || isBlobUrl(value);
}

/**
 * Nilai imageUrl yang aman disimpan ke metadata/bannerUrl.
 * `data:`/`blob:` URL → string kosong (tidak dipersist); sisanya di-trim.
 */
export function cleanStoredImageUrl(value: unknown): string {
    if (typeof value !== 'string') return '';
    const trimmed = value.trim();
    if (trimmed === '' || trimmed.startsWith('data:') || trimmed.startsWith('blob:')) return '';
    return trimmed;
}

/** Rekursif: ganti setiap string `data:`/`blob:` URL menjadi `''`. */
export function stripLocalImageUrlsDeep<T>(value: T): T {
    if (typeof value === 'string') {
        const trimmed = value.trimStart();
        if (trimmed.startsWith('data:') || trimmed.startsWith('blob:')) return '' as T;
        return value;
    }
    if (Array.isArray(value)) {
        return value.map((item) => stripLocalImageUrlsDeep(item)) as T;
    }
    if (value && typeof value === 'object') {
        const out: Record<string, unknown> = {};
        for (const [key, entry] of Object.entries(value as Record<string, unknown>)) {
            out[key] = stripLocalImageUrlsDeep(entry);
        }
        return out as T;
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

/** Preview lokal instan untuk <img>; wajib di-revoke setelah upload selesai. */
export function createLocalImagePreviewUrl(file: File): string {
    return URL.createObjectURL(file);
}

export function revokeLocalImagePreviewUrl(url: string | null | undefined): void {
    if (typeof url === 'string' && url.startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
}

export interface UseBuilderImageUploadOptions {
    /** Bila kosong: preview lokal saja (tidak dipersist), tanpa request jaringan. */
    uploadUrl?: Ref<string | undefined> | (() => string | undefined) | string | undefined;
    /** Nama field multipart (default `file` mengikuti backend). */
    fieldName?: string;
    onError?: (message: string) => void;
}

function resolveUploadUrl(source: UseBuilderImageUploadOptions['uploadUrl']): string {
    if (typeof source === 'function') {
        const value = (source as () => string | undefined)();
        return typeof value === 'string' ? value.trim() : '';
    }
    if (typeof source === 'string') return source.trim();
    if (source && typeof (source as Ref<string | undefined>).value === 'string') {
        return ((source as Ref<string | undefined>).value as string).trim();
    }
    return '';
}

/** Pesan error API (validasi 422 dsb.) → string ramah untuk toast/inline. */
function readApiErrorMessage(err: unknown): string {
    if (err && typeof err === 'object') {
        const response = (err as { response?: { data?: unknown; status?: number } }).response;
        const data = response?.data;
        if (data && typeof data === 'object') {
            const bag = data as Record<string, unknown>;
            if (typeof bag.message === 'string' && bag.message.trim() !== '') return bag.message.trim();
            const errors = bag.errors;
            if (errors && typeof errors === 'object') {
                for (const entry of Object.values(errors as Record<string, unknown>)) {
                    if (Array.isArray(entry) && typeof entry[0] === 'string') return entry[0];
                    if (typeof entry === 'string' && entry.trim() !== '') return entry;
                }
            }
        }
        if (response?.status === 413) return 'Ukuran gambar terlalu besar.';
    }
    if (err instanceof Error && err.message.trim() !== '') return err.message;
    return 'Gagal mengunggah gambar.';
}

/**
 * Upload satu file gambar ke endpoint `{form}/images`.
 * Menambah `builderPendingUploads` selama request agar autosave bisa skip/menunggu.
 */
export function useBuilderImageUpload(opts: UseBuilderImageUploadOptions = {}) {
    const uploading = ref(false);
    const error = ref('');

    async function uploadImage(file: File): Promise<BuilderImageUploadResult | null> {
        const url = resolveUploadUrl(opts.uploadUrl);
        if (url === '') {
            return null;
        }
        error.value = '';
        uploading.value = true;
        builderPendingUploads.value += 1;
        try {
            const payload = new FormData();
            payload.append(opts.fieldName?.trim() || 'file', file);
            const token = readXsrfToken();
            const res = await axios.post(url, payload, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-XSRF-TOKEN': token } : {}),
                },
            });
            const body = (res.data ?? {}) as { path?: unknown; url?: unknown; ok?: unknown };
            const path = typeof body.path === 'string' ? body.path : '';
            const remoteUrl = typeof body.url === 'string' ? body.url : '';
            const stored = cleanStoredImageUrl(remoteUrl) !== '' ? remoteUrl : path;
            if (stored.trim() === '') {
                throw new Error('Respons upload tidak membawa path/url gambar.');
            }
            return { path, url: stored };
        } catch (err) {
            const message = readApiErrorMessage(err);
            error.value = message;
            (opts.onError ?? (() => {}))(message);
            return null;
        } finally {
            uploading.value = false;
            builderPendingUploads.value = Math.max(0, builderPendingUploads.value - 1);
        }
    }

    return { uploading, error, uploadImage };
}
