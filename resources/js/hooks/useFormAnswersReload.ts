import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { HttpExceptionResponse } from '@inertiajs/core';
import { parseApiErrorMessage, showErrorToast, showHttpErrorToast } from '@/lib/error-message';

const RELOAD_ERROR_FALLBACK = 'Gagal memuat ulang jawaban. Coba lagi.';
const NETWORK_ERROR_MESSAGE = 'Tidak dapat menghubungi server. Coba lagi.';

function responseBody(response: HttpExceptionResponse): unknown {
    if (typeof response.data !== 'string') return response.data;
    if (response.data.trim() === '') return null;

    try {
        const parsed: unknown = JSON.parse(response.data);
        return parsed;
    } catch {
        return null;
    }
}

/** Owns partial answer reload state and translates failed visits into user feedback. */
export function useFormAnswersReload(): {
    reloadErrorMessage: Ref<string | null>;
    reloadSubmissions: (onSuccess?: () => void) => void;
    retrySubmissions: () => void;
} {
    const reloadErrorMessage = ref<string | null>(null);

    const setHttpReloadError = (response: HttpExceptionResponse): false => {
        const body = responseBody(response);
        const message = parseApiErrorMessage(body, RELOAD_ERROR_FALLBACK);

        reloadErrorMessage.value = message;
        showHttpErrorToast(response.status, body, { [response.status]: message });
        return false;
    };

    const setNetworkReloadError = (): void => {
        reloadErrorMessage.value = NETWORK_ERROR_MESSAGE;
        showErrorToast(NETWORK_ERROR_MESSAGE);
    };

    const reloadSubmissions = (onSuccess?: () => void): void => {
        reloadErrorMessage.value = null;
        router.reload({
            only: ['submissions'],
            onSuccess: () => {
                reloadErrorMessage.value = null;
                onSuccess?.();
            },
            onError: (errors) => {
                const message = parseApiErrorMessage(errors, RELOAD_ERROR_FALLBACK);
                reloadErrorMessage.value = message;
                showHttpErrorToast(422, errors, { 422: message });
            },
            onHttpException: setHttpReloadError,
            onNetworkError: setNetworkReloadError,
        });
    };

    const retrySubmissions = (): void => {
        reloadSubmissions();
    };

    return { reloadErrorMessage, reloadSubmissions, retrySubmissions };
}
