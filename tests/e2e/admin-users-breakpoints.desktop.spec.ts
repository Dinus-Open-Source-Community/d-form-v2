import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

/**
 * Direktori pengguna harus utuh dari 360px sampai 1920px: tanpa overflow
 * horizontal, tabel hanya pada >= 1024px, kartu hanya di bawahnya, dan aksi
 * selalu berlabel teks.
 */
const BREAKPOINTS = [360, 768, 1024, 1280, 1920] as const;
const TABLE_MIN_WIDTH = 1024;
const JOINED_COLUMN_MIN_WIDTH = 1280;
const ACTION_LABELS = ['Edit', 'Detail', 'Hapus'];
const VIEWPORT_TOLERANCE_PX = 1;

/** Selisih `scrollWidth - clientWidth` sebuah elemen; `-1` bila elemen tidak ada. */
async function overflowPixels(page: Page, selector: string): Promise<number> {
    return page.evaluate((target: string): number => {
        const element = target === 'document' ? document.scrollingElement : document.querySelector(target);

        if (element === null) return -1;

        return element.scrollWidth - element.clientWidth;
    }, selector);
}

test.describe('direktori pengguna (breakpoint)', () => {
    test('layout utuh pada setiap breakpoint target', async ({ page }) => {
        for (const width of BREAKPOINTS) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

            const visibleRow = page.locator('[data-testid="user-row"]:visible').first();
            await expect(visibleRow, `baris tidak tampil pada ${width}px`).toBeVisible();

            expect(await overflowPixels(page, 'document'), `document overflow pada ${width}px`).toBeLessThanOrEqual(
                VIEWPORT_TOLERANCE_PX
            );
            expect(
                await overflowPixels(page, '[data-testid="dashboard-content"]'),
                `dashboard-content overflow pada ${width}px`
            ).toBeLessThanOrEqual(VIEWPORT_TOLERANCE_PX);

            const table = page.locator('[data-testid="user-directory"] table');
            const visibleCardCount = (): Promise<number> =>
                page.locator('article[data-testid="user-row"]:visible').count();

            if (width >= TABLE_MIN_WIDTH) {
                await expect(table, `tabel harus tampil pada ${width}px`).toBeVisible();
                expect(await visibleCardCount(), `kartu harus tersembunyi pada ${width}px`).toBe(0);
            } else {
                await expect(table, `tabel harus tersembunyi pada ${width}px`).toBeHidden();
                expect(await visibleCardCount(), `kartu harus tampil pada ${width}px`).toBeGreaterThan(0);
            }

            // Aksi selalu berlabel teks, tidak pernah ikon tanpa nama.
            const detailAction = visibleRow.locator('[data-testid="user-row-detail"]');
            await expect(detailAction, `aksi Detail tidak tampil pada ${width}px`).toBeVisible();
            expect((await detailAction.innerText()).trim()).toBe('Detail');

            const visibleActions = visibleRow.locator(
                '[data-testid="user-row-edit"]:visible, [data-testid="user-row-detail"]:visible, [data-testid="user-row-delete"]:visible'
            );
            const actionCount = await visibleActions.count();
            expect(actionCount, `tidak ada aksi pada ${width}px`).toBeGreaterThan(0);

            for (let index = 0; index < actionCount; index += 1) {
                const label = (await visibleActions.nth(index).innerText()).trim();

                expect(ACTION_LABELS, `label aksi "${label}" pada ${width}px`).toContain(label);
            }

            const joinedHeader = page.getByRole('columnheader', { name: 'Bergabung' });

            if (width >= JOINED_COLUMN_MIN_WIDTH) {
                await expect(joinedHeader, `kolom Bergabung harus tampil pada ${width}px`).toBeVisible();
            } else {
                await expect(joinedHeader, `kolom Bergabung harus tersembunyi pada ${width}px`).toBeHidden();
            }
        }
    });
});
