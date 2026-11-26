import { requestJson } from '@/adapters/http/httpClient';
import { isRecord } from '@/lib/isRecord';
import type { IDuCourseMappingEntry, IDuIssuance, TDuRedeemStatus } from '@/types/du';

const REDEEM_STATUSES: ReadonlySet<string> = new Set(['pending', 'issued', 'failed', 'unknown']);

function isRedeemStatus(value: unknown): value is TDuRedeemStatus {
    return typeof value === 'string' && REDEEM_STATUSES.has(value);
}

/** Input mapping satu opsi kelas; `field_id` milik field kelas DU. */
export interface IDuFieldMappingInput {
    field_id: string;
    option_value: string;
    course_uid: string;
}

/** Parser ketat respons mapping (`{course_uid, course_title}`). */
function parseMapping(payload: unknown): IDuCourseMappingEntry | null {
    if (!isRecord(payload)) return null;

    const { course_uid, course_title } = payload;

    if (typeof course_uid !== 'string' || course_uid === '') return null;
    if (typeof course_title !== 'string' || course_title === '') return null;

    return { course_uid, course_title };
}

/** Parser ketat respons penerbitan; status di luar kontrak dianggap gagal, bukan ditebak. */
function parseIssuance(payload: unknown): IDuIssuance | null {
    if (!isRecord(payload)) return null;

    const { status, code, message, created_new_code } = payload;

    if (!isRedeemStatus(status)) return null;
    if (code !== null && typeof code !== 'string') return null;
    if (message !== null && typeof message !== 'string') return null;
    if (created_new_code !== undefined && typeof created_new_code !== 'boolean') return null;

    return { status, code, message, created_new_code: created_new_code === true };
}

/**
 * Simpan mapping satu nilai opsi kelas ke course DU aktif.
 * Respons hanya berisi identitas course; status aktif ditentukan katalog di hook.
 */
export async function saveDuFieldMapping(url: string, input: IDuFieldMappingInput): Promise<IDuCourseMappingEntry> {
    return requestJson({ url, method: 'PUT', body: input }, parseMapping);
}

/** Terbitkan kode redeem untuk satu jawaban; `url` berasal dari Wayfinder. */
export async function issueDuRedeem(
    url: string,
    action: 'issue' | 'reissue' | 'resend' = 'issue'
): Promise<IDuIssuance> {
    return requestJson({ url, method: 'POST', body: { action } }, parseIssuance);
}
