import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { checkEmailAvailability, requestPasswordResetLink, verifyOtpCode } from '@/adapters/http/auth';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle, IJsonServerResponse } from '@/adapters/http/__tests__/support/jsonServer';

describe('auth adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        const responses: Record<string, IJsonServerResponse> = {
            '/reset': { body: JSON.stringify({ message: 'terkirim' }) },
            '/availability': { body: JSON.stringify({ available: true }) },
            '/availability-invalid': { body: JSON.stringify({ available: 'ya' }) },
            '/otp-ok': { body: JSON.stringify({ verified: true, redirect: '/dashboard' }) },
            '/otp-open-redirect': { body: JSON.stringify({ verified: true, redirect: 'https://evil.test' }) },
            '/otp-protocol-relative': { body: JSON.stringify({ verified: true, redirect: '//evil.test' }) },
            '/otp-backslash': { body: JSON.stringify({ verified: true, redirect: '/\\evil.test' }) },
            '/otp-422': {
                status: 422,
                body: JSON.stringify({ message: 'Kode salah.' }),
                headers: { 'Retry-After': '12' },
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

    it('posts the reset email and returns the message', async () => {
        const result = await requestPasswordResetLink(`${server.baseUrl}/reset`, 'a@x.test');

        expect(result).toEqual({ message: 'terkirim' });
        expect(JSON.parse(server.requests.at(-1)?.body ?? '{}')).toEqual({ email: 'a@x.test' });
    });

    it('narrows the availability payload strictly', async () => {
        await expect(checkEmailAvailability(`${server.baseUrl}/availability`, 'a@x.test')).resolves.toEqual({
            available: true,
        });
        await expect(
            checkEmailAvailability(`${server.baseUrl}/availability-invalid`, 'a@x.test')
        ).rejects.toMatchObject({ kind: 'invalid_response' });
    });

    it('maps a successful OTP verification to an internal redirect', async () => {
        const result = await verifyOtpCode(`${server.baseUrl}/otp-ok`, '123456');

        expect(result.status).toBe(200);
        expect(result.verifiedRedirect).toBe('/dashboard');
        expect(result.message).toBeNull();
    });

    it('rejects an open redirect from the verification payload', async () => {
        const result = await verifyOtpCode(`${server.baseUrl}/otp-open-redirect`, '123456');

        expect(result.status).toBe(200);
        expect(result.verifiedRedirect).toBeNull();
    });

    it('rejects protocol-relative and backslash redirects', async () => {
        const protocolRelative = await verifyOtpCode(`${server.baseUrl}/otp-protocol-relative`, '123456');
        const backslash = await verifyOtpCode(`${server.baseUrl}/otp-backslash`, '123456');

        expect(protocolRelative.verifiedRedirect).toBeNull();
        expect(backslash.verifiedRedirect).toBeNull();
    });

    it('keeps 422 and Retry-After readable for the OTP screen', async () => {
        const result = await verifyOtpCode(`${server.baseUrl}/otp-422`, '123456');

        expect(result.status).toBe(422);
        expect(result.message).toBe('Kode salah.');
        expect(result.retryAfterSeconds).toBe(12);
    });
});
