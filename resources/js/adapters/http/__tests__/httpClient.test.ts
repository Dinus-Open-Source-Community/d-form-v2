import { createServer } from 'node:http';
import type { IncomingMessage, Server, ServerResponse } from 'node:http';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { HttpRequestError, isHttpRequestError, readValidationErrors } from '@/adapters/http/httpError';
import { probePageUrl, requestBlob, requestJson, requestJsonWithStatus } from '@/adapters/http/httpClient';

interface IServerHandle {
    baseUrl: string;
    close: () => Promise<void>;
}

/** Server HTTP lokal kecil; menghindari mock axios agar kontrak benar-benar diuji. */
function startServer(): Promise<IServerHandle> {
    const server: Server = createServer((request: IncomingMessage, response: ServerResponse): void => {
        const url = request.url ?? '/';

        if (url === '/ok') {
            response.writeHead(200, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ value: 'ok' }));
            return;
        }

        if (url === '/invalid') {
            response.writeHead(200, { 'Content-Type': 'application/json' });
            response.end('"bukan objek"');
            return;
        }

        if (url === '/validation') {
            response.writeHead(422, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ message: 'Data tidak valid.', errors: { email: ['Wajib diisi'] } }));
            return;
        }

        if (url === '/server-error') {
            response.writeHead(500, { 'Content-Type': 'text/plain' });
            response.end('boom');
            return;
        }

        if (url === '/rate-limited') {
            response.writeHead(429, { 'Content-Type': 'application/json', 'Retry-After': '45' });
            response.end(JSON.stringify({ message: 'Terlalu banyak.' }));
            return;
        }

        if (url === '/redirect') {
            response.writeHead(302, { Location: '/ok' });
            response.end();
            return;
        }

        if (url === '/binary') {
            response.writeHead(200, { 'Content-Type': 'application/octet-stream' });
            response.end('berkas');
            return;
        }

        if (url === '/slow') {
            setTimeout((): void => {
                response.writeHead(200, { 'Content-Type': 'application/json' });
                response.end(JSON.stringify({ value: 'lambat' }));
            }, 300);
            return;
        }

        if (url === '/echo-form') {
            response.writeHead(200, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ received: request.headers['content-type'] ?? '' }));
            return;
        }

        response.writeHead(404, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ message: 'Tidak ada.' }));
    });

    return new Promise((resolve): void => {
        server.listen(0, '127.0.0.1', (): void => {
            const address = server.address();
            const port = typeof address === 'object' && address !== null ? address.port : 0;

            resolve({
                baseUrl: `http://127.0.0.1:${port}`,
                close: (): Promise<void> =>
                    new Promise((done): void => {
                        server.close((): void => done());
                    }),
            });
        });
    });
}

/** Cek record non-array untuk payload JSON dinamis. */
function isRecordValue(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Parser longgar untuk test: objek apa pun diterima. */
function parseRecord(payload: unknown): Record<string, unknown> | null {
    return isRecordValue(payload) ? payload : null;
}

describe('httpClient', () => {
    let handle: IServerHandle;

    beforeAll(async (): Promise<void> => {
        handle = await startServer();
    });

    afterAll(async (): Promise<void> => {
        await handle.close();
    });

    it('returns a parsed payload for 2xx', async () => {
        const result = await requestJson({ url: `${handle.baseUrl}/ok` }, parseRecord);

        expect(result).toEqual({ value: 'ok' });
    });

    it('rejects a payload that does not match the parser', async () => {
        await expect(requestJson({ url: `${handle.baseUrl}/invalid` }, parseRecord)).rejects.toMatchObject({
            kind: 'invalid_response',
            status: 200,
        });
    });

    it('maps 422 into a validation error with the error bag', async () => {
        const error: unknown = await requestJson({ url: `${handle.baseUrl}/validation` }, parseRecord).catch(
            (thrown: unknown) => thrown
        );

        expect(isHttpRequestError(error)).toBe(true);
        if (!isHttpRequestError(error)) return;

        expect(error.kind).toBe('validation');
        expect(error.status).toBe(422);
        expect(error.message).toBe('Data tidak valid.');
        expect(error.validationErrors).toEqual({ email: ['Wajib diisi'] });
    });

    it('maps 500 and non-JSON bodies into a server error', async () => {
        const error: unknown = await requestJson({ url: `${handle.baseUrl}/server-error` }, parseRecord).catch(
            (thrown: unknown) => thrown
        );

        expect(isHttpRequestError(error)).toBe(true);
        if (!isHttpRequestError(error)) return;

        expect(error.kind).toBe('server');
        expect(error.status).toBe(500);
        expect(error.validationErrors).toBeNull();
    });

    it('surfaces Retry-After for rate limiting', async () => {
        const error: unknown = await requestJson({ url: `${handle.baseUrl}/rate-limited` }, parseRecord).catch(
            (thrown: unknown) => thrown
        );

        expect(isHttpRequestError(error)).toBe(true);
        if (!isHttpRequestError(error)) return;

        expect(error.kind).toBe('rate_limited');
        expect(error.retryAfterSeconds).toBe(45);
    });

    it('keeps HTTP status readable without throwing', async () => {
        const result = await requestJsonWithStatus({ url: `${handle.baseUrl}/validation` }, parseRecord);

        expect(result.status).toBe(422);
        expect(result.data).toEqual({ message: 'Data tidak valid.', errors: { email: ['Wajib diisi'] } });
        expect(readValidationErrors(result.data)).toEqual({ email: ['Wajib diisi'] });
    });

    it('aborts with kind aborted instead of a network error', async () => {
        const controller = new AbortController();
        const pending = requestJson({ url: `${handle.baseUrl}/slow`, signal: controller.signal }, parseRecord);
        controller.abort();

        const error: unknown = await pending.catch((thrown: unknown) => thrown);

        expect(isHttpRequestError(error)).toBe(true);
        if (!isHttpRequestError(error)) return;
        expect(error.kind).toBe('aborted');
    });

    it('marks unreachable hosts as network errors', async () => {
        const error: unknown = await requestJson({ url: 'http://127.0.0.1:1/ok' }, parseRecord).catch(
            (thrown: unknown) => thrown
        );

        expect(error instanceof HttpRequestError).toBe(true);
        if (!(error instanceof HttpRequestError)) return;
        expect(error.kind).toBe('network');
        expect(error.status).toBeNull();
    });

    it('downloads a file as blob', async () => {
        const blob = await requestBlob(`${handle.baseUrl}/binary`);

        expect(blob.size).toBeGreaterThan(0);
    });

    it('follows redirects when probing a page url', async () => {
        const finalUrl = await probePageUrl(`${handle.baseUrl}/redirect`);

        expect(finalUrl.endsWith('/ok')).toBe(true);
    });
});
