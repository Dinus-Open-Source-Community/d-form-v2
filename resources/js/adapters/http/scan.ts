import { isRecord } from '@/adapters/http/httpError';
import { requestJson, requestJsonWithStatus } from '@/adapters/http/httpClient';
import type { IScanEnvelope, IScanErrorBody, IScanFeedPayload, IScanFeedRow } from '@/types/scan';

/** Hasil submit scan: envelope sukses atau body error dengan status HTTP. */
export interface IScanSubmitResult {
    status: number;
    envelope: IScanEnvelope | null;
    errorBody: IScanErrorBody | null;
    errorMessage: string | null;
    retryAfterSeconds: number | null;
}

/** Baca field string opsional dari record. */
function readOptionalString(record: Record<string, unknown>, key: string): string | undefined {
    const value = record[key];

    return typeof value === 'string' ? value : undefined;
}

/** Baca field string wajib dengan fallback kosong. */
function readString(record: Record<string, unknown>, key: string): string {
    const value = record[key];

    return typeof value === 'string' ? value : '';
}

/** Baca `queueNumber` sebagai angka finit atau `null`. */
function readQueueNumber(record: Record<string, unknown>): number | null {
    const value = record.queueNumber;

    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

/** Baca attendee secara defensif. */
function readAttendee(value: unknown): IScanEnvelope['attendee'] | undefined {
    if (!isRecord(value)) return undefined;

    const queueNumber = value.queue_number;

    return {
        name: readOptionalString(value, 'name'),
        email: readOptionalString(value, 'email'),
        registration_number: readOptionalString(value, 'registration_number'),
        application_id: readOptionalString(value, 'application_id'),
        interview_status_label: readOptionalString(value, 'interview_status_label'),
        form_answer_id: readOptionalString(value, 'form_answer_id'),
        queue_number: typeof queueNumber === 'number' || queueNumber === null ? queueNumber : undefined,
    };
}

/** Validasi envelope sukses scan. */
function parseEnvelope(payload: unknown): IScanEnvelope | null {
    if (!isRecord(payload)) return null;

    const type = payload.type;
    const attendee = readAttendee(payload.attendee);
    if ((type !== 'event' && type !== 'recruitment') || attendee === undefined) return null;

    return {
        type,
        eventTitle: typeof payload.eventTitle === 'string' ? payload.eventTitle : '',
        attendee,
        status: payload.status === 'duplicate' ? 'duplicate' : 'success',
        scannedAt: typeof payload.scannedAt === 'string' ? payload.scannedAt : '',
    };
}

/** Baca bag error: semua key dipertahankan, entri non-string di dalam array dibuang. */
function readErrorBag(value: unknown): Record<string, string[]> | undefined {
    if (!isRecord(value)) return undefined;

    const bag: Record<string, string[]> = {};
    for (const [key, item] of Object.entries(value)) {
        if (Array.isArray(item)) {
            bag[key] = item.filter((entry): entry is string => typeof entry === 'string');
        }
    }

    return Object.keys(bag).length > 0 ? bag : undefined;
}

/** Validasi body error scan tanpa membuang konteks peserta. */
function parseErrorBody(payload: unknown): IScanErrorBody | null {
    if (!isRecord(payload)) return null;

    return {
        message: typeof payload.message === 'string' ? payload.message : undefined,
        type: typeof payload.type === 'string' ? payload.type : undefined,
        eventTitle: typeof payload.eventTitle === 'string' ? payload.eventTitle : undefined,
        attendee: readAttendee(payload.attendee),
        errors: readErrorBag(payload.errors),
    };
}

/** Validasi satu baris feed; `null` berarti baris tidak dipakai. */
function parseFeedRow(value: unknown): IScanFeedRow | null {
    if (!isRecord(value)) return null;

    const id = readString(value, 'id');
    const ts = readString(value, 'ts');
    const type = readString(value, 'type');
    if (id.length === 0 || ts.length === 0) return null;
    if (type !== 'event' && type !== 'recruitment') return null;

    return {
        id,
        ts,
        type,
        eventTitle: readString(value, 'eventTitle'),
        name: readString(value, 'name'),
        identifier: readString(value, 'identifier'),
        queueNumber: readQueueNumber(value),
    };
}

/**
 * Validasi payload feed di boundary jaringan: baris yang tidak sesuai kontrak
 * dibuang, payload non-record atau payload yang seluruh barisnya invalid ditolak
 * agar `requestJson` melempar `invalid_response`.
 */
function parseFeedPayload(payload: unknown): IScanFeedPayload | null {
    if (!isRecord(payload) || !Array.isArray(payload.rows)) return null;

    const rows: IScanFeedRow[] = [];
    for (const item of payload.rows) {
        const row = parseFeedRow(item);
        if (row !== null) rows.push(row);
    }

    if (payload.rows.length > 0 && rows.length === 0) return null;

    return { cursor: readString(payload, 'cursor'), rows };
}

/** Ambil feed scan lintas meja; baris divalidasi di adapter, bukan di `lib/`. */
export async function fetchScanFeed(options: {
    url: string;
    cursor: string;
    signal?: AbortSignal;
    timeoutMs?: number;
}): Promise<IScanFeedPayload> {
    return requestJson(
        {
            url: options.url,
            method: 'GET',
            params: options.cursor.length > 0 ? { since: options.cursor } : undefined,
            signal: options.signal,
            timeoutMs: options.timeoutMs,
        },
        parseFeedPayload
    );
}

/** Parser payload submit scan: envelope sukses atau body error, tanpa `unknown` keluar adapter. */
function parseScanPayload(payload: unknown): {
    envelope: IScanEnvelope | null;
    errorBody: IScanErrorBody | null;
} | null {
    if (!isRecord(payload)) return null;

    return { envelope: parseEnvelope(payload), errorBody: parseErrorBody(payload) };
}

/** Kirim satu payload scan (kamera atau input manual). */
export async function submitScan(options: {
    url: string;
    payload: Record<string, string>;
}): Promise<IScanSubmitResult> {
    const result = await requestJsonWithStatus(
        { url: options.url, method: 'POST', body: options.payload },
        parseScanPayload
    );
    const isSuccess = result.status >= 200 && result.status < 300;

    return {
        status: result.status,
        envelope: isSuccess ? (result.data?.envelope ?? null) : null,
        errorBody: isSuccess ? null : (result.data?.errorBody ?? null),
        errorMessage: isSuccess ? null : (result.data?.errorBody?.message ?? null),
        retryAfterSeconds: result.retryAfterSeconds,
    };
}
