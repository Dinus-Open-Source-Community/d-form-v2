import { requestJson } from '@/adapters/http/httpClient';
import type { IHttpRequestOptions } from '@/adapters/http/httpClient';
import type { THttpMethod, ITransferProgress } from '@/types/http';

/** Opsi upload multipart/JSON dengan progres dan pembatalan. */
export interface IUploadWithProgressOptions {
    url: string;
    method?: THttpMethod;
    /** Body JSON untuk endpoint non-file. */
    body?: unknown;
    /** FormData yang sudah diisi pemanggil; file tidak pernah dibaca JS. */
    formData?: FormData;
    signal?: AbortSignal;
    onProgress?: (progress: ITransferProgress) => void;
}

/** Parser respons wajib: `null` berarti respons tidak sesuai kontrak. */
export type TUploadResponseParser<T> = (payload: unknown) => T | null;

/**
 * Upload dengan progres byte di atas axios client yang sama (cookie + CSRF
 * bawaan). FormData diteruskan apa adanya sehingga browser yang men-stream file;
 * helper ini tidak membaca/menyalin isi file ke memori JS.
 */
export async function uploadWithProgress<T>(
    options: IUploadWithProgressOptions,
    parse: TUploadResponseParser<T>
): Promise<T> {
    const request: IHttpRequestOptions = {
        url: options.url,
        method: options.method ?? 'POST',
        body: options.body,
        formData: options.formData,
        signal: options.signal,
        onProgress: options.onProgress,
    };

    return requestJson(request, parse);
}

/**
 * Ubah progres byte menjadi persen untuk `TUploader`/hook. `undefined` bila
 * pemanggil tidak meminta progres, sehingga jalur lama tidak berubah.
 */
export function createPercentProgressReporter(
    onProgress?: (percent: number) => void
): ((progress: ITransferProgress) => void) | undefined {
    if (onProgress === undefined) return undefined;

    return (progress: ITransferProgress): void => onProgress(progress.percent);
}
