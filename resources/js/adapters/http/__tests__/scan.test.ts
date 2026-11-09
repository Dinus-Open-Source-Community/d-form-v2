import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { HttpRequestError, isHttpRequestError } from '@/adapters/http/httpError';
import { fetchScanFeed, submitScan } from '@/adapters/http/scan';
import { startJsonServer, type IJsonServerHandle, type IJsonServerResponse } from './support/jsonServer';

/** Ambil error dari promise yang diharapkan gagal tanpa melempar di test. */
async function catchError(promise: Promise<unknown>): Promise<unknown> {
    try {
        await promise;

        return null;
    } catch (error: unknown) {
        return error;
    }
}

describe('scan adapter', () => {
    let handle: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        handle = await startJsonServer((url: string): IJsonServerResponse => {
            if (url.startsWith('/envelope')) {
                return {
                    body: JSON.stringify({
                        type: 'event',
                        eventTitle: 'Event Uji',
                        attendee: { name: 'Budi', email: 'budi@example.test' },
                        status: 'success',
                        scannedAt: '2026-01-02T03:04:05Z',
                    }),
                };
            }

            if (url.startsWith('/invalid-envelope')) {
                return { body: JSON.stringify({ type: 'unknown', attendee: null }) };
            }

            if (url.startsWith('/conflict')) {
                return {
                    status: 409,
                    body: JSON.stringify({
                        message: 'Sudah pernah scan.',
                        type: 'recruitment',
                        eventTitle: 'Oprec · Divisi · 2026-01-02',
                        attendee: {
                            name: 'Sari',
                            registration_number: 'OPREC-1',
                            queue_number: 7,
                        },
                    }),
                };
            }

            if (url.startsWith('/validation')) {
                return {
                    status: 422,
                    body: JSON.stringify({
                        message: 'Data tidak valid.',
                        errors: { payload: ['Tidak dikenali.'] },
                    }),
                };
            }

            if (url.startsWith('/rate-limited')) {
                return {
                    status: 429,
                    headers: { 'Retry-After': '45' },
                    body: JSON.stringify({ message: 'Terlalu banyak.' }),
                };
            }

            if (url.startsWith('/feed-valid')) {
                return {
                    body: JSON.stringify({
                        cursor: '2026-01-02T03:04:05Z',
                        rows: [
                            {
                                id: 'row-1',
                                ts: '2026-01-02T03:04:05Z',
                                type: 'recruitment',
                                eventTitle: 'Oprec',
                                name: 'Budi',
                                identifier: 'budi@example.test',
                                queueNumber: 5,
                            },
                        ],
                    }),
                };
            }

            if (url.startsWith('/feed-invalid-rows')) {
                return {
                    body: JSON.stringify({
                        cursor: 'next',
                        rows: [
                            null,
                            'x',
                            { name: 'tanpa id' },
                            { id: '', ts: '2026-01-02T03:04:05Z', type: 'event' },
                            { id: 'missing-ts', type: 'event' },
                            { id: 'bad-type', ts: '2026-01-02T03:04:05Z', type: 'oprec' },
                            {
                                id: 'ok',
                                ts: '2026-01-02T03:04:05Z',
                                type: 'event',
                                eventTitle: 'Event Uji',
                                name: 'Sari',
                                identifier: 'sari@example.test',
                            },
                        ],
                    }),
                };
            }

            if (url.startsWith('/feed-all-invalid')) {
                return {
                    body: JSON.stringify({
                        cursor: 'next',
                        rows: [{ id: 'bad-type', ts: '2026-01-02T03:04:05Z', type: 'oprec' }],
                    }),
                };
            }

            if (url.startsWith('/feed-empty')) {
                return { body: JSON.stringify({ cursor: null, rows: [] }) };
            }

            return { body: JSON.stringify(['bukan record']) };
        });
    });

    afterAll(async (): Promise<void> => {
        await handle.close();
    });

    it('memetakan envelope sukses scan', async () => {
        const result = await submitScan({ url: `${handle.baseUrl}/envelope`, payload: { raw: 'x' } });

        expect(result.status).toBe(200);
        expect(result.envelope?.type).toBe('event');
        expect(result.envelope?.attendee.name).toBe('Budi');
        expect(result.errorBody).toBeNull();
    });

    it('menolak envelope yang tidak sesuai kontrak sebagai envelope null', async () => {
        const result = await submitScan({ url: `${handle.baseUrl}/invalid-envelope`, payload: { raw: 'x' } });

        expect(result.status).toBe(200);
        expect(result.envelope).toBeNull();
    });

    it('mempertahankan konteks attendee pada 409', async () => {
        const result = await submitScan({ url: `${handle.baseUrl}/conflict`, payload: { raw: 'x' } });

        expect(result.status).toBe(409);
        expect(result.envelope).toBeNull();
        expect(result.errorBody?.message).toBe('Sudah pernah scan.');
        expect(result.errorBody?.attendee?.registration_number).toBe('OPREC-1');
        expect(result.errorBody?.attendee?.queue_number).toBe(7);
    });

    it('membaca bag validasi 422', async () => {
        const result = await submitScan({ url: `${handle.baseUrl}/validation`, payload: { raw: 'x' } });

        expect(result.status).toBe(422);
        expect(result.errorBody?.errors).toEqual({ payload: ['Tidak dikenali.'] });
    });

    it('meneruskan Retry-After pada 429', async () => {
        const result = await submitScan({ url: `${handle.baseUrl}/rate-limited`, payload: { raw: 'x' } });

        expect(result.status).toBe(429);
        expect(result.retryAfterSeconds).toBe(45);
    });

    it('memvalidasi payload feed dan barisnya', async () => {
        const payload = await fetchScanFeed({ url: `${handle.baseUrl}/feed-valid`, cursor: '' });

        expect(payload.cursor).toBe('2026-01-02T03:04:05Z');
        expect(payload.rows).toEqual([
            {
                id: 'row-1',
                ts: '2026-01-02T03:04:05Z',
                type: 'recruitment',
                eventTitle: 'Oprec',
                name: 'Budi',
                identifier: 'budi@example.test',
                queueNumber: 5,
            },
        ]);
    });

    it('membuang baris feed yang tidak valid tanpa menggagalkan payload', async () => {
        const payload = await fetchScanFeed({ url: `${handle.baseUrl}/feed-invalid-rows`, cursor: '' });

        expect(payload.cursor).toBe('next');
        expect(payload.rows).toHaveLength(1);
        expect(payload.rows[0]?.id).toBe('ok');
    });

    it('menolak payload feed yang seluruh barisnya invalid sebagai invalid_response', async () => {
        const error = await catchError(fetchScanFeed({ url: `${handle.baseUrl}/feed-all-invalid`, cursor: '' }));

        expect(isHttpRequestError(error)).toBe(true);
        if (!isHttpRequestError(error)) return;
        expect(error.kind).toBe('invalid_response');
        expect(error.status).toBe(200);
    });

    it('menerima payload feed dengan rows kosong dan menormalkan cursor null', async () => {
        const payload = await fetchScanFeed({ url: `${handle.baseUrl}/feed-empty`, cursor: '' });

        expect(payload).toEqual({ cursor: '', rows: [] });
    });

    it('melempar invalid_response untuk payload feed 2xx yang bukan record', async () => {
        const error = await catchError(fetchScanFeed({ url: `${handle.baseUrl}/feed-malformed`, cursor: '' }));

        expect(isHttpRequestError(error)).toBe(true);
        expect(error).toBeInstanceOf(HttpRequestError);
        if (!isHttpRequestError(error)) return;
        expect(error.kind).toBe('invalid_response');
        expect(error.status).toBe(200);
    });
});
