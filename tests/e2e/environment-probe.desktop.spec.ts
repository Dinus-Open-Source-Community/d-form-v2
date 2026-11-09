import { expect, test } from '@playwright/test';
import { DEFAULT_BASE_URL_TIMEOUT_MS, assertEnvironment } from './support/environment';

/**
 * Unit test pola e2e tanpa jaringan: probe base URL memakai `fetchImpl` tiruan.
 * `probeBaseUrl: false` tidak dipakai di test probe; environment lain tetap valid.
 */
const VALID_ENV: NodeJS.ProcessEnv = {
    E2E_BASE_URL: 'http://127.0.0.1:8020',
    E2E_ARTISAN: 'sh',
    E2E_ADMIN_EMAIL: 'admin@example.test',
    E2E_ADMIN_PASSWORD: 'not-a-real-password',
};

type TProbeMethod = 'HEAD' | 'GET';

/** Fetch tiruan yang mencatat urutan method; `respond` menentukan hasil tiap panggilan. */
function createProbeFetch(respond: (method: TProbeMethod, call: number) => Promise<Response>): {
    fetchImpl: typeof fetch;
    methods: TProbeMethod[];
} {
    const methods: TProbeMethod[] = [];

    const fetchImpl = (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
        const method: TProbeMethod = init?.method?.toUpperCase() === 'HEAD' ? 'HEAD' : 'GET';
        methods.push(method);

        return respond(method, methods.length);
    };

    return { fetchImpl, methods };
}

test.describe('H-environment-probe: HEAD dengan fallback GET dan retry', () => {
    test('default timeout lebih panjang dari 2000 ms', (): void => {
        expect(DEFAULT_BASE_URL_TIMEOUT_MS).toBeGreaterThan(2000);
    });

    test('HEAD sukses berhenti tanpa fallback', async (): Promise<void> => {
        const probe = createProbeFetch((): Promise<Response> => Promise.resolve(new Response(null, { status: 200 })));

        await assertEnvironment({ env: VALID_ENV, fetchImpl: probe.fetchImpl });

        expect(probe.methods).toEqual(['HEAD']);
    });

    test('HEAD 405 jatuh ke GET', async (): Promise<void> => {
        const probe = createProbeFetch((method: TProbeMethod): Promise<Response> =>
            Promise.resolve(new Response(null, { status: method === 'HEAD' ? 405 : 200 }))
        );

        await assertEnvironment({ env: VALID_ENV, fetchImpl: probe.fetchImpl });

        expect(probe.methods).toEqual(['HEAD', 'GET']);
    });

    test('HEAD timeout jatuh ke GET', async (): Promise<void> => {
        const probe = createProbeFetch((method: TProbeMethod): Promise<Response> => {
            if (method === 'HEAD') {
                return Promise.reject(new Error('timed out'));
            }

            return Promise.resolve(new Response(null, { status: 200 }));
        });

        await assertEnvironment({ env: VALID_ENV, fetchImpl: probe.fetchImpl });

        expect(probe.methods).toEqual(['HEAD', 'GET']);
    });

    test('gagal sekali lalu ulang sekali dan berhasil', async (): Promise<void> => {
        const probe = createProbeFetch((_method: TProbeMethod, call: number): Promise<Response> => {
            if (call < 4) {
                return Promise.reject(new Error('timed out'));
            }

            return Promise.resolve(new Response(null, { status: 200 }));
        });

        await assertEnvironment({ env: VALID_ENV, fetchImpl: probe.fetchImpl });

        expect(probe.methods).toEqual(['HEAD', 'GET', 'HEAD', 'GET']);
    });

    test('semua percobaan gagal melempar error E2E_BASE_URL setelah dua attempt', async (): Promise<void> => {
        const probe = createProbeFetch((): Promise<Response> => Promise.reject(new Error('connection refused')));

        await expect(assertEnvironment({ env: VALID_ENV, fetchImpl: probe.fetchImpl })).rejects.toThrow(
            /E2E_BASE_URL.*2 attempts/
        );

        expect(probe.methods).toEqual(['HEAD', 'GET', 'HEAD', 'GET']);
    });

    test('E2E_BASE_URL_TIMEOUT_MS tidak valid ditolak', async (): Promise<void> => {
        const probe = createProbeFetch((): Promise<Response> => Promise.resolve(new Response(null, { status: 200 })));

        await expect(
            assertEnvironment({
                env: { ...VALID_ENV, E2E_BASE_URL_TIMEOUT_MS: 'soon' },
                fetchImpl: probe.fetchImpl,
            })
        ).rejects.toThrow(/E2E_BASE_URL_TIMEOUT_MS/);
    });
});
