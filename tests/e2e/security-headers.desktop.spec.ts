import {
    expect,
    test,
    type Browser,
    type BrowserContext,
    type Page,
    type Response,
} from '@playwright/test';
import { sessionCookieName } from './support/authGuard';
import { readEnvironment } from './support/environment';

/**
 * E2E verifikasi header keamanan global (`App\Http\Middleware\SecurityHeaders`, BE-P3-009).
 *
 * Hanya project `chromium-desktop`: header adalah metadata respons HTTP dan tidak
 * bergantung pada viewport, jadi tidak ada spec mobile untuk tiket ini.
 *
 * Profil lokal (APP_ENV=local, Vite dev): HSTS tidak dikirim dan cookie sesi tidak
 * `Secure` karena trafik lewat http. Keduanya sengaja TIDAK diassert di sini;
 * perilaku produksinya sudah dicakup PHPUnit.
 */
const environment = readEnvironment();

/**
 * Header baseline yang wajib ada persis sama di setiap respons.
 * Nilai diambil dari `config/security.php` (`headers`).
 */
const BASELINE_HEADERS: Readonly<Record<string, string>> = {
    'x-content-type-options': 'nosniff',
    'x-frame-options': 'SAMEORIGIN',
    'referrer-policy': 'strict-origin-when-cross-origin',
    'permissions-policy': 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
};

/** Directive CSP yang wajib muncul; sumber lain (Vite, fonts) boleh berbeda antar profil. */
const REQUIRED_CSP_DIRECTIVES: readonly string[] = [
    "default-src 'self'",
    "frame-ancestors 'self'",
    "object-src 'none'",
    "form-action 'self'",
];

/** Header CSP yang dipakai saat mode `report-only`; kehadirannya berarti CSP tidak menegakkan apa pun. */
const CSP_REPORT_ONLY_HEADER = 'content-security-policy-report-only';

const CSP_HEADER = 'content-security-policy';

/**
 * Jeda sadar setelah `load` untuk memberi kesempatan resource async (iframe lazy,
 * chunk vendor) memicu pelanggaran CSP sebelum daftar pelanggaran dibaca.
 */
const VIOLATION_SETTLE_MS = 750;

/** Sumber gambar yang dijamin ditolak `img-src` di profil lokal maupun produksi. */
const BLOCKED_IMAGE_URL = 'http://127.0.0.1:9/csp-blocked.png';

/** Satu pelanggaran CSP yang tercatat di dokumen halaman. */
interface IViolationRecord {
    directive: string;
    blockedUri: string;
    page: string;
}

interface ICspProbeOutcome {
    violations: Array<{ directive: string; blockedUri: string }>;
    inlineScriptRan: boolean;
}

declare global {
    interface Window {
        /** Kolektor pelanggaran CSP per dokumen; diisi `addInitScript` di spec ini. */
        __securityHeaderViolations?: Array<{ directive: string; blockedUri: string }>;
    }
}

/**
 * Catatan harness: spec ini TIDAK memakai login per-worker untuk semua test.
 * Login UI dilakukan tepat sekali di test cookie (satu-satunya test yang butuh respons
 * `Set-Cookie` asli), sementara test terautentikasi lain memakai storageState dari project
 * `setup`. Dengan begitu percobaan `auth-login` per run tetap 2 (setup + satu test), jauh di
 * bawah throttle 5 percobaan/menit di `config/auth_throttle.php`.
 */

/** Konteks guest bersih: tanpa cookie dan tanpa storageState project. */
async function createGuestContext(browser: Browser): Promise<BrowserContext> {
    return browser.newContext({
        baseURL: environment.baseUrl,
        storageState: { cookies: [], origins: [] },
    });
}

/** Login lewat form halaman `/auth/login`; dipakai test cookie untuk mendapat `Set-Cookie` asli. */
async function signIn(page: Page): Promise<void> {
    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-email').fill(environment.adminEmail);
    await page.locator('#login-password').fill(environment.adminPassword);
    await page
        .locator('form')
        .filter({ has: page.locator('#login-email') })
        .getByRole('button', { name: /masuk/i })
        .click();
    await expect(page).not.toHaveURL(/\/auth\/login/);
}

/** Respons wajib ada; `page.goto` boleh mengembalikan null dan test tidak boleh lanjut buta. */
function requireResponse(response: Response | null, label: string): Response {
    if (response === null) {
        throw new Error(`Navigasi "${label}" tidak menghasilkan respons HTTP.`);
    }

    return response;
}

