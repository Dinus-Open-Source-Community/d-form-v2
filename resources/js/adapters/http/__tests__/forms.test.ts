import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import {
    lookupRegisteredUser,
    reviewFormSubmission,
    saveFormFields,
    updateFormSettings,
    uploadBuilderImage,
} from '@/adapters/http/forms';
import { uploadBroadcastImage } from '@/adapters/http/broadcast';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle, IJsonServerResponse } from '@/adapters/http/__tests__/support/jsonServer';

describe('forms adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        const responses: Record<string, IJsonServerResponse> = {
            '/submissions/1/review': {
                body: JSON.stringify({
                    id: 'sub-1',
                    review_status: 'accepted',
                    reviewed_at: '2026-01-01T00:00:00Z',
                    reviewed_by: 'Admin',
                    registration_code: 'QR-1',
                    cascaded_rejections: 0,
                }),
            },
            '/submissions/2/review': { status: 409, body: JSON.stringify({ message: 'sudah' }) },
            '/fields': { body: JSON.stringify({ ok: true }) },
            '/fields-invalid-ack': { body: JSON.stringify({ ok: false, message: 'gagal' }) },
            '/form': { body: JSON.stringify({ ok: true }) },
            '/builder-upload': { body: JSON.stringify({ path: 'form-images/a.png', url: '/storage/a.png' }) },
            '/builder-upload-data': { body: JSON.stringify({ path: 'form-images/a.png', url: 'data:image/png;base64,x' }) },
            '/builder-upload-only-data': { body: JSON.stringify({ url: 'data:image/png;base64,x' }) },
            '/broadcast-upload': { body: JSON.stringify({ url: '/storage/b.png' }) },
            '/broadcast-upload-data': { body: JSON.stringify({ url: 'data:image/png;base64,x' }) },
            '/check-email': {
                body: JSON.stringify({
                    exists: true,
                    message: 'ditemukan',
                    data: { name: 'A', email: 'a@x.test', created_at: '2026-01-01' },
                }),
            },
            '/check-email-422': { status: 422, body: JSON.stringify({ message: 'format salah' }) },
            '/check-email-empty-record': { body: JSON.stringify({}) },
            '/check-email-missing-user': { body: JSON.stringify({ exists: true }) },
        };

        server = await startJsonServer((rawUrl: string): IJsonServerResponse => {
            const path = new URL(rawUrl, 'http://127.0.0.1').pathname;

            return responses[path] ?? { status: 404, body: JSON.stringify({ message: 'Tidak ada.' }) };
        });
    });

    afterAll(async (): Promise<void> => {
        await server.close();
    });

    it('sends PATCH JSON for submission review', async () => {
        const result = await reviewFormSubmission({
            url: `${server.baseUrl}/submissions/1/review`,
            method: 'PATCH',
            reviewStatus: 'accepted',
        });

        expect(result.status).toBe(200);
        expect(result.message).toBeNull();
        expect(result.data?.registration_code).toBe('QR-1');
        expect(result.currentStatus).toBeNull();

        const request = server.requests.at(-1);
        expect(request?.method).toBe('PATCH');
        expect(request?.contentType).toContain('application/json');
        expect(JSON.parse(request?.body ?? '{}')).toEqual({ review_status: 'accepted' });
    });

    it('keeps a conflict status readable for submission review', async () => {
        const result = await reviewFormSubmission({
            url: `${server.baseUrl}/submissions/2/review`,
            method: 'PATCH',
            reviewStatus: 'rejected',
        });

        expect(result.status).toBe(409);
        expect(result.message).toBe('sudah');
    });

    it('posts builder fields and form settings with the expected verbs', async () => {
        await saveFormFields(`${server.baseUrl}/fields`, [{ name: 'judul' }]);
        await updateFormSettings(`${server.baseUrl}/form`, { title: 'Form' });

        const fieldsRequest = server.requests.at(-2);
        const formRequest = server.requests.at(-1);

        expect(fieldsRequest?.method).toBe('POST');
        expect(JSON.parse(fieldsRequest?.body ?? '{}')).toEqual({ fields: [{ name: 'judul' }] });
        expect(formRequest?.method).toBe('PUT');
        expect(JSON.parse(formRequest?.body ?? '{}')).toEqual({ title: 'Form' });
    });

    it('rejects a 2xx autosave response without the ok ack', async () => {
        await expect(saveFormFields(`${server.baseUrl}/fields-invalid-ack`, [])).rejects.toMatchObject({
            kind: 'invalid_response',
            status: 200,
        });
    });

    it('uploads builder images as multipart and returns path/url', async () => {
        const file = new File(['isi'], 'a.png', { type: 'image/png' });
        const result = await uploadBuilderImage(`${server.baseUrl}/builder-upload`, file);

        expect(result).toEqual({ path: 'form-images/a.png', url: '/storage/a.png' });
        expect(server.requests.at(-1)?.contentType).toContain('multipart/form-data');
    });

    it('drops a temporary data url from the builder upload and keeps the path', async () => {
        const file = new File(['isi'], 'a.png', { type: 'image/png' });
        const withPath = await uploadBuilderImage(`${server.baseUrl}/builder-upload-data`, file);

        expect(withPath).toEqual({ path: 'form-images/a.png', url: '' });
        await expect(uploadBuilderImage(`${server.baseUrl}/builder-upload-only-data`, file)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });

    it('rejects a broadcast upload that returns a temporary data url', async () => {
        const file = new File(['isi'], 'b.png', { type: 'image/png' });

        await expect(uploadBroadcastImage(`${server.baseUrl}/broadcast-upload-data`, file)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
        await expect(uploadBroadcastImage(`${server.baseUrl}/broadcast-upload`, file)).resolves.toBe('/storage/b.png');
    });

    it('returns lookup data with the HTTP status preserved', async () => {
        const found = await lookupRegisteredUser({ url: `${server.baseUrl}/check-email` });
        expect(found).toEqual({
            status: 200,
            exists: true,
            message: 'ditemukan',
            user: { name: 'A', email: 'a@x.test', created_at: '2026-01-01' },
        });

        const failed = await lookupRegisteredUser({ url: `${server.baseUrl}/check-email-422` });
        expect(failed.status).toBe(422);
        expect(failed.exists).toBe(false);
        expect(failed.message).toBe('format salah');
    });

    it('rejects a 200 lookup without exists as invalid_response', async () => {
        await expect(lookupRegisteredUser({ url: `${server.baseUrl}/check-email-empty-record` })).rejects.toMatchObject({
            kind: 'invalid_response',
            status: 200,
        });
    });

    it('rejects a 200 exists:true lookup without a usable user', async () => {
        await expect(lookupRegisteredUser({ url: `${server.baseUrl}/check-email-missing-user` })).rejects.toMatchObject({
            kind: 'invalid_response',
            status: 200,
        });
    });

    it('keeps a not-found lookup as data with status 404', async () => {
        const missing = await lookupRegisteredUser({ url: `${server.baseUrl}/unknown` });

        expect(missing.status).toBe(404);
        expect(missing.exists).toBe(false);
    });
});
