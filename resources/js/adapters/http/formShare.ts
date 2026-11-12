import { isRecord } from '@/adapters/http/httpError';
import { requestJson } from '@/adapters/http/httpClient';
import type { IFormSharePayload } from '@/types/formShare';

function isRelativePath(value: unknown): value is string {
    return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !value.includes('\\');
}

function parseFormSharePayload(payload: unknown): IFormSharePayload | null {
    if (!isRecord(payload)) return null;
    if (!isRelativePath(payload.participant_path)) return null;
    if (!isRelativePath(payload.admin_path)) return null;
    if (payload.short_path !== null && !isRelativePath(payload.short_path)) return null;
    if (!isRelativePath(payload.barcode_path)) return null;
    if (!isRelativePath(payload.update_url)) return null;
    if (payload.alias !== null && (typeof payload.alias !== 'string' || payload.alias.length === 0)) return null;
    if (typeof payload.can_share_participant !== 'boolean') return null;

    return {
        participant_path: payload.participant_path,
        admin_path: payload.admin_path,
        short_path: payload.short_path,
        barcode_path: payload.barcode_path,
        update_url: payload.update_url,
        alias: payload.alias,
        can_share_participant: payload.can_share_participant,
    };
}

/** Persist an alias choice and validate the refreshed share payload from the server. */
export async function updateFormShareLink(options: { url: string; alias: string | null }): Promise<IFormSharePayload> {
    return requestJson({ url: options.url, method: 'PUT', body: { alias: options.alias } }, parseFormSharePayload);
}
