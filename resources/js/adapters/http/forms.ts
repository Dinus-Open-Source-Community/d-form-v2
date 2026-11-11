import { HttpRequestError, isRecord } from '@/adapters/http/httpError';
import { requestBlob, requestJson, requestJsonWithStatus, sendJsonKeepalive } from '@/adapters/http/httpClient';
import type { TFormReviewStatus, IFormReviewErrorBody, IFormReviewSuccessResponse } from '@/types/forms';
import type { THttpMethod } from '@/types/http';
import { isPersistableImageUrl } from '@/lib/urlPolicy';

/** Respons upload gambar builder: `path` internal dan `url` publik. */
export interface IBuilderImageUploadResult {
    path: string;
    url: string;
}

/** Ringkasan akun yang ditemukan saat cek email peserta. */
export interface IRegisteredUserSummary {
    name: string;
    email: string;
    created_at: string;
}

/** Hasil cek email peserta; `status` dipakai UI membedakan 4xx/5xx dari 200 not-found. */
export interface IRegisteredUserLookup {
    status: number;
    exists: boolean;
    message: string;
    user: IRegisteredUserSummary | null;
}

/** Hasil review submission; status dipakai UI untuk 409/422/403/404. */
export interface IFormReviewResult {
    status: number;
    message: string | null;
    data: IFormReviewSuccessResponse | null;
    currentStatus: TFormReviewStatus | null;
}

/** Parser ACK `{ ok: true }` untuk autosave builder (menolak 2xx non-JSON/kosong). */
function parseAck(payload: unknown): true | null {
    return isRecord(payload) && payload.ok === true ? true : null;
}

/** Guard status review dari body JSON dinamis. */
function isReviewStatus(value: unknown): value is TFormReviewStatus {
    return value === 'pending' || value === 'accepted' || value === 'rejected';
}

/** Validasi body sukses review sesuai kontrak `FormAnswerReviewController`. */
function parseReviewSuccess(payload: Record<string, unknown>): IFormReviewSuccessResponse | null {
    const id = payload.id;
    const reviewStatus = payload.review_status;
    const reviewedAt = payload.reviewed_at;
    const reviewedBy = payload.reviewed_by;
    const registrationCode = payload.registration_code;
    const cascadedRejections = payload.cascaded_rejections;

    if (typeof id !== 'string' || id.length === 0) return null;
    if (reviewStatus !== 'accepted' && reviewStatus !== 'rejected') return null;
    if (typeof reviewedAt !== 'string' || reviewedAt.length === 0) return null;
    if (typeof reviewedBy !== 'string' || reviewedBy.length === 0) return null;
    if (typeof cascadedRejections !== 'number' || !Number.isInteger(cascadedRejections) || cascadedRejections < 0) {
        return null;
    }

    if (reviewStatus === 'accepted') {
        if (typeof registrationCode !== 'string' || registrationCode.length === 0) return null;
    } else if (registrationCode !== null && typeof registrationCode !== 'string') {
        return null;
    }

    return {
        id,
        review_status: reviewStatus,
        reviewed_at: reviewedAt,
        reviewed_by: reviewedBy,
        registration_code: typeof registrationCode === 'string' ? registrationCode : null,
        cascaded_rejections: cascadedRejections,
    };
}

/** Varian payload review yang bisa dibedakan tanpa melihat status HTTP. */
type TReviewPayload =
    { kind: 'success'; data: IFormReviewSuccessResponse } | { kind: 'error'; body: IFormReviewErrorBody; raw: unknown };

/**
 * Parser payload review: body yang bukan kontrak sukses dianggap body error,
 * sehingga keputusan berdasarkan status HTTP tetap di pemanggil.
 */
function parseReviewPayload(payload: unknown): TReviewPayload | null {
    if (!isRecord(payload)) return null;

    const success = parseReviewSuccess(payload);
    if (success !== null) return { kind: 'success', data: success };

    return {
        kind: 'error',
        raw: payload,
        body: {
            message: typeof payload.message === 'string' ? payload.message : undefined,
            current_status: isReviewStatus(payload.current_status) ? payload.current_status : null,
        },
    };
}

/** Parser ringkasan user dari payload dinamis; nama dan email wajib string. */
function readRegisteredUser(value: unknown): IRegisteredUserSummary | null {
    if (!isRecord(value) || typeof value.name !== 'string' || typeof value.email !== 'string') return null;

    return {
        name: value.name,
        email: value.email,
        created_at: typeof value.created_at === 'string' ? value.created_at : '',
    };
}

/** URL endpoint upload gambar builder `POST /admin/events/{event}/forms/{form}/images`. */
export function resolveBuilderImageUploadUrl(eventId: string | number, formId: string | number): string {
    return `/admin/events/${eventId}/forms/${formId}/images`;
}

