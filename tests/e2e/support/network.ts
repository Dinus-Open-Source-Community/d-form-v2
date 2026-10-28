import type { Page, Request, Response } from '@playwright/test';

/** Bentuk satu respons stub untuk mensimulasikan kegagalan/response server. */
export interface IStubResponse {
    status: number;
    contentType?: string;
    body?: string;
    headers?: Record<string, string>;
    delayMs?: number;
}

/** Log request/response yang bisa diquery tanpa menyentuh detail internal Playwright. */
export interface IRequestLog {
    requestCount: (method: string, pattern: string | RegExp) => number;
    responseStatuses: (pattern: string | RegExp) => number[];
    waitForRequestCount: (method: string, pattern: string | RegExp, expected: number, timeoutMs?: number) => Promise<void>;
    stop: () => void;
}

interface ILoggedRequest {
    method: string;
    url: string;
}

interface ILoggedResponse {
    url: string;
    status: number;
}

interface IInertiaPage {
    component: string;
    props: unknown;
    url: string;
}

/**
 * Mulai mencatat request/response halaman; `stop()` wajib dipanggil agar listener lepas.
 * Dipakai assertion jumlah POST/status tanpa membaca log server.
 */
export function startRequestLog(page: Page): IRequestLog {
    const requests: ILoggedRequest[] = [];
    const responses: ILoggedResponse[] = [];

    const onRequest = (request: Request): void => {
        requests.push({ method: request.method().toUpperCase(), url: request.url() });
    };
    const onResponse = (response: Response): void => {
        responses.push({ url: response.url(), status: response.status() });
    };

    page.on('request', onRequest);
    page.on('response', onResponse);

    const requestCount = (method: string, pattern: string | RegExp): number =>
        requests.filter(
            (entry: ILoggedRequest): boolean =>
                entry.method === method.toUpperCase() && matches(entry.url, pattern),
        ).length;

    return {
        requestCount,
        responseStatuses: (pattern: string | RegExp): number[] =>
            responses
                .filter((entry: ILoggedResponse): boolean => matches(entry.url, pattern))
                .map((entry: ILoggedResponse): number => entry.status),
        waitForRequestCount: async (
            method: string,
            pattern: string | RegExp,
            expected: number,
            timeoutMs: number = 10_000,
        ): Promise<void> => {
            const deadline = Date.now() + timeoutMs;

            while (Date.now() < deadline) {
                if (requestCount(method, pattern) >= expected) {
                    return;
                }

                await new Promise<void>((done: () => void): void => {
                    setTimeout(done, 100);
                });
            }

            throw new Error(
                `Timed out waiting for ${expected} ${method.toUpperCase()} request(s) matching ${String(pattern)}; saw ${requestCount(method, pattern)}.`,
            );
        },
        stop: (): void => {
            page.off('request', onRequest);
            page.off('response', onResponse);
        },
    };
}

/** Stub satu URL dengan response deterministik (5xx, HTML 200, redirect tak dikenal, timeout). */
export async function stubStoreResponse(
    page: Page,
    url: string | RegExp,
    response: IStubResponse,
): Promise<void> {
    await page.route(url, async (route): Promise<void> => {
        if (response.delayMs !== undefined && response.delayMs > 0) {
            await new Promise<void>((done: () => void): void => {
                setTimeout(done, response.delayMs);
            });
        }

        await route.fulfill({
            status: response.status,
            contentType: response.contentType,
            body: response.body,
            headers: response.headers,
        });
    });
}

/** Baca props Inertia dari atribut `data-page` root aplikasi tanpa menebak DOM. */
export async function readPageProps<TProps>(page: Page): Promise<TProps> {
    const raw = await page.locator('#app').getAttribute('data-page');

    if (raw === null) {
        throw new Error('Inertia root #app has no data-page attribute.');
    }

    const parsed: unknown = JSON.parse(raw);

    if (typeof parsed !== 'object' || parsed === null) {
        throw new Error('Inertia data-page is not an object.');
    }

    const pagePayload = parsed as IInertiaPage;

    return pagePayload.props as TProps;
}

/** Cocokkan URL dengan string akhir atau RegExp. */
function matches(url: string, pattern: string | RegExp): boolean {
    return pattern instanceof RegExp ? pattern.test(url) : url.endsWith(pattern);
}
