import { randomInt } from 'node:crypto';
import { expect, test, type APIResponse, type Browser, type BrowserContext, type Response } from '@playwright/test';
import { readEnvironment } from './support/environment';

/**
 * E2E verifikasi TrustProxies di belakang ingress (`BE-P3-011`).
 *
 * Bukti yang diukur di sini adalah perilaku HTTP yang bisa diamati dari luar:
 * rate limit yang di-bucket per IP klien dari `X-Forwarded-For`, dan skema URL
 * absolut yang dihasilkan aplikasi saat `X-Forwarded-Proto: https` dikirim.
 *
 * Batasan cakupan: peer request dari browser/Playwright selalu berasal dari IP
 * privat Docker (default tepercaya), jadi skenario "peer TIDAK tepercaya" tidak
 * bisa disimulasikan dari E2E. Skenario itu dicakup
 * `tests/Feature/Security/TrustedProxyTest.php` (`REMOTE_ADDR` di luar daftar).
 */
const environment = readEnvironment();

const baseUrl = new URL(environment.baseUrl);

/** Host + port sebagaimana dikirim browser di header `Host` (port non-default ikut). */
const BASE_HOST = baseUrl.host;

/** URL absolut yang dihasilkan Boost dan mengikuti skema request server. */
const BOOST_MARKER = '/_boost/browser-logs';

/** Penanda halaman error framework; tidak boleh muncul di error publik. */
const ERROR_PAGE_MARKERS: readonly string[] = ['Whoops', 'Stack trace'];

/**
 * Rujukan kelas framework pada stack trace. Sengaja diikat ke karakter nama kelas:
 * debugbar profil lokal menulis path sebagai `Illuminate\/Foundation\/...` (escape JSON),
 * sehingga marker `Illuminate\` polos akan selalu positif palsu tanpa mengukur kebocoran.
 */
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

interface IClientAddressPair {
    first: string;
    second: string;
}

/**
 * IP klien unik per eksekusi di TEST-NET-3 (`203.0.113.0/24`, RFC 5737) supaya
 * bucket rate limit tidak bertabrakan dengan spec lain atau retry sebelumnya.
 */
function createClientAddress(): string {
    return `203.0.113.${randomInt(1, 240)}`;
}

/** Dua IP klien berbeda untuk membandingkan bucket rate limit pada eksekusi yang sama. */
function createClientAddressPair(): IClientAddressPair {
    const start = randomInt(1, 240);

    return { first: `203.0.113.${start}`, second: `203.0.113.${start + 4}` };
}

/** Daftar `X-Forwarded-For` panjang untuk menguji batas parsing header. */
function forwardedForEntries(count: number): string {
    return Array.from(
        { length: count },
        (_value: unknown, index: number): string => `198.51.100.${(index % 250) + 1}`
    ).join(', ');
}

interface IRateLimitProbe {
    limit: string;
    remaining: number;
}

/** Satu request `/events` sebagai klien `clientIp`, mengembalikan header rate limit-nya. */
async function readRateLimit(context: BrowserContext, clientIp: string): Promise<IRateLimitProbe> {
    const response = await context.request.get('/events', {
        headers: { 'X-Forwarded-For': clientIp },
    });

    expect(response.status(), `GET /events dengan X-Forwarded-For ${clientIp}`).toBe(200);

    const headers = response.headers();
    const limit = headers['x-ratelimit-limit'];
    const remaining = headers['x-ratelimit-remaining'];

    expect(limit, 'x-ratelimit-limit wajib ada pada respons /events').toBeDefined();
    expect(remaining, 'x-ratelimit-remaining wajib ada pada respons /events').toBeDefined();

    if (limit === undefined || remaining === undefined) {
        throw new Error('Header rate limit tidak lengkap pada respons /events.');
    }

    const remainingCount = Number(remaining);

    expect(Number.isFinite(remainingCount), `x-ratelimit-remaining "${remaining}" harus berupa angka`).toBe(true);

    return { limit, remaining: remainingCount };
}

/**
 * Body respons aplikasi tidak boleh memuat stack trace atau nama kelas internal framework.
 *
 * Hanya dipakai untuk respons yang dirender aplikasi sendiri (status < 400). Profil lokal
 * berjalan dengan `APP_DEBUG=true`, jadi exception yang ditolak sebelum routing (host invalid)
 * memang dirender sebagai halaman debug framework berisi trace; itu artefak profil, bukan
 * kebocoran produk. Untuk respons error cukup dipastikan klasifikasinya klien (bukan 500).
 */
async function expectNoStackTrace(response: APIResponse, label: string): Promise<void> {
    const body = await response.text();

    for (const marker of ERROR_PAGE_MARKERS) {
        expect(body, `${label}: body tidak boleh memuat "${marker}"`).not.toContain(marker);
    }

    expect(FRAMEWORK_CLASS_PATTERN.test(body), `${label}: body tidak boleh memuat nama kelas Illuminate\\...`).toBe(
        false
    );
}

test('rate limit /events di-bucket per IP klien dari X-Forwarded-For', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const { first: firstIp, second: secondIp } = createClientAddressPair();

        const first = await readRateLimit(context, firstIp);
        const repeated = await readRateLimit(context, firstIp);
        const otherClient = await readRateLimit(context, secondIp);

        expect(first.remaining, 'request pertama belum boleh menghabiskan bucket').toBeGreaterThan(0);
        expect(repeated.remaining, 'request kedua dari IP yang sama menurun tepat satu').toBe(first.remaining - 1);
        expect(otherClient.remaining, 'IP klien lain punya bucket sendiri (bukan dihitung global)').toBe(
            first.remaining
        );
        expect(otherClient.limit, 'batas per IP konsisten').toBe(first.limit);
    } finally {
        await context.close();
    }
});

