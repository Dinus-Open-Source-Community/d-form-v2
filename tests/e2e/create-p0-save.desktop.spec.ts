import type { Locator, Page } from '@playwright/test';
import { countFixtureRows, expect, test } from './support/fixtures';
import { startRequestLog } from './support/network';

/** Empty state bagian aktif; hanya dirender selama bagian belum memiliki field. */
const EMPTY_CANVAS_TEXT = 'Bagian ini belum memiliki field';

/** Input label pada setiap kartu field kanvas; bukti field hasil drag benar-benar dirender. */
const FIELD_LABEL_INPUT = 'input[placeholder^="Label pertanyaan"]';

/** Tombol simpan yang benar-benar terlihat: toolbar desktop (`Save Form`) atau blok mobile (`Simpan`). */
function saveControls(page: Page): Locator {
    return page.getByRole('button', { name: /^(Simpan|Save Form)$/ }).filter({ visible: true });
}

/** Tombol pratinjau yang benar-benar terlihat (toolbar desktop dan blok mobile berbagi aria-label). */
function previewControls(page: Page): Locator {
    return page.getByRole('button', { name: 'Pratinjau formulir' }).filter({ visible: true });
}

/** Area drop kanvas: section yang memuat input judul. */
function canvasForTitle(page: Page): Locator {
    return page
        .locator('section')
        .filter({ has: page.locator('#f-title') })
        .first();
}

/**
 * Buka halaman Create form untuk satu event fixture dan pastikan kanvas siap diisi.
 * SSR sudah merender `#f-title` sebelum Vue mount, jadi tunggu hidrasi dulu: klik palette
 * yang terjadi sebelum mount tidak mengubah state apa pun.
 */
async function openCreatePage(page: Page, eventId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible();
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

/**
 * Tambah satu field Short Text dari palette lalu tunggu kartunya render.
 * Drop diarahkan ke koordinat eksplisit di area field, dan percobaan native drag diulang
 * sekali bila event drop tidak sampai — bukan memperbesar timeout simpan.
 */
async function addShortTextField(page: Page): Promise<void> {
    await page.getByRole('button', { name: /text inputs/i }).click();

    const shortText = page.getByText('Short Text', { exact: true }).first();
    await expect(shortText).toBeVisible();

    const fieldRows = page.locator('.fb-field-row');

    for (let attempt = 0; attempt < 2; attempt += 1) {
        await shortText.dragTo(canvasForTitle(page), {
            targetPosition: { x: 40, y: 60 },
            timeout: 20_000,
        });

        try {
            await expect(fieldRows).toHaveCount(1, { timeout: 5_000 });

            return;
        } catch (error) {
            if (attempt === 1) throw error;
        }
    }
}

/**
 * Isi kelima syarat simpan lewat UI: judul, subtitle, tanggal tutup, visibility, satu field.
 * Dipakai baris P0 sebagai "valid wizard data" selama wizard Create belum ada.
 */
async function fillValidFormDraft(page: Page, title: string): Promise<void> {
    await page.locator('#f-title').fill(title);
    await page.locator('#f-description').fill('Deskripsi e2e P0-1');

    // Bukti awal: empty state memang tampil, sehingga hilangnya teks setelah drag bermakna.
    await expect(page.getByText(EMPTY_CANVAS_TEXT, { exact: true })).toBeVisible();
    await addShortTextField(page);
    await expect(page.getByText(EMPTY_CANVAS_TEXT, { exact: true })).toHaveCount(0);
    await expect(page.locator(FIELD_LABEL_INPUT)).toHaveCount(1);
    await expect(page.locator(FIELD_LABEL_INPUT).first()).toHaveValue('Short Text');

    await page.getByRole('button', { name: /pengaturan form/i }).click();
    await page.locator('#l-closed-at-date').click();
    await page.locator('[data-reka-calendar-cell-trigger]').last().click();

    await page.getByRole('button', { name: 'Public', exact: true }).click();
}

test.describe('P0-1 jalur simpan Create form', () => {
    test('P0-01-save-visible-with-hidden-titles', async ({ page, fixtureEvent }) => {
        await openCreatePage(page, fixtureEvent.eventId);
        await fillValidFormDraft(page, `P0-01 hidden titles [${fixtureEvent.marker}]`);

        // Prasyarat baris: halaman Create menyembunyikan judul toolbar.
        await expect(page.getByText(`New form for E2E fixture ${fixtureEvent.ownerKey}`)).toHaveCount(0);

        await expect(saveControls(page)).toHaveCount(1);
        await expect(saveControls(page).first()).toBeEnabled();
        await expect(previewControls(page)).toHaveCount(1);
        await expect(previewControls(page).first()).toBeEnabled();
    });

    test('P0-02-single-post', async ({ page, fixtureEvent }) => {
        const storePath = `/admin/events/${fixtureEvent.eventId}/forms`;

        await openCreatePage(page, fixtureEvent.eventId);
        await fillValidFormDraft(page, `P0-02 single post [${fixtureEvent.marker}]`);

        const requestLog = startRequestLog(page);

        try {
            const [storeResponse] = await Promise.all([
                page.waitForResponse(
                    (response): boolean => response.request().method() === 'POST' && response.url().endsWith(storePath),
                    { timeout: 30_000 }
                ),
                saveControls(page).first().click(),
            ]);

            // Server menolak draft tidak valid dengan redirect balik ke Create; status ini
            // membedakan POST yang benar-benar diterima dari request yang gagal.
            expect([200, 302]).toContain(storeResponse.status());
            await requestLog.waitForRequestCount('POST', storePath, 1, 30_000);
            await page.waitForTimeout(1000);

            expect(requestLog.requestCount('POST', storePath)).toBe(1);
        } finally {
            requestLog.stop();
        }

        expect(countFixtureRows(fixtureEvent.marker).counts.forms).toBe(1);
    });

    test('P0-03-double-click-guard', async ({ page, fixtureEvent }) => {
        const storePath = `/admin/events/${fixtureEvent.eventId}/forms`;

        await page.route(/\/admin\/events\/[^/]+\/forms$/, async (route): Promise<void> => {
            if (route.request().method() === 'POST') {
                await new Promise<void>((done: () => void): void => {
                    setTimeout(done, 1500);
                });
            }

            await route.continue();
        });

        await openCreatePage(page, fixtureEvent.eventId);
        await fillValidFormDraft(page, `P0-03 double click [${fixtureEvent.marker}]`);

        const requestLog = startRequestLog(page);

        try {
            const [storeResponse] = await Promise.all([
                page.waitForResponse(
                    (response): boolean => response.request().method() === 'POST' && response.url().endsWith(storePath),
                    { timeout: 30_000 }
                ),
                saveControls(page).first().dblclick(),
            ]);

            expect([200, 302]).toContain(storeResponse.status());
            await requestLog.waitForRequestCount('POST', storePath, 1, 30_000);
            // Jendela pengamatan guard: klik kedua tidak boleh memicu POST baru setelah response.
            await page.waitForTimeout(2000);

            expect(requestLog.requestCount('POST', storePath)).toBe(1);
        } finally {
            requestLog.stop();
        }

        expect(countFixtureRows(fixtureEvent.marker).counts.forms).toBe(1);
    });
});
