import { isRecord } from '@/adapters/http/httpError';
import { requestJson } from '@/adapters/http/httpClient';
import type { IApplicationDetail } from '@/types/recruitment';

/** Cek nilai string. */
function isString(value: unknown): value is string {
    return typeof value === 'string';
}

/** Cek nilai boolean. */
function isBoolean(value: unknown): value is boolean {
    return typeof value === 'boolean';
}

/**
 * Validasi runtime detail applicant. Properti diskriminan diperiksa ketat;
 * properti tampilan lain dibiarkan mengikuti kontrak `IApplicationDetail`.
 */
function isApplicationDetail(value: unknown): value is IApplicationDetail {
    if (!isRecord(value)) return false;

    if (
        !isString(value.id) ||
        !isString(value.registration_number) ||
        !isString(value.full_name) ||
        !isString(value.nim) ||
        typeof value.semester !== 'number' ||
        !isString(value.stage) ||
        !isString(value.result) ||
        !isBoolean(value.is_verified)
    ) {
        return false;
    }

    if (
        !Array.isArray(value.screenings) ||
        !Array.isArray(value.activity_logs) ||
        !Array.isArray(value.correction_requests)
    ) {
        return false;
    }

    if (value.document !== null && !isRecord(value.document)) return false;
    if (value.evaluations !== undefined && !isRecord(value.evaluations)) return false;

    return true;
}

/** Ambil detail applicant dari endpoint JSON periode recruitment. */
export async function fetchApplicantDetail(url: string): Promise<IApplicationDetail> {
    return requestJson({ url, method: 'GET' }, (payload) => {
        if (!isRecord(payload)) return null;

        return isApplicationDetail(payload.application) ? payload.application : null;
    });
}