test('halaman /events tetap sehat saat browser mengirim X-Forwarded-For', async ({ browser }) => {
    const context = await createGuestContext(browser);
    const clientIp = createClientAddress();

    await context.setExtraHTTPHeaders({ 'X-Forwarded-For': clientIp });

    try {
        const page = await context.newPage();
        const pageErrors: string[] = [];

        page.on('pageerror', (error: Error): void => {
            pageErrors.push(error.message);
        });

        const response = requireResponse(await page.goto('/events', { waitUntil: 'domcontentloaded' }), '/events');

        expect(response.status(), 'GET /events (browser, header forwarded)').toBe(200);
        await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
        expect(pageErrors, `page error: ${JSON.stringify(pageErrors)}`).toEqual([]);
    } finally {
        await context.close();
    }
});

test('X-Forwarded-Proto: https membuat URL absolut aplikasi berskema https', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const plain = await context.request.get('/');
        expect(plain.status(), 'GET / tanpa header forwarded').toBe(200);

        const plainBody = await plain.text();

        // Boost hanya aktif di profil tertentu. Bila penandanya tidak ada, skema tidak bisa
        // diukur dari body dan test di-skip alih-alih memakai assert yang selalu lolos.
        test.skip(
            !plainBody.includes(BOOST_MARKER),
            `Penanda "${BOOST_MARKER}" tidak ada pada GET / (Boost nonaktif).`
        );

        const forwarded = await context.request.get('/', {
            headers: { 'X-Forwarded-Proto': 'https' },
        });
        expect(forwarded.status(), 'GET / dengan X-Forwarded-Proto: https').toBe(200);

        const forwardedBody = await forwarded.text();

        expect(plainBody, 'tanpa header, URL absolut memakai skema http').toContain(
            `http://${BASE_HOST}${BOOST_MARKER}`
        );
        expect(plainBody, 'tanpa header, URL absolut tidak boleh berskema https').not.toContain(
            `https://${BASE_HOST}${BOOST_MARKER}`
        );
        expect(forwardedBody, 'dengan header forwarded, URL absolut menjadi https').toContain(
            `https://${BASE_HOST}${BOOST_MARKER}`
        );
    } finally {
        await context.close();
    }
});

interface IForwardedAnomaly {
    label: string;
    path: string;
    headers: Record<string, string>;
    status: number;
}

test('header forwarded anomali tidak menghasilkan 500 atau stack trace', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const anomalies: readonly IForwardedAnomaly[] = [
            {
                label: 'X-Forwarded-For bukan IP',
                path: '/events',
                headers: { 'X-Forwarded-For': 'not-an-ip' },
                status: 200,
            },
            {
                label: 'X-Forwarded-For 300 entri',
                path: '/events',
                headers: { 'X-Forwarded-For': forwardedForEntries(300) },
                status: 200,
            },
            {
                label: 'X-Forwarded-Proto javascript://',
                path: '/',
                headers: { 'X-Forwarded-Proto': 'javascript://evil' },
                status: 200,
            },
            {
                label: 'X-Forwarded-Proto kosong',
                path: '/',
                headers: { 'X-Forwarded-Proto': '' },
                status: 200,
            },
            {
                label: 'X-Forwarded-Host invalid',
                path: '/',
                headers: { 'X-Forwarded-Host': 'evil.example/x?a=1' },
                status: 400,
            },
        ];

        for (const anomaly of anomalies) {
            const response = await context.request.get(anomaly.path, { headers: anomaly.headers });

            expect(response.status(), `${anomaly.label}: status ${anomaly.path}`).toBe(anomaly.status);
            expect(response.status(), `${anomaly.label}: tidak boleh 500`).toBeLessThan(500);

            if (response.status() >= 400) {
                // Host invalid ditolak sebelum routing; profil lokal (`APP_DEBUG=true`) merender
                // halaman debug framework, jadi yang dipastikan adalah klasifikasi 400 + bukan
                // renderer error generik, bukan ketiadaan trace di halaman debug.
                expect(await response.text(), `${anomaly.label}: bukan halaman error generik`).not.toContain('Whoops');

                continue;
            }

            await expectNoStackTrace(response, anomaly.label);
        }
    } finally {
        await context.close();
    }
});

test('health check /up tetap 200 dengan header forwarded anomali', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        // `X-Forwarded-Host` invalid sengaja tidak dipakai: validasi host Symfony berjalan sebelum
        // routing dan menolak dengan 400 (sudah diuji di test anomali), bukan kegagalan aplikasi.
        const headerSets: ReadonlyArray<{ label: string; headers: Record<string, string> }> = [
            { label: 'XFF bukan IP', headers: { 'X-Forwarded-For': 'not-an-ip' } },
            { label: 'XFF 300 entri', headers: { 'X-Forwarded-For': forwardedForEntries(300) } },
            { label: 'proto javascript://', headers: { 'X-Forwarded-Proto': 'javascript://evil' } },
            { label: 'proto kosong', headers: { 'X-Forwarded-Proto': '' } },
        ];

        for (const headerSet of headerSets) {
            const response = await context.request.get('/up', { headers: headerSet.headers });

            expect(response.status(), `/up dengan ${headerSet.label}`).toBe(200);
            await expectNoStackTrace(response, `/up dengan ${headerSet.label}`);
        }
    } finally {
        await context.close();
    }
});
