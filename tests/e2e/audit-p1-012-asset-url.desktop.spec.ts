import type { BrowserContext, ConsoleMessage, Dialog, Frame, Locator, Page, Request, TestInfo } from '@playwright/test';
import { readEnvironment } from './support/environment';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    defaultFixtureRequest,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';
import type { IOwnerIdentity } from './support/marker';

/**
 * AUDIT-P1-012: gambar/banner builder harus tetap inert untuk payload URL anomali
 * setelah pemindahan `normalizePublicStorageUrl` (dulu `normalizeBannerSrc`) ke
 * `resources/js/lib/publicStorageUrl.ts`, dan editor Tiptap lazy harus tetap termuat.
 */

/**
 * PNG 4x4 hasil GD: harus bisa didecode `createImageBitmap` karena klien menolak
 * berkas yang signature-nya benar tetapi isinya tidak dapat dibaca (1x1 base64
 * yang umum dipakai justru gagal decode di Chromium).
 */
const VALID_PNG_BYTES: Buffer = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPkqjjBAANMDEgANwcAQLoBUlac4vkAAAAASUVORK5CYII=',
    'base64',
);

const ACTIVE_SVG_SOURCE = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
const FORM_ID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

interface IAdminOwnedForm {
    identity: IOwnerIdentity;
    eventId: string;
    formId: string;
}

interface IPutBannerOptions {
    title: string;
    bannerUrl: string;
}

interface IDialogCollector {
    messages: () => string[];
    clear: () => void;
    stop: () => void;
}

interface INavigationCollector {
    urls: () => string[];
    stop: () => void;
}

interface IRequestCollector {
    urls: () => string[];
    stop: () => void;
}

interface IConsoleErrorCollector {
    texts: () => string[];
    stop: () => void;
}

/** Event/form milik admin E2E: fixture bawaan membuat event milik user marker sehingga admin kena 403. */
function createAdminOwnedForm(testInfo: TestInfo): IAdminOwnedForm {
    const identity = ownerIdentityForTest(testInfo);
    const ownerEmail = readEnvironment().adminEmail;
    const owner = createOwnerFixture(identity, ownerEmail);
    const forms = createFormsFixture(owner, {
        ...defaultFixtureRequest(identity, 1, 1, 0),
        ownerEmail,
    });
    const primary = forms[0];

    if (primary === undefined) {
        cleanupOwnerFixture(identity);
        throw new Error('e2e:fixture returned no form.');
    }

    return { identity, eventId: owner.eventId, formId: primary.formId };
}

function showFormPath(form: IAdminOwnedForm): string {
    return `/admin/events/${form.eventId}/forms/${form.formId}`;
}

async function openShowEditor(page: Page, form: IAdminOwnedForm): Promise<void> {
    const response = await page.goto(showFormPath(form), { waitUntil: 'domcontentloaded' });

    expect(response?.status() ?? 0).toBe(200);
    await expect(page.locator('#f-title')).toBeVisible();
}

/** Tambah field dari palette lewat pencarian + drag, lalu kembalikan baris field terakhir. */
async function addPaletteField(page: Page, searchTerm: string, label: string): Promise<Locator> {
    await page.getByPlaceholder('Cari komponen…').fill(searchTerm);

    const canvas = page.locator('section').filter({ has: page.locator('#f-title') }).first();
    await page.getByText(label, { exact: true }).first().dragTo(canvas, { targetPosition: { x: 140, y: 120 } });

    const row = page.locator('.fb-field-row').last();
    await expect(row).toBeVisible();

    return row;
}

async function readXsrfToken(context: BrowserContext): Promise<string> {
    const cookie = (await context.cookies()).find((entry) => entry.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        throw new Error('XSRF-TOKEN cookie is missing; open the form page before calling the update endpoint.');
    }

    return decodeURIComponent(cookie.value);
}

/**
 * Simpan `banner_url` lewat endpoint update asli (jalur yang dipakai autosave/legacy import).
 * Judul dipertahankan agar marker fixture tetap terbaca oleh `e2e:cleanup`.
 */
