import type { THttpErrorKind } from '@/types/http';
import { formatRetryAfterMessage } from '@/lib/http-retry-after';

export interface IRequestErrorTextInput {
    kind: THttpErrorKind;
    status: number | null;
    retryAfterSeconds?: number | null;
}

/** Satu sumber pesan publik per jenis kegagalan; `aborted` sengaja `null` (senyap). */
const REQUEST_ERROR_MESSAGES: Record<THttpErrorKind, string | null> = {
    aborted: null,
    network: 'Gagal terhubung ke server. Periksa koneksi internet, lalu coba lagi.',
    timeout: 'Server tidak merespons tepat waktu. Coba lagi.',
    validation: 'Data yang dikirim tidak valid. Periksa kembali formulir.',
    unauthorized: 'Sesi Anda sudah berakhir. Muat ulang halaman lalu masuk kembali.',
    forbidden: 'Anda tidak memiliki izin untuk tindakan ini.',
    not_found: 'Data tidak ditemukan.',
    conflict: 'Data bertentangan dengan kondisi saat ini. Muat ulang lalu coba lagi.',
    rate_limited: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
    server: 'Terjadi kesalahan server. Coba lagi beberapa saat lagi.',
    invalid_response: 'Server mengirim respons yang tidak valid. Coba lagi beberapa saat lagi.',
    client: 'Permintaan gagal diproses. Coba lagi.',
    unknown: 'Permintaan gagal diproses. Coba lagi.',
};

/** Pesan publik per jenis kegagalan; null = sengaja senyap (aborted). */
export function describeRequestError(error: IRequestErrorTextInput): string | null {
    if (error.kind === 'rate_limited' && error.retryAfterSeconds != null) {
        return formatRetryAfterMessage(error.retryAfterSeconds);
    }

    return REQUEST_ERROR_MESSAGES[error.kind];
}
