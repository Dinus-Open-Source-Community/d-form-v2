import { isRecord } from '@/adapters/http/httpError';
import { requestJson } from '@/adapters/http/httpClient';
import { createPercentProgressReporter } from '@/adapters/http/upload';
import { isPersistableImageUrl } from '@/lib/urlPolicy';
import type { IUploadTransferOptions } from '@/types/http';

/**
 * Upload gambar inline broadcast. Mengembalikan URL yang siap ditampilkan dan
 * menolak URL data/blob agar editor tidak menyimpan gambar sementara.
 */
export async function uploadBroadcastImage(
    url: string,
    file: File,
    fieldName = 'file',
    options?: IUploadTransferOptions
): Promise<string> {
    const formData = new FormData();
    formData.append(fieldName, file);

    return requestJson(
        {
            url,
            method: 'POST',
            formData,
            signal: options?.signal,
            onProgress: createPercentProgressReporter(options?.onProgress),
        },
        (payload) => {
            if (!isRecord(payload) || typeof payload.url !== 'string') return null;

            const uploadedUrl = payload.url.trim();

            return isPersistableImageUrl(uploadedUrl) ? uploadedUrl : null;
        }
    );
}
