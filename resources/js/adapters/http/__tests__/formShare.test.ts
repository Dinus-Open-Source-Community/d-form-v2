import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { updateFormShareLink } from '@/adapters/http/formShare';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle, IJsonServerResponse } from '@/adapters/http/__tests__/support/jsonServer';

describe('form share adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        const payload = {
            participant_path: '/joined/event-a/forms/form-a/fill',
            admin_path: '/admin/events/event-a/forms/form-a?tab=editor',
            short_path: '/s/form-a',
            barcode_path: '/admin/events/event-a/forms/form-a/share-link/barcode',
            update_url: '/admin/events/event-a/forms/form-a/share-link',
            alias: 'form-a',
            can_share_participant: true,
        };
        const responses: Record<string, IJsonServerResponse> = {
            '/share-link': { body: JSON.stringify(payload) },
            '/share-link-invalid': { body: JSON.stringify({ ...payload, admin_path: 'https://evil.test/editor' }) },
        };

        server = await startJsonServer((rawUrl: string): IJsonServerResponse => {
            const path = new URL(rawUrl, 'http://127.0.0.1').pathname;

            return responses[path] ?? { status: 404, body: JSON.stringify({ message: 'Tidak ada.' }) };
        });
    });

    afterAll(async (): Promise<void> => {
        await server.close();
    });

    it('sends the alias with PUT and returns the validated refreshed payload', async () => {
        const result = await updateFormShareLink({ url: `${server.baseUrl}/share-link`, alias: 'form-a' });

        expect(result.participant_path).toBe('/joined/event-a/forms/form-a/fill');
        expect(result.alias).toBe('form-a');
        expect(server.requests.at(-1)?.method).toBe('PUT');
        expect(JSON.parse(server.requests.at(-1)?.body ?? '{}')).toEqual({ alias: 'form-a' });
    });

    it('rejects a successful response that violates the relative-path contract', async () => {
        await expect(
            updateFormShareLink({ url: `${server.baseUrl}/share-link-invalid`, alias: null })
        ).rejects.toMatchObject({
            kind: 'invalid_response',
            status: 200,
        });
    });
});