async function putFormBannerUrl(page: Page, form: IAdminOwnedForm, options: IPutBannerOptions): Promise<void> {
    if (!FORM_ID_PATTERN.test(form.formId)) {
        throw new Error(`Unexpected fixture form id "${form.formId}".`);
    }

    const response = await page.context().request.put(showFormPath(form), {
        data: {
            _method: 'put',
            title: options.title,
            description: 'e2e asset url probe',
            closed_at: '2026-12-31 23:59:59',
            visible_for: ['public'],
            banner_url: options.bannerUrl,
            metadata: { purpose: 'other' },
        },
        headers: {
            'X-XSRF-TOKEN': await readXsrfToken(page.context()),
            Accept: 'application/json',
        },
        failOnStatusCode: false,
    });

    expect(response.status(), `PUT banner_url=${options.bannerUrl}`).toBe(200);
}

function collectDialogs(page: Page): IDialogCollector {
    const messages: string[] = [];
    const onDialog = (dialog: Dialog): void => {
        messages.push(dialog.message());
        void dialog.dismiss().catch((): void => {});
    };

    page.on('dialog', onDialog);

    return {
        messages: (): string[] => [...messages],
        clear: (): void => {
            messages.length = 0;
        },
        stop: (): void => {
            page.off('dialog', onDialog);
        },
    };
}

function collectNavigations(page: Page): INavigationCollector {
    const urls: string[] = [];
    const onNavigated = (frame: Frame): void => {
        urls.push(frame.url());
    };

    page.on('framenavigated', onNavigated);

    return {
        urls: (): string[] => [...urls],
        stop: (): void => {
            page.off('framenavigated', onNavigated);
        },
    };
}

function collectRequests(page: Page): IRequestCollector {
    const urls: string[] = [];
    const onRequest = (request: Request): void => {
        urls.push(request.url());
    };

    page.on('request', onRequest);

    return {
        urls: (): string[] => [...urls],
        stop: (): void => {
            page.off('request', onRequest);
        },
    };
}

function collectConsoleErrors(page: Page): IConsoleErrorCollector {
    const texts: string[] = [];
    const onConsole = (message: ConsoleMessage): void => {
        if (message.type() === 'error') {
            texts.push(message.text());
        }
    };

    page.on('console', onConsole);

    return {
        texts: (): string[] => [...texts],
        stop: (): void => {
            page.off('console', onConsole);
        },
    };
}

/** Bukti listener dialog benar-benar menangkap alert, bukan sekadar array kosong. */
async function assertDialogListenerWorks(page: Page, dialogs: IDialogCollector): Promise<void> {
    const before = dialogs.messages().length;

    await page.evaluate((): void => {
        window.alert('dialog-listener-probe');
    });

    await expect.poll((): number => dialogs.messages().length).toBe(before + 1);
}

/** Sumber `src` gambar tidak boleh berupa skema skrip yang bisa dieksekusi. */
function expectInertImageSource(src: string | null, label: string): void {
    expect(src, `${label}: img[src]`).not.toBeNull();
    expect(/^(javascript|vbscript):/i.test(src ?? ''), `${label}: src tidak boleh skema skrip`).toBe(false);
}

