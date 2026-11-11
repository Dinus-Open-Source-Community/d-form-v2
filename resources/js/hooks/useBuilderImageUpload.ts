import { computed, ref, type Ref } from 'vue';
import { uploadBuilderImage, type IBuilderImageUploadResult } from '@/adapters/http/forms';
import { isHttpRequestError, readPublicMessage } from '@/adapters/http/httpError';
import type { TValidationErrorBag } from '@/types/http';
import { cleanStoredImageUrl } from '@/lib/builderImageSanitizer';
import { validateUploadFile } from '@/lib/uploadValidation';

/**
 * Upload gambar builder (banner, blok image, opsi image) ke storage backend.
 *
 * Endpoint backend: `POST /admin/events/{event}/forms/{form}/images`
 * multipart field `file` → `{ ok, path, url }`.
 * (`resolveBuilderImageUploadUrl` di `@/adapters/http/forms` membangun URL-nya.)
 *
 * Aturan keras: DB tidak boleh menerima `data:` URL (base64). Alur di semua
 * komponen: preview lokal via object URL (`@/adapters/browser/objectUrl`) →
 * upload multipart → simpan `path`/`url` server ke metadata/bannerUrl → revoke.
 *
 * Hook ini hanya menyimpan state reaktif upload; sanitizer murni ada di
 * `@/lib/builderImageSanitizer`, seam browser di `@/adapters/browser/objectUrl`.
 */

/** Counter global: jumlah upload gambar builder yang sedang berjalan. */
export const builderPendingUploads = ref(0);

/** True bila ada upload gambar builder yang sedang berjalan. */
export const isBuilderUploading = computed(() => builderPendingUploads.value > 0);

export interface IUseBuilderImageUploadOptions {
    /** Bila kosong: preview lokal saja (tidak dipersist), tanpa request jaringan. */
    uploadUrl?: Ref<string | undefined> | (() => string | undefined) | string | undefined;
    /** Nama field multipart (default `file` mengikuti backend). */
    fieldName?: string;
    onError?: (message: string) => void;
}

/** Baca nilai `uploadUrl` dari string/ref/getter; kosong berarti tanpa request. */
function resolveUploadUrl(source: IUseBuilderImageUploadOptions['uploadUrl']): string {
    if (typeof source === 'function') {
        const value = source();

        return typeof value === 'string' ? value.trim() : '';
    }

    if (typeof source === 'string') return source.trim();

    return typeof source?.value === 'string' ? source.value.trim() : '';
}

/** Pesan validasi pertama dari bag error Laravel. */
function readFirstValidationMessage(errors: TValidationErrorBag | null): string {
    if (errors === null) return '';

    for (const entry of Object.values(errors)) {
        if (Array.isArray(entry)) {
            const first = entry.find((item: string): boolean => item.trim() !== '');
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
export function useBuilderImageUpload(opts: IUseBuilderImageUploadOptions = {}) {
    const uploading = ref(false);
    const error = ref('');

    async function uploadImage(file: File): Promise<IBuilderImageUploadResult | null> {
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
            const stored = cleanStoredImageUrl(uploadResult.url) !== '' ? uploadResult.url : uploadResult.path;

            if (stored.trim() === '') {
                throw new Error('Respons upload tidak membawa path/url gambar.');
            }

            return { path: uploadResult.path, url: stored };
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

export type { IBuilderImageUploadResult };
