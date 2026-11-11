import { readFileSync } from 'node:fs';
import type { Download, Locator, Page } from '@playwright/test';
import { cleanupOwnerFixture, expect, ownerIdentityForTest, runFixtureCommand, test } from './support/fixtures';
import { runArtisanCommand, runArtisanWithInput } from './support/artisan';
import type { IOwnerIdentity } from './support/marker';

/**
 * E2E UI unggah/unduh: kartu unggah builder (progres → pratinjau dari URL server),
 * import dataset CSV broadcast (kartu `Selesai`), dan tombol export
 * (`downloadWithProgress`: progres saat `Content-Length` ada, fallback `direct`).
 *
 * Endpoint, kontrak submit, dan nama file tidak diubah lane ini: spec hanya
 * memverifikasi bentuk UI baru terhadap respons yang sama seperti sebelumnya.
 * Berjalan untuk project desktop dan mobile karena penambahan field berbeda
 * (drag palette vs sheet FAB).
 */

const IMAGE_UPLOAD_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+\/images$/;
const SNAPSHOT_PATH = /\/admin\/broadcasts\/[^/]+\/snapshot$/;
const REGISTRATIONS_EXPORT_PATH = /\/admin\/events\/[^/]+\/exports\/registrations\.csv$/;

/** PNG 1x1 valid agar validator sisi server menerima file. */
const PNG_1X1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64'
);

const CSV_BODY = 'name,email\nPeserta E2E,e2e-csv@example.test\n';
const EXPORT_CSV_BODY = 'submission_id,form_id\n1,2\n';

function isMobile(testProject: string): boolean {
    return testProject === 'chromium-mobile';
}

function laporanPath(eventId: string): string {
    return `/admin/events/${eventId}/laporan`;
}

