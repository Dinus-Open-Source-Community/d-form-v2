import { expect, test, type Browser, type BrowserContext, type Response } from '@playwright/test';
import { readEnvironment } from './support/environment';

/**
 * E2E verifikasi allowlist `Host`/`X-Forwarded-Host` (`SEC-P3-012`).
 *
 * Yang diukur adalah perilaku HTTP dari luar: request dengan host di luar allowlist
 * ditolak `400` sebelum routing, host yang diizinkan tetap dilayani, dan URL absolut
 * tidak pernah memakai host kiriman klien.
 *
 * Batasan: browser tidak boleh menimpa header `Host` (Chromium menolak dengan
 * `ERR_INVALID_ARGUMENT`), jadi skenario `Host` palsu dikirim lewat HTTP client milik
 * Playwright (`context.request`), sedangkan `X-Forwarded-Host` — yang dipercaya karena
 * peer berasal dari jaringan privat Docker — dikirim lewat navigasi browser sungguhan.
 */
const environment = readEnvironment();

const baseUrl = new URL(environment.baseUrl);

/** Host + port sebagaimana dikirim browser di header `Host` (port non-default ikut). */
const ALLOWED_HOST = baseUrl.host;

/** Origin yang diizinkan, dipakai untuk memastikan redirect tidak keluar dari origin. */
const ALLOWED_ORIGIN = `${baseUrl.protocol}//${ALLOWED_HOST}`;

/** Host yang dikendalikan penyerang; tidak boleh pernah muncul di respons mana pun. */
const FORGED_HOST = 'evil.example';

/** Penanda halaman error framework; bukan bagian dari kontrak respons kita. */
const ERROR_PAGE_MARKERS: readonly string[] = ['Whoops', 'Stack trace'];

/** Nama kelas framework pada stack trace; dipakai untuk mendeteksi kebocoran internal. */
const FRAMEWORK_CLASS_PATTERN = /Illuminate\\[A-Za-z]/;

/** Konteks guest bersih: tanpa cookie dan tanpa storageState project. */
async function createGuestContext(browser: Browser): Promise<BrowserContext> {
    return browser.newContext({
        baseURL: environment.baseUrl,
        storageState: { cookies: [], origins: [] },
    });
}

/** Respons wajib ada; `page.goto` boleh mengembalikan null. */
function requireResponse(response: Response | null, label: string): Response {
    if (response === null) {
        throw new Error(`Navigasi "${label}" tidak menghasilkan respons HTTP.`);
    }

    return response;
}

/** Bentuk minimal respons yang dipakai assertion; dipenuhi `Response` maupun `APIResponse`. */
interface IHttpResponseProbe {
    status(): number;
    headers(): Record<string, string>;
    text(): Promise<string>;
}

/** Pastikan body penolakan tidak membocorkan host kiriman klien maupun detail internal. */
async function expectOpaqueBadRequest(response: IHttpResponseProbe, label: string): Promise<void> {
    expect(response.status(), `${label}: host asing harus ditolak 400`).toBe(400);
    expect(response.headers()['location'], `${label}: penolakan tidak boleh mengarahkan ulang`).toBeUndefined();

    const body = await response.text();

    expect(body, `${label}: body tidak boleh memantulkan host penyerang`).not.toContain(FORGED_HOST);
    expect(body.toLowerCase(), `${label}: body harus publik-aman`).toContain('bad request');

    for (const marker of ERROR_PAGE_MARKERS) {
        expect(body, `${label}: body tidak boleh memuat "${marker}"`).not.toContain(marker);
    }

    expect(FRAMEWORK_CLASS_PATTERN.test(body), `${label}: body tidak boleh memuat kelas framework`).toBe(false);
}

test('halaman publik tetap sehat pada host yang diizinkan', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const page = await context.newPage();
        const pageErrors: string[] = [];

        page.on('pageerror', (error: Error): void => {
            pageErrors.push(error.message);
        });

        const home = requireResponse(await page.goto('/', { waitUntil: 'domcontentloaded' }), 'GET /');
        expect(home.status(), 'GET / pada host yang diizinkan').toBe(200);

        const events = requireResponse(await page.goto('/events', { waitUntil: 'domcontentloaded' }), 'GET /events');
        expect(events.status(), 'GET /events pada host yang diizinkan').toBe(200);
        await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
        expect(pageErrors, `page error: ${JSON.stringify(pageErrors)}`).toEqual([]);
    } finally {
        await context.close();
    }
});