/** Simpan daftar field form builder. Payload builder bersifat dinamis, adapter hanya menyerialkan. */
export async function saveFormFields(url: string, fields: unknown[]): Promise<void> {
    await requestJson({ url, method: 'POST', body: { fields } }, parseAck);
}

/** Simpan header/metadata form builder. */
export async function updateFormSettings(url: string, payload: Record<string, unknown>): Promise<void> {
    await requestJson({ url, method: 'PUT', body: payload }, parseAck);
}

/** Snapshot final autosave yang dikirim saat halaman ditinggalkan. */
export interface IFormSnapshotKeepaliveOptions {
    fieldsUrl: string;
    fields: unknown[];
    /** Bila kosong, hanya daftar field yang dikirim (header belum lengkap). */
    settingsUrl?: string;
    settings?: Record<string, unknown>;
}

/**
 * Simpan snapshot terakhir dengan `keepalive`, dipanggil dari `pagehide`.
 * Request yang sudah berjalan lewat axios/XHR dibatalkan browser saat reload;
 * jalur ini memakai Fetch `keepalive` sehingga perubahan terakhir tetap sampai.
 */
export function persistFormSnapshotKeepalive(options: IFormSnapshotKeepaliveOptions): void {
    sendJsonKeepalive({ url: options.fieldsUrl, method: 'POST', body: { fields: options.fields } });

    if (options.settingsUrl === undefined || options.settings === undefined) return;

    sendJsonKeepalive({ url: options.settingsUrl, method: 'PUT', body: options.settings });
}

/** Upload satu gambar builder ke endpoint `{form}/images`. */
export async function uploadBuilderImage(
    url: string,
    file: File,
    fieldName = 'file'
): Promise<IBuilderImageUploadResult> {
    const formData = new FormData();
    formData.append(fieldName, file);

    return requestJson({ url, method: 'POST', formData }, (payload) => {
        if (!isRecord(payload)) return null;

        const path = typeof payload.path === 'string' ? payload.path.trim() : '';
        const rawUrl = typeof payload.url === 'string' ? payload.url.trim() : '';
        const safeUrl = isPersistableImageUrl(rawUrl) ? rawUrl : '';

        if (!isPersistableImageUrl(path) && safeUrl === '') return null;

        return { path, url: safeUrl };
    });
}

/** Kirim keputusan review submission (accept/reject). */
export async function reviewFormSubmission(options: {
    url: string;
    method: THttpMethod;
    reviewStatus: 'accepted' | 'rejected';
}): Promise<IFormReviewResult> {
    const result = await requestJsonWithStatus(
        { url: options.url, method: options.method, body: { review_status: options.reviewStatus } },
        parseReviewPayload
    );

    const isSuccess = result.status >= 200 && result.status < 300;

    if (!isSuccess) {
        return {
            status: result.status,
            message: result.data?.kind === 'error' ? (result.data.body.message ?? null) : null,
            data: null,
            currentStatus: result.data?.kind === 'error' ? (result.data.body.current_status ?? null) : null,
        };
    }

    if (result.data?.kind !== 'success') {
        throw new HttpRequestError({
            kind: 'invalid_response',
            status: result.status,
            message: 'Respons server tidak sesuai kontrak.',
            body: result.data?.kind === 'error' ? result.data.raw : null,
        });
    }

    return { status: result.status, message: null, data: result.data.data, currentStatus: null };
}

/** Cek apakah email peserta sudah punya akun. `url` sudah memuat query email. */
export async function lookupRegisteredUser(options: {
    url: string;
    signal?: AbortSignal;
}): Promise<IRegisteredUserLookup> {
    const result = await requestJsonWithStatus(
        { url: options.url, method: 'GET', signal: options.signal },
        (payload) => {
            if (!isRecord(payload)) return null;

            const exists = typeof payload.exists === 'boolean' ? payload.exists : null;
            const message = typeof payload.message === 'string' ? payload.message : '';
            const user = exists === true ? readRegisteredUser(payload.data) : null;

            // 200 dengan `exists:true` wajib menyertakan user yang bisa dipakai UI.
            if (exists === true && user === null) return null;
            // Record tanpa `exists` dan tanpa pesan bukan kontrak yang dikenal.
            if (exists === null && message === '') return null;

            return { exists, message, user };
        }
    );
    const data = result.data;
    const isSuccess = result.status >= 200 && result.status < 300;

    if (isSuccess && (data === null || data.exists === null)) {
        throw new HttpRequestError({
            kind: 'invalid_response',
            status: result.status,
            message: 'Respons server tidak sesuai kontrak.',
            body: data,
        });
    }

    return {
        status: result.status,
        exists: data?.exists === true,
        message: data?.message ?? '',
        user: data?.user ?? null,
    };
}

/** Unduh lampiran jawaban form sebagai blob. */
export async function downloadFormAnswerFile(url: string, signal?: AbortSignal): Promise<Blob> {
    return requestBlob(url, signal);
}