/** Form fixture dibuat `closed_at = NULL`; builder autosave menuntutnya, jadi diisi dulu. */
function openFormForAutosave(formId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'updated_at' => now()]);`,
    ]);
}

/** Buka builder `edit` lalu tunggu hidrasi Vue selesai (pola spec builder lain). */
async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    openFormForAutosave(formId);

    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

function canvasForTitle(page: Page): Locator {
    return page
        .locator('section')
        .filter({ has: page.locator('#f-title') })
        .first();
}

/** Tambah blok gambar: drag palette di desktop, sheet FAB di mobile. */
async function addImageBlock(page: Page, projectName: string): Promise<void> {
    if (isMobile(projectName)) {
        await page.getByRole('button', { name: 'Add field' }).click();
        await page.getByRole('button', { name: /Display-only image block/ }).click();

        return;
    }

    await page.getByRole('button', { name: /^Content/ }).click();
    await page.getByText('Display-only image block').first().dragTo(canvasForTitle(page));
}

/** Buka sheet properti field untuk blok gambar yang baru ditambahkan. */
async function openImageFieldEditor(page: Page, projectName: string): Promise<Locator> {
    if (isMobile(projectName)) {
        await page.getByRole('button', { name: 'Edit' }).last().click();
    } else {
        await page.getByRole('button', { name: 'Pengaturan field' }).last().click();
    }

    const sheet = page.getByRole('dialog').filter({ hasText: 'Properti field' });
    await expect(sheet).toBeVisible({ timeout: 15_000 });
    await expect(sheet.getByText('Gambar tampilan')).toBeVisible();

    return sheet;
}

async function pickImage(sheet: Locator): Promise<void> {
    await sheet
        .locator('input[type="file"]')
        .first()
        .setInputFiles({ name: 'blok-e2e.png', mimeType: 'image/png', buffer: PNG_1X1 });
}

interface IUploadedImage {
    path: string;
    url: string;
}

/** Route yang menunda respons agar state `uploading` (progress + Batalkan) bisa diamati. */
async function delayImageUpload(page: Page, uploaded: IUploadedImage[], delayMs: number): Promise<void> {
    await page.route(IMAGE_UPLOAD_PATH, async (route) => {
        const response = await route.fetch();

        try {
            const payload = (await response.json()) as { path?: string; url?: string };

            if (typeof payload.path === 'string' && typeof payload.url === 'string') {
                uploaded.push({ path: payload.path, url: payload.url });
            }
        } catch {
            // Request dibatalkan sebelum body terbaca (uji Batalkan): tidak ada yang dicatat.
        }

        await new Promise<void>((resolve) => {
            setTimeout(resolve, delayMs);
        });

        // Request bisa sudah dibatalkan browser saat kita hendak memenuhi respons.
        await route.fulfill({ response }).catch((): void => undefined);
    });
}

async function collectDownload(download: Download): Promise<{ filename: string; raw: string }> {
    const filePath = await download.path();

    if (filePath === null) {
        throw new Error('Unduhan tidak memiliki path sementara.');
    }

    return { filename: download.suggestedFilename(), raw: readFileSync(filePath, 'utf8') };
}

interface IBroadcastFixtureResult {
    broadcasts: { id: string; name: string }[];
}

/** Fixture broadcast dorman (`draft`); route-nya tetap di-gate `FEATURE_BROADCAST`. */
function createBroadcastFixture(identity: IOwnerIdentity): string {
    const payload = {
        marker: identity.marker,
        slug: identity.slug,
        title: `E2E upload wiring ${identity.ownerKey}`,
        forms: [],
        broadcasts: [{ name: 'E2E CSV import', status: 'draft', recipients: [] }],
    };

    const output = runArtisanWithInput(['e2e:fixture', `--marker=${identity.marker}`], JSON.stringify(payload));
    const result = JSON.parse(output) as IBroadcastFixtureResult;
    const broadcastId = result.broadcasts[0]?.id;

    if (broadcastId === undefined) {
        throw new Error('e2e:fixture tidak mengembalikan broadcast id.');
    }

    return broadcastId;
}

test.describe('EP-07 UI unggah/unduh', () => {
    test('builder: progres unggah, pratinjau dari URL server, dan Batalkan kembali ke pratinjau tersimpan', async ({
        page,
    }, testInfo) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(testInfo);

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E upload wiring ${identity.ownerKey}`,
                forms: [
                    {
                        title: 'Form unggah gambar',
                        fields: [{ label: 'Nama', name: 'nama', input_type: 'input', required: false }],
                        submissions: 0,
                    },
                ],
            });
            const formId = fixture.forms[0]?.formId;

            if (formId === undefined) {
                throw new Error('fixture form tidak terbentuk.');
            }

            await openBuilder(page, fixture.eventId, formId);
            await addImageBlock(page, testInfo.project.name);

            const sheet = await openImageFieldEditor(page, testInfo.project.name);
            const uploaded: IUploadedImage[] = [];
            await delayImageUpload(page, uploaded, 1_200);

            await pickImage(sheet);

            const progress = sheet.getByRole('progressbar', { name: /Progres unggah/ });
            await expect(progress).toBeVisible({ timeout: 10_000 });
            await expect(sheet.getByRole('button', { name: 'Batalkan' })).toBeVisible();

            const preview = sheet.locator('img').first();
            await expect(preview).toBeVisible({ timeout: 15_000 });
            await expect(progress).toHaveCount(0);

            const stored = uploaded[0];
            expect(stored, 'respons upload harus membawa path + url').toBeDefined();

            const previewSrc = await preview.getAttribute('src');
            const basename = (stored?.path ?? '').split('/').pop() ?? '';

            expect(previewSrc, 'pratinjau memakai URL server, bukan blob/data').not.toMatch(/^(blob|data):/);
            expect(previewSrc).toContain(basename);

            // Unggah kedua dibatalkan: pratinjau server sebelumnya tetap tampil, tanpa error.
            await page.unroute(IMAGE_UPLOAD_PATH);
            await delayImageUpload(page, uploaded, 5_000);
            await pickImage(sheet);
            await expect(progress).toBeVisible({ timeout: 10_000 });
            await sheet.getByRole('button', { name: 'Batalkan' }).click();

            await expect(progress).toHaveCount(0);
            await expect(preview).toBeVisible();
            await expect(sheet.locator('p.text-destructive')).toHaveCount(0);
        } finally {
            await page.unroute(IMAGE_UPLOAD_PATH);
            cleanupOwnerFixture(identity);
        }
    });

    test('broadcast: import CSV memakai kartu unggah dengan progres dan Batalkan', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(testInfo);

        try {
            const broadcastId = createBroadcastFixture(identity);
            const response = await page.goto(`/admin/broadcasts/${broadcastId}`, { waitUntil: 'domcontentloaded' });

            if (response === null || response.status() === 404) {
                test.skip(true, 'Route broadcast di-gate FEATURE_BROADCAST; server E2E berjalan dengan flag mati.');

                return;
            }

            await expect(page.getByRole('button', { name: 'Pilih file CSV' })).toBeVisible({ timeout: 20_000 });

            // Input file milik kartu dataset saja; halaman ini juga punya input lampiran
            // dan input editor inline yang urutannya tidak boleh diasumsikan.
            // Input kartu dataset punya `accept=".csv,.txt"` unik; halaman ini juga punya
            // input lampiran dan input editor inline yang urutannya tidak boleh diasumsikan.
            await page.locator('input[type="file"][accept=".csv,.txt"]').setInputFiles({
                name: 'dataset-e2e.csv',
                mimeType: 'text/csv',
                buffer: Buffer.from(CSV_BODY, 'utf8'),
            });

            await expect(page.getByText('dataset-e2e.csv')).toBeVisible();
            await expect(page.getByText(/· Selesai/)).toBeVisible();

            // Visit snapshot ditahan agar state `uploading` + Batalkan bisa diuji tanpa
            // mengubah kontrak respons (redirect Inertia tetap milik server asli).
            await page.route(SNAPSHOT_PATH, () => {
                // Tidak pernah dipenuhi: Batalkan yang menghentikan request.
            });
            await page.getByRole('button', { name: 'Generate Snapshot' }).click({ timeout: 15_000 });

            const progress = page.getByRole('progressbar', { name: /Progres unggah dataset-e2e\.csv/ });
            await expect(progress).toBeVisible({ timeout: 20_000 });
            await page.getByRole('button', { name: 'Batalkan' }).click({ timeout: 15_000 });

            await expect(progress).toHaveCount(0);
            await expect(page.getByText(/· Selesai/)).toBeVisible();
            await expect(page.getByText(/Gagal generate snapshot|Format file tidak didukung/)).toHaveCount(0);
        } finally {
            await page.unroute(SNAPSHOT_PATH);
            cleanupOwnerFixture(identity);
        }
    });

    test('export: progres saat Content-Length ada, lalu file tersimpan dengan nama dari server', async ({
        page,
    }, testInfo) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(testInfo);

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E export wiring ${identity.ownerKey}`,
                forms: [{ title: 'Form export', fields: [], submissions: 0 }],
            });

            await page.route(REGISTRATIONS_EXPORT_PATH, async (route) => {
                await new Promise<void>((resolve) => {
                    setTimeout(resolve, 900);
                });
                await route.fulfill({
                    status: 200,
                    headers: {
                        'content-type': 'text/csv; charset=UTF-8',
                        'content-length': String(Buffer.byteLength(EXPORT_CSV_BODY)),
                        'content-disposition': 'attachment; filename="registrations-event-e2e.csv"',
                    },
                    body: EXPORT_CSV_BODY,
                });
            });

            const response = await page.goto(laporanPath(fixture.eventId), { waitUntil: 'domcontentloaded' });
            expect(response?.status()).toBe(200);

            const button = page.getByRole('button', { name: 'Registrations CSV' });
            await expect(button).toBeVisible({ timeout: 20_000 });

            const downloadPromise = page.waitForEvent('download', { timeout: 30_000 });
            await button.click();

            await expect(page.getByRole('progressbar', { name: /Progres unduhan Registrations CSV/ })).toBeVisible({
                timeout: 20_000,
            });

            const download = await collectDownload(await downloadPromise);
            expect(download.filename).toBe('registrations-event-e2e.csv');
            expect(download.raw).toBe(EXPORT_CSV_BODY);
            await expect(page.getByText('Unduhan selesai')).toBeVisible({ timeout: 20_000 });
        } finally {
            await page.unroute(REGISTRATIONS_EXPORT_PATH);
            cleanupOwnerFixture(identity);
        }
    });

    test('export: endpoint nyata tanpa Content-Length tetap terunduh (progres indeterminate)', async ({
        page,
    }, testInfo) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(testInfo);

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E export stream ${identity.ownerKey}`,
                forms: [{ title: 'Form export', fields: [], submissions: 0 }],
            });

            const response = await page.goto(laporanPath(fixture.eventId), { waitUntil: 'domcontentloaded' });
            expect(response?.status()).toBe(200);

            const button = page.getByRole('button', { name: 'Registrations CSV' });
            await expect(button).toBeVisible({ timeout: 20_000 });

            const downloadPromise = page.waitForEvent('download', { timeout: 30_000 });
            await button.click();

            const download = await collectDownload(await downloadPromise);

            // Nama file dari `Content-Disposition` endpoint nyata (bukan segmen URL).
            expect(download.filename).toMatch(/^registrations-event-.+\.csv$/);
            expect(download.raw).toContain('submission_id');
            await expect(page.getByText('Unduhan selesai')).toBeVisible({ timeout: 20_000 });
        } finally {
            cleanupOwnerFixture(identity);
        }
    });

    test('export: Content-Length di atas batas jatuh ke unduhan langsung', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(testInfo);

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E export direct ${identity.ownerKey}`,
                forms: [{ title: 'Form export', fields: [], submissions: 0 }],
            });

            let requestCount = 0;
            await page.route(REGISTRATIONS_EXPORT_PATH, async (route) => {
                requestCount += 1;

                // Percobaan pertama (fetch) menyatakan 60 MB; adapter harus menolak
                // menahan payload lalu menyerahkan unduhan ke browser.
                if (requestCount === 1) {
                    await route.fulfill({
                        status: 200,
                        headers: {
                            'content-type': 'text/csv; charset=UTF-8',
                            'content-length': String(60 * 1024 * 1024),
                        },
                        body: EXPORT_CSV_BODY,
                    });

                    return;
                }

                await route.fulfill({
                    status: 200,
                    headers: {
                        'content-type': 'text/csv; charset=UTF-8',
                        'content-disposition': 'attachment; filename="registrations-event-large.csv"',
                    },
                    body: EXPORT_CSV_BODY,
                });
            });

            const response = await page.goto(laporanPath(fixture.eventId), { waitUntil: 'domcontentloaded' });
            expect(response?.status()).toBe(200);

            const button = page.getByRole('button', { name: 'Registrations CSV' });
            await expect(button).toBeVisible({ timeout: 20_000 });

            const downloadPromise = page.waitForEvent('download', { timeout: 30_000 });
            await button.click();

            const download = await collectDownload(await downloadPromise);

            // Nama file berasal dari `Content-Disposition` (server), bukan segmen URL.
            expect(download.filename).toMatch(/^registrations-event-.+\.csv$/);
            await expect(page.getByText('Mulai diunduh')).toBeVisible({ timeout: 20_000 });
        } finally {
            await page.unroute(REGISTRATIONS_EXPORT_PATH);
            cleanupOwnerFixture(identity);
        }
    });
});