test.describe('AUDIT-P1-012 normalisasi URL aset', () => {
    test('editor gambar: payload javascript/data/vbscript tetap inert di kanvas', async ({ page }) => {
        test.setTimeout(60_000);
        const form = createAdminOwnedForm(test.info());
        const dialogs = collectDialogs(page);
        const navigations = collectNavigations(page);
        const consoleErrors = collectConsoleErrors(page);

        try {
            await openShowEditor(page, form);
            await assertDialogListenerWorks(page, dialogs);
            dialogs.clear();

            const row = await addPaletteField(page, 'Image', 'Image');
            await row.getByRole('button', { name: 'Pengaturan field' }).click();

            const urlInput = page.getByPlaceholder('atau tempel URL gambar…');
            await expect(urlInput).toBeVisible();

            await urlInput.fill('javascript:alert(1)');
            await expect(row.locator('img')).toHaveAttribute('src', '/storage/javascript:alert(1)');

            await urlInput.fill('data:text/html,<script>alert(1)</script>');
            await expect(row.locator('img')).toHaveAttribute('src', 'data:text/html,<script>alert(1)</script>');

            await urlInput.fill('vbscript:msgbox(1)');
            const vbscriptSrc = await row.locator('img').getAttribute('src');
            expectInertImageSource(vbscriptSrc, 'vbscript');
            expect(vbscriptSrc).toBe('/storage/vbscript:msgbox(1)');

            expect(dialogs.messages(), 'tidak ada dialog alert dari payload').toHaveLength(0);
            expect(
                navigations.urls().filter((url) => url.startsWith('javascript:')),
                'tidak ada navigasi ke skema javascript:',
            ).toHaveLength(0);
            expect(
                consoleErrors.texts().filter((text) => /javascript:|Uncaught/i.test(text)),
                'console error terkait payload',
            ).toHaveLength(0);
        } finally {
            consoleErrors.stop();
            navigations.stop();
            dialogs.stop();
            cleanupOwnerFixture(form.identity);
        }
    });

    test('opsi gambar: payload javascript/vbscript tetap inert di kanvas', async ({ page }) => {
        test.setTimeout(60_000);
        const form = createAdminOwnedForm(test.info());
        const dialogs = collectDialogs(page);

        try {
            await openShowEditor(page, form);
            // Buktikan listener dialog menangkap alert sebelum mengklaim "tidak ada dialog".
            await assertDialogListenerWorks(page, dialogs);
            dialogs.clear();

            const row = await addPaletteField(page, 'Checkbox', 'Checkbox');
            await row.getByRole('button', { name: 'Kelola opsi' }).click();
            await page.getByRole('button', { name: 'Tambah opsi' }).click();
            await page.getByText('Pakai gambar', { exact: true }).first().click();

            const urlInput = page.getByPlaceholder('URL gambar');
            await expect(urlInput).toBeVisible();

            await urlInput.fill('javascript:alert(1)');
            await expect(row.locator('img')).toHaveAttribute('src', '/storage/javascript:alert(1)');

            await urlInput.fill('vbscript:msgbox(1)');
            const vbscriptSrc = await row.locator('img').getAttribute('src');
            expectInertImageSource(vbscriptSrc, 'opsi vbscript');
            expect(vbscriptSrc).toBe('/storage/vbscript:msgbox(1)');

            expect(dialogs.messages(), 'tidak ada dialog alert dari payload opsi').toHaveLength(0);
        } finally {
            dialogs.stop();
            cleanupOwnerFixture(form.identity);
        }
    });

    test('banner tersimpan: payload javascript/data/vbscript inert di kanvas dan pratinjau', async ({ page }) => {
        test.setTimeout(90_000);
        const form = createAdminOwnedForm(test.info());
        const dialogs = collectDialogs(page);
        const navigations = collectNavigations(page);
        const consoleErrors = collectConsoleErrors(page);

        try {
            await openShowEditor(page, form);
            await assertDialogListenerWorks(page, dialogs);
            dialogs.clear();

            const title = await page.locator('#f-title').inputValue();
            const bannerImage = page.getByAltText('Pratinjau banner form');

            const cases: { payload: string; expectedSrc: string }[] = [
                { payload: 'javascript:alert(1)', expectedSrc: '/storage/javascript:alert(1)' },
                {
                    payload: 'data:text/html,<script>alert(1)</script>',
                    expectedSrc: 'data:text/html,<script>alert(1)</script>',
                },
                { payload: 'vbscript:msgbox(1)', expectedSrc: '/storage/vbscript:msgbox(1)' },
            ];

            for (const entry of cases) {
                await putFormBannerUrl(page, form, { title, bannerUrl: entry.payload });
                await page.reload({ waitUntil: 'domcontentloaded' });

                await expect(bannerImage, `banner untuk ${entry.payload}`).toBeVisible();
                await expect(bannerImage).toHaveAttribute('src', entry.expectedSrc);
                expectInertImageSource(await bannerImage.getAttribute('src'), entry.payload);
            }

            await page.getByRole('button', { name: 'Pratinjau formulir' }).first().click();
            const preview = page.getByRole('dialog', { name: 'Form respondent preview' });
            await expect(preview).toBeVisible();

            const previewSources = await preview.locator('img').evaluateAll((images: Element[]): (string | null)[] =>
                images.map((image: Element): string | null => image.getAttribute('src')),
            );

            // Non-empty agar loop tidak lolos secara vacuous saat pratinjau tidak merender gambar.
            expect(previewSources.length, 'pratinjau harus merender minimal satu gambar').toBeGreaterThan(0);
            for (const src of previewSources) {
                expectInertImageSource(src, 'pratinjau');
            }

            expect(dialogs.messages(), 'tidak ada dialog alert dari banner').toHaveLength(0);
            expect(
                navigations.urls().filter((url) => url.startsWith('javascript:')),
                'tidak ada navigasi ke skema javascript:',
            ).toHaveLength(0);
            expect(
                consoleErrors.texts().filter((text) => /javascript:|Uncaught/i.test(text)),
                'console error terkait payload banner',
            ).toHaveLength(0);
        } finally {
            consoleErrors.stop();
            navigations.stop();
            dialogs.stop();
            cleanupOwnerFixture(form.identity);
        }
    });

    test('unggah banner: PNG valid diterima, file teks ditolak tanpa request unggah', async ({ page }) => {
        test.setTimeout(60_000);
        const form = createAdminOwnedForm(test.info());
        const requests = collectRequests(page);

        try {
            await openShowEditor(page, form);

            const bannerInput = page.locator('input[type=file][accept="image/png,image/jpeg,image/gif"]');
            const uploadResponse = page.waitForResponse(
                (response): boolean =>
                    response.request().method() === 'POST' &&
                    new URL(response.url()).pathname.endsWith('/images'),
            );

            // Kontrol positif: jalur unggah banner benar-benar aktif untuk gambar sah.
            await bannerInput.setInputFiles({
                name: 'banner.png',
                mimeType: 'image/png',
                buffer: VALID_PNG_BYTES,
            });

            expect((await uploadResponse).status(), 'PNG valid diterima server').toBe(200);

            const bannerImage = page.getByAltText('Pratinjau banner form');
            await expect(bannerImage).toBeVisible();
            const uploadsBefore = requests.urls().filter((url) => url.includes('/images')).length;

            await bannerInput.setInputFiles({
                name: 'evil.txt',
                mimeType: 'text/plain',
                buffer: Buffer.from('not an image'),
            });

            await expect(page.getByText('Gunakan PNG, JPG, JPEG, atau GIF.')).toBeVisible();
            expect(
                requests.urls().filter((url) => url.includes('/images')).length,
                'file non-gambar tidak boleh dikirim ke endpoint upload',
            ).toBe(uploadsBefore);
            // Banner lama tetap tampil: tidak ada broken image diam-diam.
            await expect(bannerImage).toBeVisible();
        } finally {
            requests.stop();
            cleanupOwnerFixture(form.identity);
        }
    });

    test('unggah gambar opsi: SVG aktif ditolak sebelum request jaringan', async ({ page }) => {
        test.setTimeout(60_000);
        const form = createAdminOwnedForm(test.info());
        const requests = collectRequests(page);

        try {
            await openShowEditor(page, form);

            const row = await addPaletteField(page, 'Checkbox', 'Checkbox');
            await row.getByRole('button', { name: 'Kelola opsi' }).click();
            await page.getByRole('button', { name: 'Tambah opsi' }).click();
            await page.getByText('Pakai gambar', { exact: true }).first().click();

            const optionFileInput = page.locator('[role=dialog] input[type=file][accept="image/*"]');
            const uploadsBefore = requests.urls().filter((url) => url.endsWith('/images')).length;

            await optionFileInput.first().setInputFiles({
                name: 'evil.svg',
                mimeType: 'image/svg+xml',
                buffer: Buffer.from(ACTIVE_SVG_SOURCE),
            });

            // Validasi klien menolak format di luar jpg/jpeg/png/webp/gif, sehingga
            // payload aktif tidak pernah menyentuh server/storage. Teks persis dipakai
            // agar tidak tertukar dengan pesan gagal-upload generik.
            await expect(
                page.getByText('Gagal mengunggah gambar opsi. Coba lagi.', { exact: true }),
                'error validasi tampil di UI',
            ).toBeVisible();
            expect(
                requests.urls().filter((url) => url.endsWith('/images')).length,
                'SVG tidak boleh dikirim ke endpoint upload',
            ).toBe(uploadsBefore);

            // Tidak boleh ada broken image diam-diam: URL opsi dikosongkan setelah gagal.
            await expect(row.locator('img')).toHaveCount(0);
        } finally {
            requests.stop();
            cleanupOwnerFixture(form.identity);
        }
    });

    test('path storage dengan traversal tetap same-origin', async ({ page }) => {
        test.setTimeout(60_000);
        const form = createAdminOwnedForm(test.info());
        const requests = collectRequests(page);
        const baseUrl = readEnvironment().baseUrl;

        try {
            await openShowEditor(page, form);
            const title = await page.locator('#f-title').inputValue();
            const payload = 'form-images/../../etc/passwd';

            await putFormBannerUrl(page, form, { title, bannerUrl: payload });
            await page.reload({ waitUntil: 'domcontentloaded' });

            const bannerImage = page.getByAltText('Pratinjau banner form');
            await expect(bannerImage).toBeVisible();
            await expect(bannerImage).toHaveAttribute('src', '/storage/form-images/../../etc/passwd');
            await page.waitForLoadState('networkidle');

            const traversalRequests = requests.urls().filter((url) => url.includes('etc/passwd'));
            // Loop kosong tidak membuktikan apa pun; pastikan browser benar-benar meminta path itu.
            expect(traversalRequests.length, 'browser harus meminta path traversal agar assertion bermakna').toBeGreaterThan(0);
            for (const url of traversalRequests) {
                expect(url.startsWith(baseUrl), `request traversal harus same-origin: ${url}`).toBe(true);
            }
        } finally {
            requests.stop();
            cleanupOwnerFixture(form.identity);
        }
    });

    /**
     * Banner eksternal (termasuk network-path reference `//host/x.png`) memang
     * fitur admin: `normalizePublicStorageUrl` hanya menjamin payload tidak
     * menjadi skema yang dapat dieksekusi. Host passthrough dicatat sebagai
     * hardening terpisah di ticket `AUDIT-P1-012-F2`, bukan regresi tiket ini.
     */
    test('URL protocol-relative tetap inert sebagai gambar, bukan skrip atau navigasi', async ({ page }) => {
        test.setTimeout(60_000);
        const form = createAdminOwnedForm(test.info());
        const dialogs = collectDialogs(page);
        const navigations = collectNavigations(page);

        try {
            await openShowEditor(page, form);
            const title = await page.locator('#f-title').inputValue();

            await putFormBannerUrl(page, form, { title, bannerUrl: '//evil.example/x.png' });
            await page.reload({ waitUntil: 'domcontentloaded' });

            const bannerImage = page.getByAltText('Pratinjau banner form');
            await expect(bannerImage).toBeVisible();

            const bannerSrc = await bannerImage.getAttribute('src');
            expectInertImageSource(bannerSrc, 'protocol-relative banner');
            expect(bannerSrc?.startsWith('data:text/html'), 'src tidak boleh HTML data URL').toBe(false);
            expect(dialogs.messages(), 'tidak ada dialog dari banner eksternal').toHaveLength(0);
            expect(
                navigations.urls().filter((url) => url.startsWith('javascript:')),
                'tidak ada navigasi javascript:',
            ).toHaveLength(0);
        } finally {
            dialogs.stop();
            navigations.stop();
            cleanupOwnerFixture(form.identity);
        }
    });

    test('editor deskripsi Tiptap lazy termuat dan bisa diketik', async ({ page }) => {
        test.setTimeout(60_000);
        const consoleErrors = collectConsoleErrors(page);

        try {
            const response = await page.goto('/admin/events/create', { waitUntil: 'domcontentloaded' });
            expect(response?.status() ?? 0).toBe(200);

            await expect(page.getByLabel('Toolbar editor deskripsi')).toBeVisible();

            const editor = page.locator('[contenteditable=true]').first();
            await expect(editor).toBeVisible();
            await editor.click();
            await page.keyboard.type('E2E lazy editor chunk');
            await expect(editor).toContainText('E2E lazy editor chunk');

            expect(
                consoleErrors.texts().filter((text) => /Failed to fetch dynamically imported module/i.test(text)),
                'chunk editor gagal dimuat',
            ).toHaveLength(0);
        } finally {
            consoleErrors.stop();
        }
    });
});
