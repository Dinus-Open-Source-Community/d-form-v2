import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { issueDuRedeem, saveDuFieldMapping } from '@/adapters/http/duMapping';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle, IJsonServerResponse } from '@/adapters/http/__tests__/support/jsonServer';

const MAPPING_INPUT = { field_id: 'field-1', option_value: 'AI Engineer', course_uid: 'course-ai' };

describe('duMapping adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        const responses: Record<string, IJsonServerResponse> = {
            '/field-map': { body: JSON.stringify({ course_uid: 'course-ai', course_title: 'AI Engineer' }) },
            '/field-map-malformed': { body: JSON.stringify({ course_uid: '', course_title: 'AI Engineer' }) },
            '/issuance': { body: JSON.stringify({ status: 'issued', code: 'DU-1', message: null }) },
            '/issuance-unknown-status': {
                body: JSON.stringify({ status: 'queued', code: null, message: null }),
            },
        };

        server = await startJsonServer((rawUrl: string): IJsonServerResponse => {
            const path = new URL(rawUrl, 'http://127.0.0.1').pathname;

            return responses[path] ?? { status: 404, body: JSON.stringify({ message: 'Tidak ada.' }) };
        });
    });

    afterAll(async (): Promise<void> => {
        await server.close();
    });

    it('mengirim mapping dengan PUT dan membaca identitas course', async () => {
        const entry = await saveDuFieldMapping(`${server.baseUrl}/field-map`, MAPPING_INPUT);

        expect(entry).toEqual({ course_uid: 'course-ai', course_title: 'AI Engineer' });
        expect(server.requests.at(-1)?.method).toBe('PUT');
        expect(JSON.parse(server.requests.at(-1)?.body ?? '')).toEqual(MAPPING_INPUT);
    });

    it('menolak respons mapping tanpa course_uid', async () => {
        await expect(saveDuFieldMapping(`${server.baseUrl}/field-map-malformed`, MAPPING_INPUT)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });

    it('menerbitkan kode dengan POST dan membaca status', async () => {
        const issuance = await issueDuRedeem(`${server.baseUrl}/issuance`);

        expect(issuance).toEqual({ status: 'issued', code: 'DU-1', message: null, created_new_code: false });
        expect(server.requests.at(-1)?.method).toBe('POST');
        expect(JSON.parse(server.requests.at(-1)?.body ?? '')).toEqual({ action: 'issue' });
    });

    it('menolak status penerbitan di luar kontrak', async () => {
        await expect(issueDuRedeem(`${server.baseUrl}/issuance-unknown-status`)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });
});