/**
 * Assert baseline header + CSP mode enforce pada sekumpulan header respons.
 * Header HSTS dan atribut `Secure` pada cookie sengaja tidak diperiksa: keduanya
 * hanya aktif di profil produksi.
 */
function expectSecurityHeaders(headers: Record<string, string>, label: string): void {
    for (const [name, value] of Object.entries(BASELINE_HEADERS)) {
        expect(headers[name], `${label}: header ${name}`).toBe(value);
    }

    const csp = headers[CSP_HEADER] ?? '';

    expect(headers[CSP_REPORT_ONLY_HEADER], `${label}: CSP tidak boleh report-only`).toBeUndefined();
    expect(csp, `${label}: header ${CSP_HEADER} wajib ada`).not.toBe('');

    for (const directive of REQUIRED_CSP_DIRECTIVES) {
        expect(csp, `${label}: CSP wajib memuat "${directive}"`).toContain(directive);
    }
}

/** Daftar pelanggaran CSP yang tercatat di dokumen aktif saat ini. */
async function readViolations(page: Page): Promise<Array<{ directive: string; blockedUri: string }>> {
    return page.evaluate((): Array<{ directive: string; blockedUri: string }> => {
        return window.__securityHeaderViolations ?? [];
    });
}

/** Pasang kolektor pelanggaran CSP sebelum dokumen pertama dieksekusi. */
async function collectViolations(page: Page): Promise<void> {
    await page.addInitScript((): void => {
        window.__securityHeaderViolations = [];
        document.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent): void => {
            window.__securityHeaderViolations?.push({
                directive: event.violatedDirective,
                blockedUri: event.blockedURI,
            });
        });
    });
}

test('guest page / mengirim baseline security headers dan CSP enforce', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const page = await context.newPage();

        const [navigationResponse] = await Promise.all([
            page.waitForResponse((candidate) => new URL(candidate.url()).pathname === '/'),
            page.goto('/', { waitUntil: 'domcontentloaded' }),
        ]);

        expect(navigationResponse.status()).toBe(200);
        expectSecurityHeaders(navigationResponse.headers(), 'GET / (browser)');

        // Jalur HTTP mentah: membuktikan header berasal dari middleware, bukan dari renderer.
        const rawResponse = await context.request.get('/');
        expect(rawResponse.status()).toBe(200);
        expectSecurityHeaders(rawResponse.headers(), 'GET / (request)');

        await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
    } finally {
        await context.close();
    }
});

test('halaman admin terautentikasi /admin membawa header yang sama dan izin kamera', async ({ page }) => {
    const response = requireResponse(await page.goto('/admin', { waitUntil: 'domcontentloaded' }), '/admin');

    // `/admin` adalah redirect bernama ke dasbor; yang diverifikasi adalah respons akhirnya.
    expect(new URL(page.url()).pathname).toBe('/dashboard');
    expect(response.status()).toBe(200);
    expectSecurityHeaders(response.headers(), 'GET /admin (terautentikasi)');
    expect(response.headers()['permissions-policy']).toContain('camera=(self)');
});

test('route 404 dan health check /up tetap membawa header keamanan', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const page = await context.newPage();

        const notFound = requireResponse(
            await page.goto('/this-route-does-not-exist-be-p3-009', { waitUntil: 'domcontentloaded' }),
            'route 404',
        );
        expect(notFound.status()).toBe(404);
        expectSecurityHeaders(notFound.headers(), 'GET /this-route-does-not-exist-be-p3-009');

        const health = await context.request.get('/up');
        expect(health.status()).toBe(200);
        expectSecurityHeaders(health.headers(), 'GET /up');
    } finally {
        await context.close();
    }
});

