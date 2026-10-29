import type { Locator, Page } from '@playwright/test';
import { countFixtureRows, expect, test } from './support/fixtures';
import { startRequestLog } from './support/network';

/** Penanda kanvas kosong; hilang setelah minimal satu field ada di kanvas. */
const EMPTY_CANVAS_TEXT = 'Kanvas masih kosong';

/** Tombol simpan yang benar-benar terlihat: toolbar desktop (`Save Form`) atau blok mobile (`Simpan`). */
function saveControls(page: Page): Locator {
    return page.getByRole('button', { name: /^(Simpan|Save Form)$/ }).filter({ visible: true });
}

/** Tombol pratinjau yang benar-benar terlihat (toolbar desktop dan blok mobile berbagi aria-label). */
function previewControls(page: Page): Locator {
    return page.getByRole('button', { name: 'Pratinjau formulir' }).filter({ visible: true });
}

/** Buka halaman Create form untuk satu event fixture dan pastikan kanvas siap diisi. */
async function openCreatePage(page: Page, eventId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible();
}

/**
 * Isi kelima syarat simpan lewat UI: judul, subtitle, tanggal tutup, visibility, satu field.
 * Dipakai baris P0 sebagai "valid wizard data" selama wizard Create belum ada.
 */
async function fillValidFormDraft(page: Page, title: string): Promise<void> {
    await page.locator('#f-title').fill(title);
    await page.locator('#f-description').fill('Deskripsi e2e P0-1');

    await page.getByRole('button', { name: /text inputs/i }).click();
    const canvas = page.locator('section').filter({ has: page.locator('#f-title') }).first();
    await page.getByText('Short Text', { exact: true }).first().dragTo(canvas);
    await expect(page.getByText(EMPTY_CANVAS_TEXT)).toHaveCount(0);

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
            await saveControls(page).first().click();
            await requestLog.waitForRequestCount('POST', storePath, 1);
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
            await saveControls(page).first().dblclick();
            await requestLog.waitForRequestCount('POST', storePath, 1);
            await page.waitForTimeout(2000);

            expect(requestLog.requestCount('POST', storePath)).toBe(1);
        } finally {
            requestLog.stop();
        }

        expect(countFixtureRows(fixtureEvent.marker).counts.forms).toBe(1);
    });
});
