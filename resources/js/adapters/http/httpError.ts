import { parseRetryAfterSeconds } from '@/lib/http-retry-after';
import type { THttpErrorKind, TValidationErrorBag } from '@/types/http';

/** Input konstruksi `HttpRequestError`; semua field opsional punya default aman. */
export interface IHttpRequestErrorInit {
    kind: THttpErrorKind;
    status: number | null;
    message: string;
    body?: unknown;
    validationErrors?: TValidationErrorBag | null;
    retryAfterSeconds?: number | null;
    cause?: unknown;
}

/** Cek record non-array untuk payload JSON dinamis. */
export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Error transport seragam. Hanya adapter HTTP yang membuat error ini; UI cukup
 * membaca `kind`/`status` tanpa bergantung pada bentuk axios.
 */
export class HttpRequestError extends Error {
    readonly kind: THttpErrorKind;
    readonly status: number | null;
    readonly body: unknown;
    readonly validationErrors: TValidationErrorBag | null;
    readonly retryAfterSeconds: number | null;

    constructor(init: IHttpRequestErrorInit) {
        super(init.message, init.cause === undefined ? undefined : { cause: init.cause });
        this.name = 'HttpRequestError';
        this.kind = init.kind;
        this.status = init.status;
        this.body = init.body;
        this.validationErrors = init.validationErrors ?? null;
        this.retryAfterSeconds = init.retryAfterSeconds ?? null;
    }
}

/** Guard runtime agar pemanggil tidak perlu type assertion. */
export function isHttpRequestError(value: unknown): value is HttpRequestError {
    return value instanceof HttpRequestError;
}

/** Peta status HTTP ke kind error. `419` adalah sesi kedaluwarsa Laravel. */
export function toHttpErrorKind(status: number): THttpErrorKind {
    if (status === 419) return 'unauthorized';
    if (status === 401) return 'unauthorized';
    if (status === 403) return 'forbidden';
    if (status === 404) return 'not_found';
    if (status === 409) return 'conflict';
    if (status === 422) return 'validation';
    if (status === 429) return 'rate_limited';
    if (status >= 500) return 'server';
    if (status >= 400) return 'client';

    return 'unknown';
}

/** Ambil bag error validasi Laravel tanpa membawa nilai non-string. */
export function readValidationErrors(body: unknown): TValidationErrorBag | null {
    if (!isRecord(body) || !isRecord(body.errors)) return null;

    const bag: TValidationErrorBag = {};
    for (const [key, value] of Object.entries(body.errors)) {
        if (typeof value === 'string') {
            bag[key] = value;
            continue;
        }
        if (Array.isArray(value)) {
            bag[key] = value.filter((item): item is string => typeof item === 'string');
        }
    }

    return Object.keys(bag).length > 0 ? bag : null;
}

/** Ambil `message` publik dari body JSON bila tersedia. */
export function readPublicMessage(body: unknown): string | null {
    if (!isRecord(body) || typeof body.message !== 'string') return null;

    const message = body.message.trim();

    return message.length > 0 ? message : null;
}

/** Normalisasi `Retry-After` (detik) menjadi integer positif; nilai 0/invalid menjadi null. */
export function readRetryAfterSeconds(value: unknown): number | null {
    if (typeof value !== 'string' && typeof value !== 'number') return null;

    const seconds = parseRetryAfterSeconds(String(value));

    return seconds !== null && seconds > 0 ? seconds : null;
}
