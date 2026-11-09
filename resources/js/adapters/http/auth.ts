import { isRecord, readPublicMessage } from '@/adapters/http/httpError';
import { isInternalPath } from '@/lib/urlPolicy';
import { requestJson, requestJsonWithStatus } from '@/adapters/http/httpClient';

/** Restart password: pesan sukses dari server. */
export interface IPasswordResetResult {
    message: string;
}

/** Hasil cek ketersediaan email. */
export interface IEmailAvailabilityResult {
    available: boolean;
}

/** Hasil verifikasi OTP: status HTTP tetap terbaca untuk 422/429. */
export interface IOtpVerificationResult {
    status: number;
    verifiedRedirect: string | null;
    message: string | null;
    retryAfterSeconds: number | null;
}

/**
 * Kirim permintaan tautan reset password.
 * Error validasi (422) dilempar sebagai `HttpRequestError` kind `validation`.
 */
export async function requestPasswordResetLink(url: string, email: string): Promise<IPasswordResetResult> {
    return requestJson({ url, method: 'POST', body: { email } }, (payload) => {
        if (!isRecord(payload)) return null;

        return { message: typeof payload.message === 'string' ? payload.message : '' };
    });
}

/** Cek ketersediaan email saat registrasi; `signal` dipakai debounce pembatalan. */
export async function checkEmailAvailability(
    url: string,
    email: string,
    signal?: AbortSignal,
): Promise<IEmailAvailabilityResult> {
    return requestJson({ url, method: 'POST', body: { email }, signal }, (payload) => {
        if (!isRecord(payload) || typeof payload.available !== 'boolean') return null;

        return { available: payload.available };
    });
}

/**
 * Verifikasi kode OTP. Tidak melempar untuk status HTTP apa pun supaya halaman
 * tetap bisa membedakan 200 / 422 / 429.
 */
export async function verifyOtpCode(url: string, code: string): Promise<IOtpVerificationResult> {
    const result = await requestJsonWithStatus({ url, method: 'POST', body: { code } }, (payload) =>
        isRecord(payload) ? payload : null,
    );
    const payload = result.data;
    const redirect = payload === null ? null : payload.redirect;
    const verifiedRedirect =
        payload !== null && payload.verified === true && typeof redirect === 'string' && isInternalPath(redirect)
            ? redirect
            : null;

    return {
        status: result.status,
        verifiedRedirect,
        message: readPublicMessage(payload),
        retryAfterSeconds: result.retryAfterSeconds,
    };
}
