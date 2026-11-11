import { HttpRequestError, isRecord } from '@/adapters/http/httpError';
import { requestBlob, requestJson, requestJsonWithStatus, sendJsonKeepalive } from '@/adapters/http/httpClient';
import { createPercentProgressReporter } from '@/adapters/http/upload';
import type { TFormReviewStatus, IFormReviewErrorBody, IFormReviewSuccessResponse } from '@/types/forms';
import type { IBackendField, TBackendFieldType } from '@/types/form-builder';
import type { THttpMethod, IUploadTransferOptions } from '@/types/http';
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

/**
 * Snapshot final autosave yang dikirim saat halaman ditinggalkan. Kedua bagian
 * opsional: autosave builder memakai dua kanal (header saja / fields + settings).
 */
export interface IFormSnapshotKeepaliveOptions {
    fieldsUrl?: string;
    fields?: unknown[];
    settingsUrl?: string;
    settings?: Record<string, unknown>;
}

/** Tipe field backend yang dikenal (validasi runtime payload draf). */
const BACKEND_FIELD_TYPES: ReadonlySet<string> = new Set([
    'input',
    'select',
    'textarea',
    'datePicker',
    'fileUpload',
    'checkbox',
    'radio',
]);

/** Hasil `POST /admin/events/{event}/forms/draft`: snapshot draf + URL halaman edit. */
export interface IFormDraftResult {
    formId: string;
    editUrl: string;
    form: IForm;
    fields: IBackendField[];
}

/** Guard header form dari body draf (`formToInertia()`). */
function readDraftForm(value: unknown): IForm | null {
    if (!isRecord(value)) return null;

    const id = value.id;
    const title = value.title;
    const description = value.description;
    const eventId = value.event_id;
    const visibleFor = value.visible_for;

    if (typeof id !== 'string' || id === '') return null;
    if (typeof title !== 'string' || typeof description !== 'string') return null;
    if (typeof eventId !== 'string' || eventId === '') return null;
    if (!Array.isArray(visibleFor) || !visibleFor.every((item): boolean => typeof item === 'string')) return null;

    return {
        id,
        title,
        description,
        event_id: eventId,
        visible_for: visibleFor,
        closed_at: typeof value.closed_at === 'string' ? value.closed_at : '',
        success_content: typeof value.success_content === 'string' ? value.success_content : null,
        banner_url: typeof value.banner_url === 'string' ? value.banner_url : null,
        banner_caption: typeof value.banner_caption === 'string' ? value.banner_caption : null,
        metadata: isRecord(value.metadata) ? value.metadata : undefined,
    };
}

/** Guard satu baris field backend (`formFieldToInertia()`). */
function readDraftField(value: unknown): IBackendField | null {
    if (!isRecord(value)) return null;

    const id = value.id;
    const name = value.name;
    const type = value.type;
    const order = value.order;

    if (typeof id !== 'string' || id === '') return null;
    if (typeof name !== 'string' || name === '') return null;
    if (typeof type !== 'string' || !BACKEND_FIELD_TYPES.has(type)) return null;
    if (typeof order !== 'number' || !Number.isFinite(order)) return null;

    return {
        id,
        name,
        // `type` lolos membership `BACKEND_FIELD_TYPES` (runtime validation) sebelum dipersempit.
        type: type as TBackendFieldType,
        label: typeof value.label === 'string' ? value.label : '',
        description: typeof value.description === 'string' ? value.description : null,
        order,
        metadata: isRecord(value.metadata) ? value.metadata : {},
        is_append: value.is_append === true,
    };
}

/**
 * Ambil atau buat draf server-side untuk halaman "buat form". Backend idempoten per
 * user+event dan mengembalikan snapshot lengkap (`{ form, fields }`) supaya halaman
 * bisa melanjutkan draf setelah reload. 403 = draf milik pembuat event.
 */
export async function requestFormDraft(url: string): Promise<IFormDraftResult> {
    return requestJson({ url, method: 'POST' }, (payload) => {
        if (!isRecord(payload)) return null;

        const form = readDraftForm(payload.form);
        if (form === null) return null;

        const editUrl = isRecord(payload.form) ? payload.form.edit_url : undefined;
        if (typeof editUrl !== 'string' || editUrl === '') return null;

        const fields: IBackendField[] = [];
        if (payload.fields !== undefined) {
            if (!Array.isArray(payload.fields)) return null;

            for (const row of payload.fields) {
                const field = readDraftField(row);
                if (field === null) return null;
                fields.push(field);
            }
        }

        return { formId: form.id, editUrl, form, fields };
    });
}

/**
 * Simpan snapshot terakhir dengan `keepalive`, dipanggil dari `pagehide`.
 * Request yang sudah berjalan lewat axios/XHR dibatalkan browser saat reload;
 * jalur ini memakai Fetch `keepalive` sehingga perubahan terakhir tetap sampai.
 */
export function persistFormSnapshotKeepalive(options: IFormSnapshotKeepaliveOptions): void {
    if (options.fieldsUrl !== undefined && options.fields !== undefined) {
        sendJsonKeepalive({ url: options.fieldsUrl, method: 'POST', body: { fields: options.fields } });
    }

    if (options.settingsUrl === undefined || options.settings === undefined) return;

    sendJsonKeepalive({ url: options.settingsUrl, method: 'PUT', body: options.settings });
}

/** Upload satu gambar builder ke endpoint `{form}/images`. */
export async function uploadBuilderImage(
    url: string,
    file: File,
    fieldName = 'file',
    options?: IUploadTransferOptions
): Promise<IBuilderImageUploadResult> {
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
            if (!isRecord(payload)) return null;

            const path = typeof payload.path === 'string' ? payload.path.trim() : '';
            const rawUrl = typeof payload.url === 'string' ? payload.url.trim() : '';
            const safeUrl = isPersistableImageUrl(rawUrl) ? rawUrl : '';

            if (!isPersistableImageUrl(path) && safeUrl === '') return null;

            return { path, url: safeUrl };
        }
    );
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
