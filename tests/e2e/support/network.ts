import type { BrowserContext, Page, Request, Response } from '@playwright/test';

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
    /** Status >= 500, opsional difilter URL; pengganti listener 5xx ad-hoc di spec. */
    serverErrorStatuses: (pattern?: string | RegExp) => number[];
    waitForRequestCount: (
        method: string,
        pattern: string | RegExp,
        expected: number,
        timeoutMs?: number
    ) => Promise<void>;
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

export interface IInertiaPage {
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
            (entry: ILoggedRequest): boolean => entry.method === method.toUpperCase() && matches(entry.url, pattern)
        ).length;

    return {
        requestCount,
        responseStatuses: (pattern: string | RegExp): number[] =>
            responses
                .filter((entry: ILoggedResponse): boolean => matches(entry.url, pattern))
                .map((entry: ILoggedResponse): number => entry.status),
        serverErrorStatuses: (pattern?: string | RegExp): number[] =>
            responses
                .filter(
                    (entry: ILoggedResponse): boolean =>
                        entry.status >= 500 && (pattern === undefined || matches(entry.url, pattern))
                )
                .map((entry: ILoggedResponse): number => entry.status),
        waitForRequestCount: async (
            method: string,
            pattern: string | RegExp,
            expected: number,
            timeoutMs: number = 10_000
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
                `Timed out waiting for ${expected} ${method.toUpperCase()} request(s) matching ${String(pattern)}; saw ${requestCount(method, pattern)}.`
            );
        },
        stop: (): void => {
            page.off('request', onRequest);
            page.off('response', onResponse);
        },
    };
}

/** Stub satu URL dengan response deterministik (5xx, HTML 200, redirect tak dikenal, timeout). */
export async function stubStoreResponse(page: Page, url: string | RegExp, response: IStubResponse): Promise<void> {
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

/**
 * Baca payload Inertia yang dirender server. Inertia 3 menaruh payload di
 * `script[data-page="app"]`; atribut `data-page` pada `#app` hanya ada di
 * versi lama dan hilang setelah hidrasi. Payload di script tidak berubah
 * setelah navigasi client-side, jadi URL-nya dipakai sebagai penanda.
 */
export async function readInertiaPage(page: Page): Promise<IInertiaPage> {
    const payload = await readInertiaPayload(page);

    if (!samePath(payload.url, page.url())) {
        await page.reload({ waitUntil: 'domcontentloaded' });

        const reloaded = await readInertiaPayload(page);

        if (!samePath(reloaded.url, page.url())) {
            throw new Error(
                `Inertia payload URL "${reloaded.url}" still does not match page URL "${page.url()}" after reload.`
            );
        }

        return reloaded;
    }

    return payload;
}

/** Baca props Inertia halaman saat ini tanpa menebak DOM terhidrasi. */
export async function readPageProps<TProps>(page: Page): Promise<TProps> {
    return (await readInertiaPage(page)).props as TProps;
}

async function readInertiaPayload(page: Page): Promise<IInertiaPage> {
    const script = page.locator('script[data-page="app"][type="application/json"]').first();
    const raw =
        (await script.count()) > 0 ? await script.textContent() : await page.locator('#app').getAttribute('data-page');

    if (raw === null) {
        throw new Error('Inertia page payload is missing from script[data-page="app"] and #app[data-page].');
    }

    const parsed: unknown = JSON.parse(raw);

    if (
        typeof parsed !== 'object' ||
        parsed === null ||
        !('component' in parsed) ||
        !('props' in parsed) ||
        !('url' in parsed) ||
        typeof parsed.component !== 'string' ||
        typeof parsed.url !== 'string'
    ) {
        throw new Error('Inertia page payload is not an object with string component/url and props.');
    }

    return {
        component: parsed.component,
        props: parsed.props,
        url: parsed.url,
    };
}

function samePath(left: string, right: string): boolean {
    return pathOf(left) === pathOf(right);
}

function pathOf(value: string): string {
    try {
        const url = new URL(value, 'http://localhost');

        // Query ikut dibandingkan: Inertia partial visit `/events?q=x` memakai
        // pathname yang sama tetapi payload-nya berbeda.
        return `${url.pathname}${url.search}`;
    } catch {
        return value;
    }
}

/**
 * Token CSRF dari cookie context. Satu implementasi untuk spec yang mengirim
 * POST langsung lewat `page.request`/`context.request`.
 */
export async function readXsrfToken(context: BrowserContext): Promise<string> {
    const cookie = (await context.cookies()).find((entry) => entry.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        throw new Error('XSRF-TOKEN cookie is missing; open a page before posting.');
    }

    return decodeURIComponent(cookie.value);
}

/** Cocokkan URL dengan string akhir atau RegExp. */
function matches(url: string, pattern: string | RegExp): boolean {
    return pattern instanceof RegExp ? pattern.test(url) : url.endsWith(pattern);
}
