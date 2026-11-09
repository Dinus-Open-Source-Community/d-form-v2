import axios from 'axios';
import type { AxiosRequestConfig, AxiosResponse } from 'axios';
import {
    HttpRequestError,
    readPublicMessage,
    readRetryAfterSeconds,
    readValidationErrors,
    toHttpErrorKind,
} from '@/adapters/http/httpError';
import type { THttpMethod } from '@/types/http';

/** Opsi request JSON/form-data yang dipakai modul domain. */
export interface IHttpRequestOptions {
    url: string;
    method?: THttpMethod;
    body?: unknown;
    formData?: FormData;
    params?: Record<string, string>;
    signal?: AbortSignal;
    headers?: Record<string, string>;
    timeoutMs?: number;
}

/** Hasil request yang membiarkan status HTTP dibaca pemanggil (mis. OTP 422/429). */
export interface IHttpStatusResult<T> {
    status: number;
    retryAfterSeconds: number | null;
    data: T | null;
}

type TPayloadParser<T> = (payload: unknown) => T | null;

/**
 * Instance axios tunggal. Tidak memakai `window.axios` global supaya adapter
 * tetap bisa diuji tanpa bootstrap browser.
 */
const httpClient = axios.create({
    withCredentials: true,
    // `withXSRFToken` sengaja tidak dipaksa true: default axios hanya mengirim
    // header XSRF untuk URL same-origin, sehingga token tidak bocor lintas origin.
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/** True bila kegagalan berasal dari `AbortController`. */
function isAbortError(error: unknown): boolean {
    return axios.isCancel(error) || (axios.isAxiosError(error) && error.code === 'ERR_CANCELED');
}

/** True bila axios menandai timeout transport. */
function isTimeoutError(error: unknown): boolean {
    return axios.isAxiosError(error) && (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT');
}

/** Ubah kegagalan axios menjadi kontrak `HttpRequestError`. */
function toRequestError(error: unknown): HttpRequestError {
    if (isAbortError(error)) {
        return new HttpRequestError({ kind: 'aborted', status: null, message: 'Permintaan dibatalkan.', cause: error });
    }

    if (isTimeoutError(error)) {
        return new HttpRequestError({
            kind: 'timeout',
            status: null,
            message: 'Permintaan melewati batas waktu.',
            cause: error,
        });
    }

    if (axios.isAxiosError(error) && error.response !== undefined) {
        const { status, data } = error.response;

        return new HttpRequestError({
            kind: toHttpErrorKind(status),
            status,
            message: readPublicMessage(data) ?? `Permintaan gagal (kode ${status}).`,
            body: data,
            validationErrors: readValidationErrors(data),
            retryAfterSeconds: readRetryAfterSeconds(error.response.headers['retry-after']),
            cause: error,
        });
    }

    return new HttpRequestError({
        kind: 'network',
        status: null,
        message: 'Tidak dapat menghubungi server.',
        cause: error,
    });
}

/** Kirim request dan melempar `HttpRequestError` untuk kegagalan transport/status. */
async function send(options: IHttpRequestOptions, tolerateHttpStatus = false): Promise<AxiosResponse<unknown>> {
    try {
        const data = options.formData === undefined ? options.body : options.formData;
        const config: AxiosRequestConfig = {
            url: options.url,
            method: options.method ?? 'GET',
            data,
            params: options.params,
            signal: options.signal,
            headers: options.headers,
            timeout: options.timeoutMs,
        };
        if (tolerateHttpStatus) config.validateStatus = (): boolean => true;

        return await httpClient.request<unknown>(config);
    } catch (error) {
        throw toRequestError(error);
    }
}

/**
 * Request JSON dengan validasi runtime wajib. `parse` yang mengembalikan `null`
 * dianggap respons tidak valid, bukan `unknown` yang dibiarkan lolos.
 */
export async function requestJson<T>(options: IHttpRequestOptions, parse: TPayloadParser<T>): Promise<T> {
    const response = await send(options);
    const data = parse(response.data);

    if (data === null) {
        throw new HttpRequestError({
            kind: 'invalid_response',
            status: response.status,
            message: 'Respons server tidak sesuai kontrak.',
            body: response.data,
        });
    }

    return data;
}

/** Request JSON yang membiarkan status HTTP apa pun dibaca pemanggil. */
export async function requestJsonWithStatus<T>(
    options: IHttpRequestOptions,
    parse: TPayloadParser<T>
): Promise<IHttpStatusResult<T>> {
    const response = await send(options, true);
    const data = parse(response.data);
    const isSuccess = response.status >= 200 && response.status < 300;

    if (isSuccess && data === null) {
        throw new HttpRequestError({
            kind: 'invalid_response',
            status: response.status,
            message: 'Respons server tidak sesuai kontrak.',
            body: response.data,
        });
    }

    return {
        status: response.status,
        retryAfterSeconds: readRetryAfterSeconds(response.headers['retry-after']),
        data,
    };
}

/** Unduh file sebagai blob; error transport tetap melempar `HttpRequestError`. */
export async function requestBlob(url: string, signal?: AbortSignal): Promise<Blob> {
    try {
        const response = await httpClient.get<ArrayBuffer>(url, { responseType: 'arraybuffer', signal });
        const contentType = response.headers['content-type'];

        return new Blob([response.data], { type: typeof contentType === 'string' ? contentType : '' });
    } catch (error) {
        throw toRequestError(error);
    }
}

/**
 * Probe URL halaman dan kembalikan URL final setelah redirect.
 * Memakai `fetch` di dalam adapter agar opsi cache/redirect/header tetap
 * persis seperti guard auth sebelumnya (`no-store`, non-AJAX, follow redirect).
 */
export async function probePageUrl(url: string): Promise<string> {
    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'text/html' },
            redirect: 'follow',
        });

        return response.url.length > 0 ? response.url : url;
    } catch (error) {
        throw toRequestError(error);
    }
}