test('Host asing ditolak 400 tanpa memantulkan host penyerang', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        for (const path of ['/dashboard', '/', '/up']) {
            const response = await context.request.get(path, {
                headers: { Host: FORGED_HOST },
                maxRedirects: 0,
                failOnStatusCode: false,
            });

            await expectOpaqueBadRequest(response, `GET ${path} dengan Host ${FORGED_HOST}`);
        }
    } finally {
        await context.close();
    }
});

test('redirect guest memakai host yang diizinkan, bukan Host kiriman klien', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const allowed = await context.request.get('/dashboard', {
            headers: { Host: ALLOWED_HOST },
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        expect(allowed.status(), 'guest pada host yang diizinkan tetap diarahkan ke login').toBe(302);

        const location = allowed.headers()['location'] ?? '';
        expect(location.startsWith(`${ALLOWED_ORIGIN}/`), `Location "${location}" harus di origin yang diizinkan`).toBe(
            true
        );

        const forged = await context.request.get('/dashboard', {
            headers: { Host: FORGED_HOST },
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        await expectOpaqueBadRequest(forged, 'guest dengan Host asing');
    } finally {
        await context.close();
    }
});

test('variasi host asing: subdomain, prefix, port, dan host terlalu panjang', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        interface IForgedHostCase {
            host: string;
            /**
             * Host dengan sintaks tidak valid (`:` di tengah) sudah ditolak Symfony di dalam
             * `TrustProxies` sebelum middleware host berjalan; profil lokal `APP_DEBUG=true`
             * merender halaman debug framework (artefak dev, bukan kebocoran produksi).
             * Untuk kasus itu hanya status yang diukur.
             */
            expectPlainBody: boolean;
        }

        const forgedHosts: readonly IForgedHostCase[] = [
            { host: `api.${ALLOWED_HOST}`, expectPlainBody: true },
            { host: `evil-${ALLOWED_HOST}`, expectPlainBody: true },
            { host: `${FORGED_HOST}:8443`, expectPlainBody: true },
            { host: `${ALLOWED_HOST}.${FORGED_HOST}`, expectPlainBody: false },
            { host: 'a'.repeat(300), expectPlainBody: true },
        ];

        for (const testCase of forgedHosts) {
            const response = await context.request.get('/up', {
                headers: { Host: testCase.host },
                maxRedirects: 0,
                failOnStatusCode: false,
            });

            expect(response.status(), `Host "${testCase.host.slice(0, 40)}" harus 400, bukan 5xx`).toBe(400);

            if (!testCase.expectPlainBody) {
                continue;
            }

            expect(await response.text(), `Host "${testCase.host.slice(0, 40)}": body publik-aman`).not.toContain(
                FORGED_HOST
            );
        }

        const upperCase = await context.request.get('/up', {
            headers: { Host: ALLOWED_HOST.toUpperCase() },
            failOnStatusCode: false,
        });

        expect(upperCase.status(), 'host yang diizinkan bersifat case-insensitive').toBe(200);
    } finally {
        await context.close();
    }
});

test('X-Forwarded-Host asing dari peer tepercaya ditolak di navigasi browser', async ({ browser }) => {
    const context = await createGuestContext(browser);

    await context.setExtraHTTPHeaders({ 'X-Forwarded-Host': FORGED_HOST });

    try {
        const page = await context.newPage();
        const response = requireResponse(
            await page.goto('/events', { waitUntil: 'domcontentloaded' }).catch(() => null),
            'GET /events dengan X-Forwarded-Host asing'
        );

        await expectOpaqueBadRequest(response, 'navigasi dengan X-Forwarded-Host asing');
        expect(new URL(page.url()).origin, 'browser tidak boleh dilarikan ke host penyerang').toBe(ALLOWED_ORIGIN);
    } finally {
        await context.close();
    }
});

test('X-Forwarded-Host yang diizinkan tetap dilayani', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const response = await context.request.get('/up', {
            headers: { 'X-Forwarded-Host': ALLOWED_HOST },
            failOnStatusCode: false,
        });

        expect(response.status(), 'X-Forwarded-Host yang diizinkan tidak boleh ditolak').toBe(200);
    } finally {
        await context.close();
    }
});

test('penolakan host tidak meninggalkan state yang merusak request berikutnya', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const forged = await context.request.get('/up', {
            headers: { Host: FORGED_HOST },
            failOnStatusCode: false,
        });

        await expectOpaqueBadRequest(forged, 'request host asing sebelum request normal');

        const allowed = await context.request.get('/up', { failOnStatusCode: false });
        expect(allowed.status(), 'request normal setelah penolakan harus tetap 200').toBe(200);
    } finally {
        await context.close();
    }
});
