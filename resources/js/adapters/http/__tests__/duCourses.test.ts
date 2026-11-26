import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { listDuCourses, syncDuCourses } from '@/adapters/http/duCourses';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle, IJsonServerResponse } from '@/adapters/http/__tests__/support/jsonServer';

describe('duCourses adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        const responses: Record<string, IJsonServerResponse> = {
            '/courses': {
                body: JSON.stringify({
                    courses: [
                        { uid: 'course-ai', title: 'AI Engineering' },
                        { uid: 'course-ds', title: 'Data Science' },
                    ],
                }),
            },
            '/courses-malformed-row': {
                body: JSON.stringify({
                    courses: [{ uid: 'course-ai', title: 'AI Engineering' }, { uid: 'course-broken' }],
                }),
            },
            '/courses-malformed-envelope': { body: JSON.stringify({ courses: 'bukan array' }) },
            '/sync': {
                body: JSON.stringify({
                    courses: [{ uid: 'course-ai', title: 'AI Engineering' }],
                    synced_at: '2026-11-25T10:00:00Z',
                }),
            },
            '/sync-missing-timestamp': { body: JSON.stringify({ courses: [] }) },
            '/sync-malformed-row': {
                body: JSON.stringify({
                    courses: [{ uid: '', title: 'AI Engineering' }],
                    synced_at: '2026-11-25T10:00:00Z',
                }),
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

    it('reads a valid list response with GET', async () => {
        const courses = await listDuCourses(`${server.baseUrl}/courses`);

        expect(courses).toEqual([
            { uid: 'course-ai', title: 'AI Engineering' },
            { uid: 'course-ds', title: 'Data Science' },
        ]);
        expect(server.requests.at(-1)?.method).toBe('GET');
    });

    it('rejects the whole list when one course row is malformed', async () => {
        await expect(listDuCourses(`${server.baseUrl}/courses-malformed-row`)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });

    it('rejects a list response without a courses array', async () => {
        await expect(listDuCourses(`${server.baseUrl}/courses-malformed-envelope`)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });

    it('maps synced_at to syncedAt and sends POST', async () => {
        const result = await syncDuCourses(`${server.baseUrl}/sync`);

        expect(result).toEqual({
            courses: [{ uid: 'course-ai', title: 'AI Engineering' }],
            syncedAt: '2026-11-25T10:00:00Z',
        });
        expect(server.requests.at(-1)?.method).toBe('POST');
    });

    it('rejects a sync response without synced_at', async () => {
        await expect(syncDuCourses(`${server.baseUrl}/sync-missing-timestamp`)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });

    it('rejects a sync response with an invalid course row', async () => {
        await expect(syncDuCourses(`${server.baseUrl}/sync-malformed-row`)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });
});