test('cookie sesi hasil login UI bersifat httpOnly + SameSite=Lax dan tidak terbaca JavaScript', async ({
    browser,
}) => {
    const context = await createGuestContext(browser);

    try {
        const page = await context.newPage();

        const [loginResponse] = await Promise.all([
            page.waitForResponse(
                (candidate) =>
                    new URL(candidate.url()).pathname === '/auth/login' && candidate.request().method() === 'POST',
            ),
            signIn(page),
        ]);

        // Bukti tingkat wire: atribut datang dari `Set-Cookie` server, bukan dari asumsi config.
        const setCookieHeaders = (await loginResponse.headersArray())
            .filter((header) => header.name.toLowerCase() === 'set-cookie')
            .map((header) => header.value);

        const sessionSetCookie = setCookieHeaders.find((value) =>
            value.startsWith(`${sessionCookieName()}=`),
        );

        expect(sessionSetCookie, `Set-Cookie "${sessionCookieName()}" wajib ada pada respons login`).toBeDefined();
        expect(sessionSetCookie ?? '').toMatch(/;\s*httponly/i);
        expect(sessionSetCookie ?? '').toMatch(/;\s*samesite=lax/i);

        const session = (await context.cookies()).find((cookie) => cookie.name === sessionCookieName());

        expect(session, `cookie "${sessionCookieName()}" wajib tersimpan setelah login UI`).toBeDefined();

        if (session === undefined) {
            throw new Error(`Cookie "${sessionCookieName()}" tidak tersimpan di konteks setelah login UI.`);
        }

        expect(session.httpOnly, 'flag httpOnly yang tersimpan di browser').toBe(true);
        expect(session.sameSite, 'nilai SameSite yang tersimpan di browser').toBe('Lax');

        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        const scriptVisibleCookies = await page.evaluate((): string => document.cookie);

        // Dibandingkan sebagai boolean agar nilai cookie tidak pernah ikut tercetak saat gagal.
        expect(
            scriptVisibleCookies.includes(session.value),
            'document.cookie tidak boleh memuat nilai cookie sesi',
        ).toBe(false);

        // Kontrol positif: JS memang bisa membaca cookie lain, jadi assert di atas tidak lolos karena kosong.
        expect(scriptVisibleCookies).toContain('XSRF-TOKEN');
    } finally {
        await context.close();
    }
});

test('CSP benar-benar menegakkan img-src pada halaman terautentikasi', async ({ page }) => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    const outcome = await page.evaluate(async (blockedImageUrl: string): Promise<ICspProbeOutcome> => {
        const violations: Array<{ directive: string; blockedUri: string }> = [];

        const violationObserved = new Promise<void>((resolve) => {
            document.addEventListener(
                'securitypolicyviolation',
                (event: SecurityPolicyViolationEvent): void => {
                    violations.push({
                        directive: event.violatedDirective,
                        blockedUri: event.blockedURI,
                    });

                    if (event.violatedDirective.startsWith('img-src')) {
                        resolve();
                    }
                },
                { once: false },
            );
        });

        const blockedImage = document.createElement('img');
        blockedImage.src = blockedImageUrl;
        document.body.append(blockedImage);

        // Profil lokal menambahkan `'unsafe-inline'` ke script-src (Vite HMR + toolbar debug),
        // jadi script inline TIDAK BISA dipakai sebagai bukti penegakan CSP; hanya gambar di atas.
        const inlineScript = document.createElement('script');
        inlineScript.textContent = "document.documentElement.dataset.inlineProbe = 'ran';";
        document.body.append(inlineScript);

        const inlineScriptRan = document.documentElement.dataset.inlineProbe === 'ran';
        const settle = new Promise<void>((resolve) => {
            window.setTimeout(resolve, 5000);
        });

        await Promise.race([violationObserved, settle]);

        return { violations, inlineScriptRan };
    }, BLOCKED_IMAGE_URL);

    const imageViolations = outcome.violations.filter((violation) =>
        violation.directive.startsWith('img-src'),
    );

    expect(
        imageViolations.length,
        `tidak ada securitypolicyviolation img-src; pelanggaran tercatat: ${JSON.stringify(outcome.violations)}`,
    ).toBeGreaterThan(0);
    expect(imageViolations[0]?.blockedUri).toContain('127.0.0.1:9');
    expect(outcome.inlineScriptRan, 'inline script dieksekusi karena dev allowance, bukan bukti blokir').toBe(true);
});

