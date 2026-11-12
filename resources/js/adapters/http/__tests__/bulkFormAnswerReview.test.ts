import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { reviewFormSubmissionsBulk } from '@/adapters/http/forms';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle, IJsonServerResponse } from '@/adapters/http/__tests__/support/jsonServer';

describe('bulk form answer review adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        const responses: Record<string, IJsonServerResponse> = {
            '/bulk-review': {
                body: JSON.stringify({
                    requested: 2,
                    processed: 2,
                    skipped: 0,
                    conflicts: 0,
                    queued_intents: 2,
                }),
            },
            '/bulk-review-422': {
                status: 422,
                body: JSON.stringify({ message: 'Batch tidak eligible.' }),
            },
            '/bulk-review-409': {
                status: 409,
                body: JSON.stringify({ message: 'Batch berubah.' }),
            },
            '/bulk-review-inconsistent-requested': {
                body: JSON.stringify({
                    requested: 1,
                    processed: 1,
                    skipped: 0,
                    conflicts: 0,
                    queued_intents: 1,
                }),
            },
            '/bulk-review-partial': {
                body: JSON.stringify({
                    requested: 2,
                    processed: 1,
                    skipped: 1,
                    conflicts: 0,
                    queued_intents: 1,
                }),
            },
            '/bulk-review-missing-intent': {
                body: JSON.stringify({
                    requested: 2,
                    processed: 2,
                    skipped: 0,
                    conflicts: 0,
                    queued_intents: 1,
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

    it('sends explicit submission IDs and parses the success summary', async () => {
        const result = await reviewFormSubmissionsBulk({
            url: `${server.baseUrl}/bulk-review`,
            submissionIds: ['sub-1', 'sub-2'],
            reviewStatus: 'accepted',
        });

        expect(result.status).toBe(200);
        expect(result.data).toEqual({
            requested: 2,
            processed: 2,
            skipped: 0,
            conflicts: 0,
            queued_intents: 2,
        });

        const request = server.requests.at(-1);
        expect(request?.method).toBe('PATCH');
        expect(JSON.parse(request?.body ?? '{}')).toEqual({
            submission_ids: ['sub-1', 'sub-2'],
            review_status: 'accepted',
        });
    });

    it('preserves safe validation and conflict responses', async () => {
        await expect(
            reviewFormSubmissionsBulk({
                url: `${server.baseUrl}/bulk-review-422`,
                submissionIds: ['sub-1'],
                reviewStatus: 'rejected',
            })
        ).resolves.toMatchObject({ status: 422, message: 'Batch tidak eligible.', data: null });

        await expect(
            reviewFormSubmissionsBulk({
                url: `${server.baseUrl}/bulk-review-409`,
                submissionIds: ['sub-1'],
                reviewStatus: 'accepted',
            })
        ).resolves.toMatchObject({ status: 409, message: 'Batch berubah.', data: null });
    });

    it('rejects a 2xx summary whose requested count differs from the submitted IDs', async () => {
        await expect(
            reviewFormSubmissionsBulk({
                url: `${server.baseUrl}/bulk-review-inconsistent-requested`,
                submissionIds: ['sub-1', 'sub-2'],
                reviewStatus: 'accepted',
            })
        ).rejects.toMatchObject({ kind: 'invalid_response', status: 200 });
    });

    it('rejects a 2xx summary that reports partial processing or conflicts', async () => {
        await expect(
            reviewFormSubmissionsBulk({
                url: `${server.baseUrl}/bulk-review-partial`,
                submissionIds: ['sub-1', 'sub-2'],
                reviewStatus: 'rejected',
            })
        ).rejects.toMatchObject({ kind: 'invalid_response', status: 200 });
    });

    it('rejects a 2xx summary without one queued intent per processed row', async () => {
        await expect(
            reviewFormSubmissionsBulk({
                url: `${server.baseUrl}/bulk-review-missing-intent`,
                submissionIds: ['sub-1', 'sub-2'],
                reviewStatus: 'accepted',
            })
        ).rejects.toMatchObject({ kind: 'invalid_response', status: 200 });
    });
});
