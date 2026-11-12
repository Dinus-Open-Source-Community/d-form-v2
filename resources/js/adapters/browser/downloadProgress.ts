import { downloadUrl, saveBlobAsFile } from '@/adapters/browser/fileDownload';

/** Batas byte yang boleh ditahan di memori sebelum jatuh ke unduhan langsung. */
const DEFAULT_DOWNLOAD_MAX_BYTES = 50 * 1024 * 1024;

/** Batas tambahan saat `Content-Length` tidak ada (respons `streamDownload`). */
const INDETERMINATE_MAX_BYTES = 20 * 1024 * 1024;

/** Progres unduhan; `total` null berarti ukuran tidak diketahui (indeterminate). */
export interface IDownloadProgress {
    loaded: number;
    total: number | null;
}

/** `stream` = payload ditahan lalu disimpan; `direct` = browser mengunduh sendiri. */
export type TDownloadMode = 'stream' | 'direct';

export interface IDownloadWithProgressOptions {
    onProgress?: (progress: IDownloadProgress) => void;
    signal?: AbortSignal;
    /** Batas byte; default 50 MB. */
    maxBytes?: number;
    /** Nama cadangan bila `Content-Disposition` tidak ada; default segmen terakhir path. */
    fileName?: string;
}

/** `direct` tidak punya progres byte karena browser menangani unduhan. */
export type TDownloadWithProgressResult =
    { mode: 'stream'; bytes: number; fileName: string; indeterminate: boolean } | { mode: 'direct'; fileName: string };

/**
 * `Content-Length` byte; `null` bila tidak ada/tidak valid. Backend
 * `streamDownload` tidak mengirim header ini, sehingga progres indeterminate.
 */
function readContentLength(headers: Headers): number | null {
    const raw = headers.get('content-length');
    if (raw === null) return null;

    const value = Number(raw);

    return Number.isFinite(value) && value > 0 ? value : null;
}

/** Buang path separator/karakter kontrol dan segmen traversal dari nama file. */
function sanitizeFileName(value: string): string {
    const cleaned = value
        // Kontrol C0/DEL tidak pernah valid di nama file.
        .replace(/[\u0000-\u001f\u007f]/g, '')
        // Separator path (Unix/Windows) dan colon drive/ADS dibuang agar tidak
        // keluar dari folder unduhan.
        .replace(/[\\/:]/g, '')
        .replace(/^\.+/, '')
        .trim();

    return cleaned === '' || cleaned === '.' || cleaned === '..' ? '' : cleaned;
}

/** Nilai `filename*` RFC 5987 (`charset'lang'percent-encoded`). */
function decodeExtendedFileName(value: string): string {
    const raw = value.trim().replace(/^"(.*)"$/, '$1');
    const separator = raw.indexOf("'");
    const secondSeparator = separator === -1 ? -1 : raw.indexOf("'", separator + 1);
    const encoded = secondSeparator === -1 ? raw : raw.slice(secondSeparator + 1);

    try {
        return decodeURIComponent(encoded);
    } catch {
        return encoded;
    }
}

/**
 * Nama file dari `Content-Disposition`. Prioritas: `filename*` (RFC 5987,
 * percent-decoded), lalu `filename` quoted/unquoted. `null` bila tidak ada.
 */
function readContentDispositionFileName(header: string | null): string | null {
    if (header === null || header.trim() === '') return null;

    const extended = /filename\*\s*=\s*([^;]+)/i.exec(header);
    if (extended?.[1] !== undefined) {
        const decoded = sanitizeFileName(decodeExtendedFileName(extended[1]));
        if (decoded !== '') return decoded;
    }

    const plain = /filename\s*=\s*(?:"([^"]*)"|([^;]+))/i.exec(header);
    const plainValue = plain?.[1] ?? plain?.[2];
    if (plainValue !== undefined) {
        const decoded = sanitizeFileName(plainValue);
        if (decoded !== '') return decoded;
    }

    return null;
}

/** Nama file: eksplisit, atau segmen terakhir path (query diabaikan). */
function resolveUrlFileName(url: string, explicit?: string): string {
    if (explicit !== undefined && explicit.trim() !== '') return sanitizeFileName(explicit.trim());

    const path = url.split('?')[0] ?? '';
    const segments = path.split('/');
    const last = segments[segments.length - 1] ?? '';

    try {
        return sanitizeFileName(decodeURIComponent(last));
    } catch {
        return sanitizeFileName(last);
    }
}

