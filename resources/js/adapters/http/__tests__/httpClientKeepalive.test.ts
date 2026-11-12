import { afterEach, describe, expect, it, vi } from 'vitest';
import { sendJsonKeepalive } from '@/adapters/http/httpClient';

/** Mock fetch bertipe supaya `mock.calls` tidak perlu di-cast. */
function stubFetch(handler: () => Promise<Response>): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn((input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
        void input;
        void init;
        return handler();
    });
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('sendJsonKeepalive', () => {
    it('mengirim JSON dengan keepalive dan header XSRF dari cookie', () => {
        const fetchMock = stubFetch((): Promise<Response> => Promise.resolve(new Response('{}')));
        vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=token%3D' });

        sendJsonKeepalive({ url: '/admin/events/e1/forms/f1/fields', method: 'POST', body: { fields: [] } });

        expect(fetchMock).toHaveBeenCalledTimes(1);

        const call = fetchMock.mock.calls[0];
        const init = call[1];
        const headers = new Headers(init?.headers);

        expect(call[0]).toBe('/admin/events/e1/forms/f1/fields');
        expect(init?.method).toBe('POST');
        expect(init?.keepalive).toBe(true);
        expect(init?.credentials).toBe('same-origin');
        expect(init?.body).toBe(JSON.stringify({ fields: [] }));
        expect(headers.get('Content-Type')).toBe('application/json');
        expect(headers.get('X-XSRF-TOKEN')).toBe('token=');
    });

    it('tidak menambahkan header XSRF saat cookie belum ada', () => {
        const fetchMock = stubFetch((): Promise<Response> => Promise.resolve(new Response('{}')));
        vi.stubGlobal('document', { cookie: '' });

        sendJsonKeepalive({ url: '/x', method: 'PUT', body: { title: 'A' } });

        const headers = new Headers(fetchMock.mock.calls[0][1]?.headers);

        expect(headers.get('X-XSRF-TOKEN')).toBeNull();
    });

    it('kegagalan transport tidak melempar ke pemanggil (halaman sudah ditinggalkan)', () => {
        stubFetch((): Promise<Response> => Promise.reject(new Error('aborted')));
        vi.stubGlobal('document', { cookie: '' });

        expect(() => sendJsonKeepalive({ url: '/x', body: {} })).not.toThrow();
    });
});