test('CSP tidak merusak halaman /, /events, dan /auth/login', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const page = await context.newPage();
        const violations: IViolationRecord[] = [];
        const pageErrors: string[] = [];

        await collectViolations(page);
        page.on('pageerror', (error: Error): void => {
            pageErrors.push(error.message);
        });

        const visitedPaths: readonly string[] = ['/', '/events', '/auth/login'];

        for (const path of visitedPaths) {
            const response = requireResponse(await page.goto(path, { waitUntil: 'load' }), path);

            expect(response.status(), `status ${path}`).toBe(200);
            await expect(page.getByRole('heading', { level: 1 }).first(), `konten ${path} wajib render`).toBeVisible();

            // Footer memuat iframe peta dengan `loading="lazy"`, jadi scroll ke bawah dipaksa agar
            // pengecekan tidak bergantung pada heuristik lazy-load Chromium (jarak viewport).
            await page.evaluate((): void => {
                window.scrollTo(0, document.body.scrollHeight);
            });

            await page.waitForTimeout(VIOLATION_SETTLE_MS);

            for (const violation of await readViolations(page)) {
                violations.push({ ...violation, page: path });
            }
        }

        expect(pageErrors, `page error pada ${visitedPaths.join(', ')}`).toEqual([]);

        // DEFEK PRODUK (BE-P3-009, terverifikasi 2 run berturut-turut): footer landing memuat
        // `https://maps.google.com/maps?...&output=embed`, dan Google me-redirect 301 ke
        // `https://www.google.com/maps/embed?...` — origin yang TIDAK ada di `frame-src`
        // (`config/security.php`). Hasilnya iframe peta diblokir CSP dan peta tidak pernah tampil.
        // Perbaikan yang disarankan: ganti URL ke bentuk akhir `https://maps.google.com/maps/embed?pb=...`
        // (200 tanpa redirect) atau tambahkan `https://www.google.com` ke `frame-src`.
        // Assert di bawah sengaja TIDAK dilonggarkan: pelanggaran CSP di halaman publik adalah regresi nyata.
        expect(
            violations,
            `pelanggaran CSP pada ${visitedPaths.join(', ')}: ${JSON.stringify(violations)}`,
        ).toEqual([]);
    } finally {
        await context.close();
    }
});

test('clickjacking: dokumen aplikasi ditolak saat di-frame origin lain', async ({ browser }) => {
    const context = await createGuestContext(browser);

    try {
        const framedUrl = `${environment.baseUrl}/`;

        // Kontrol: sumber frame memang sehat (200), jadi penolakan bukan karena network error.
        const framedResponse = await context.request.get(framedUrl);
        expect(framedResponse.status(), `${framedUrl} wajib 200 sebelum uji framing`).toBe(200);

        const page = await context.newPage();
        const consoleMessages: string[] = [];
        page.on('console', (message): void => {
            consoleMessages.push(message.text());
        });

        const parentDocument = `<iframe id="victim" src="${framedUrl}"></iframe>`;
        await page.goto(`data:text/html,${encodeURIComponent(parentDocument)}`);

        // Penolakan dikenali dari pesan Chromium (X-Frame-Options / frame-ancestors) ATAU dari
        // frame anak yang tidak pernah memuat dokumen aplikasi (chrome-error).
        const refusalReason = async (): Promise<string> => {
            const refusalMessage = consoleMessages.find((text) => /X-Frame-Options|frame-ancestors/i.test(text));

            if (refusalMessage !== undefined) {
                return `console: ${refusalMessage}`;
            }

            const handle = await page.locator('#victim').elementHandle();
            const childFrame = handle === null ? null : await handle.contentFrame();
            const childUrl = childFrame === null ? '' : childFrame.url();

            return childUrl.startsWith('chrome-error://') ? `child frame: ${childUrl}` : '';
        };

        await expect
            .poll(refusalReason, {
                message:
                    'dokumen aplikasi harus ditolak di iframe lintas origin (X-Frame-Options/frame-ancestors)',
                timeout: 10_000,
            })
            .not.toBe('');

        const appFrames = page.frames().filter((frame) => frame.url().startsWith(environment.baseUrl));
        expect(
            appFrames.map((frame) => frame.url()),
            'iframe lintas origin tidak boleh memuat dokumen aplikasi',
        ).toHaveLength(0);
    } finally {
        await context.close();
    }
});

test('anomali input: payload script di query dan NUL byte di path tidak menghasilkan 500 atau dialog', async ({
    browser,
}) => {
    const context = await createGuestContext(browser);

    try {
        const page = await context.newPage();
        const dialogs: string[] = [];

        page.on('dialog', async (dialog): Promise<void> => {
            dialogs.push(dialog.message());
            await dialog.dismiss();
        });

        const queryPayload = requireResponse(
            await page.goto('/?q=%3Cscript%3Ealert(1)%3C/script%3E', { waitUntil: 'domcontentloaded' }),
            'query payload',
        );
        expect(queryPayload.status(), 'payload query tidak boleh 500').toBe(200);
        expectSecurityHeaders(queryPayload.headers(), 'GET /?q=<script>alert(1)</script>');

        const nulPath = requireResponse(await page.goto('/%00', { waitUntil: 'domcontentloaded' }), 'NUL path');
        expect(nulPath.status(), 'path NUL tidak boleh 500').toBe(404);
        expectSecurityHeaders(nulPath.headers(), 'GET /%00');

        expect(dialogs, `dialog yang muncul: ${JSON.stringify(dialogs)}`).toEqual([]);
    } finally {
        await context.close();
    }
});
