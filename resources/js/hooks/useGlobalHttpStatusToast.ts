import { router } from '@inertiajs/vue3';
import { AxiosHeaders } from 'axios';
import type { AxiosResponse } from 'axios';
import { formatRetryAfterMessage, parseRetryAfterSeconds } from '@/lib/http-retry-after';

const SERVER_ERROR_MESSAGE =
    'Terjadi kesalahan server saat memuat halaman. Coba refresh; jika tetap terjadi, ini perlu diperbaiki di server.';
const SESSION_EXPIRED_MESSAGE = 'Sesi tidak valid atau sudah habis. Muat ulang halaman lalu coba lagi.';

let registered = false;

type TInvalidEvent = CustomEvent<{ response: AxiosResponse<unknown> }>;

/** Ambil header `Retry-After` dari respons Axios; null bila header tidak ada. */
function readRetryAfterHeader(response: AxiosResponse<unknown>): string | null {
    const headers: unknown = response.headers;
    if (!(headers instanceof AxiosHeaders)) return null;

    const value: unknown = headers.get('Retry-After');
    return typeof value === 'string' ? value : null;
}

/** Muat modul pesan saat error terjadi saja, supaya tabel pesan tidak ikut entry bundle. */
async function showStatusToast(status: number, message: string | null): Promise<void> {
    const { showErrorToast, showHttpErrorToast } = await import('@/lib/error-message');

    if (message === null) {
        showHttpErrorToast(status);
        return;
    }

    showErrorToast(message);
}

/** Status yang punya toast khusus; status lain dibiarkan ke perilaku bawaan Inertia. */
function isHandledStatus(status: number): boolean {
    return status >= 500 || status === 419 || status === 429;
}

/** Pilih pesan sesuai status; null berarti 429 tanpa header sehingga memakai pesan default. */
function resolveStatusMessage(status: number, retryAfter: string | null): string | null {
    if (status === 429) {
        const seconds: number | null = parseRetryAfterSeconds(retryAfter);
        return seconds === null ? null : formatRetryAfterMessage(seconds);
    }

    if (status === 419) return SESSION_EXPIRED_MESSAGE;
    if (status >= 500) return SERVER_ERROR_MESSAGE;

    return null;
}

/**
 * Daftarkan toast global sekali untuk respons Inertia `invalid` (419/429/5xx),
 * supaya modal error mentah Inertia tidak muncul saat halaman gagal dimuat.
 */
export function useGlobalHttpStatusToast(): void {
    if (registered) return;
    registered = true;

    router.on('invalid', (event: TInvalidEvent) => {
        const response: AxiosResponse<unknown> = event.detail.response;
        const status: number | undefined = response?.status;

        if (typeof status !== 'number' || !isHandledStatus(status)) return;

        event.preventDefault();

        const message: string | null = resolveStatusMessage(status, readRetryAfterHeader(response));

        void showStatusToast(status, message);
    });
}