/** AbortError yang konsisten dengan `AbortController` untuk pemanggil hook. */
function createAbortError(): DOMException {
    return new DOMException('Unduhan dibatalkan.', 'AbortError');
}

function isAbortError(error: unknown): boolean {
    return error instanceof Error && error.name === 'AbortError';
}

/** Cek abort lewat fungsi agar narrowing TS tidak terbawa melewati `await`. */
function isSignalAborted(signal?: AbortSignal): boolean {
    return signal?.aborted === true;
}

/** Unduh tanpa menahan payload; `false` bila browser tidak mendukung anchor. */
function downloadDirect(url: string, fileName: string): TDownloadWithProgressResult {
    if (!downloadUrl(url, fileName)) throw new Error('Unduhan langsung tidak didukung browser ini.');

    return { mode: 'direct', fileName };
}

/**
 * Unduh file dengan progres byte dan batas memori.
 *
 * Nama file diambil dari `Content-Disposition` respons (header `streamDownload`),
 * sehingga hasil simpan memakai nama + ekstensi dari server. Fallback: opsi
 * `fileName`, lalu segmen terakhir URL. Nama disanitasi dari path separator,
 * karakter kontrol, dan segmen traversal.
 *
 * Aturan progres:
 * - `Content-Length` ada dan ≤ `maxBytes` → stream dengan `total` angka.
 * - `Content-Length` ada dan > `maxBytes` → `direct` segera, tanpa buffering.
 * - `Content-Length` tidak ada → stream indeterminate (`total: null`) dengan
 *   batas `min(maxBytes, 20 MB)`; melewati batas membatalkan fetch, membuang
 *   chunk, lalu `direct`.
 *
 * `direct` memakai `<a download>` dengan nama yang sama. Stream menyimpan Blob
 * hanya setelah selesai, me-revoke object URL tepat setelah klik, dan selalu
 * melepas reader (termasuk saat batal).
 */
export async function downloadWithProgress(
    url: string,
    options: IDownloadWithProgressOptions = {}
): Promise<TDownloadWithProgressResult> {
    const maxBytes = options.maxBytes ?? DEFAULT_DOWNLOAD_MAX_BYTES;
    const controller = new AbortController();

    if (isSignalAborted(options.signal)) throw createAbortError();

    const abortFromCaller = (): void => controller.abort();
    options.signal?.addEventListener('abort', abortFromCaller, { once: true });

    try {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (isSignalAborted(options.signal)) throw createAbortError();
        if (!response.ok) throw new Error(`Unduhan gagal (kode ${response.status}).`);

        const fileName =
            readContentDispositionFileName(response.headers.get('content-disposition')) ??
            resolveUrlFileName(url, options.fileName);

        const body = response.body;
        if (body === null) return downloadDirect(url, fileName);

        const declaredLength = readContentLength(response.headers);
        if (declaredLength !== null && declaredLength > maxBytes) {
            controller.abort();
            return downloadDirect(url, fileName);
        }

        const limit = declaredLength === null ? Math.min(maxBytes, INDETERMINATE_MAX_BYTES) : maxBytes;
        const reader = body.getReader();
        const chunks: BlobPart[] = [];
        let loaded = 0;
        let overflowed = false;

        try {
            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;
                if (value === undefined) continue;

                loaded += value.byteLength;
                if (loaded > limit) {
                    overflowed = true;
                    await reader.cancel().catch((): void => undefined);
                    break;
                }

                chunks.push(value);
                options.onProgress?.({ loaded, total: declaredLength });
            }
        } catch (error) {
            if (isAbortError(error)) {
                controller.abort();
                await reader.cancel().catch((): void => undefined);
                throw createAbortError();
            }
            throw error;
        } finally {
            reader.releaseLock();
        }

        if (overflowed) {
            // Jangan tahan payload yang melewati batas: buang chunk lalu unduh langsung.
            controller.abort();
            chunks.length = 0;
            return downloadDirect(url, fileName);
        }

        const contentType = response.headers.get('content-type') ?? 'application/octet-stream';
        const blob = new Blob(chunks, { type: contentType });

        if (!saveBlobAsFile(blob, fileName, { revokeDelayMs: 0 })) {
            throw new Error('Unduhan tidak didukung browser ini.');
        }

        return { mode: 'stream', bytes: loaded, fileName, indeterminate: declaredLength === null };
    } catch (error) {
        if (isAbortError(error)) throw createAbortError();
        throw error;
    } finally {
        options.signal?.removeEventListener('abort', abortFromCaller);
    }
}
