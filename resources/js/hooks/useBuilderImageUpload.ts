import { computed, ref, type Ref } from 'vue';
import { uploadBuilderImage } from '@/adapters/http/forms';
import { isHttpRequestError, readPublicMessage } from '@/adapters/http/httpError';
import type { TValidationErrorBag } from '@/types/http';
import { validateUploadFile } from '@/lib/uploadValidation';

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

/** Pesan validasi pertama dari bag error Laravel. */
function readFirstValidationMessage(errors: TValidationErrorBag | null): string {
    if (errors === null) return '';
    for (const entry of Object.values(errors)) {
        if (Array.isArray(entry)) {
            const first = entry.find((item) => item.trim() !== '');
            if (first !== undefined) return first.trim();
            continue;
        }
        if (entry.trim() !== '') return entry.trim();
    }
    return '';
}

/** Pesan error upload dari kontrak `HttpRequestError` (validasi 422, 413, transport). */
function readUploadErrorMessage(error: unknown): string {
    if (!isHttpRequestError(error)) {
        return error instanceof Error && error.message.trim() !== '' ? error.message : 'Gagal mengunggah gambar.';
    }
    const bodyMessage = readPublicMessage(error.body);
    if (bodyMessage !== null) return bodyMessage;
    const validationMessage = readFirstValidationMessage(error.validationErrors);
    if (validationMessage !== '') return validationMessage;
    if (error.kind === 'invalid_response') return 'Respons upload tidak membawa path/url gambar.';
    if (error.status === 413) return 'Ukuran gambar terlalu besar.';
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
        const validationError = await validateUploadFile(file, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        if (validationError !== null) {
            error.value = validationError;
            (opts.onError ?? (() => {}))(validationError);
            return null;
        }
        const url = resolveUploadUrl(opts.uploadUrl);
        if (url === '') {
            return null;
        }
        error.value = '';
        uploading.value = true;
        builderPendingUploads.value += 1;
        try {
            const uploadResult = await uploadBuilderImage(url, file, opts.fieldName?.trim() || 'file');
            const path = uploadResult.path;
            const remoteUrl = uploadResult.url;
            const stored = cleanStoredImageUrl(remoteUrl) !== '' ? remoteUrl : path;
            if (stored.trim() === '') {
                throw new Error('Respons upload tidak membawa path/url gambar.');
            }
            return { path, url: stored };
        } catch (err) {
            const message = readUploadErrorMessage(err);
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
