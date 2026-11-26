import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

/**
 * Topbar layar sempit harus tetap memberi konteks halaman: judul tampil dan
 * terpotong satu baris, sementara breadcrumb tetap hanya untuk layar lebar.
 */

const TITLE = '[data-testid="topbar-title"]';
const BREADCRUMB = '[aria-label="Breadcrumb"]';

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(
        (): number => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect(overflow).toBeLessThanOrEqual(1);
}

test.describe('topbar mobile', () => {
    test('menampilkan judul halaman dan menyembunyikan breadcrumb', async ({ page }) => {
        await page.goto('/admin/events', { waitUntil: 'domcontentloaded' });

        await expect(page.locator(TITLE)).toBeVisible();
        await expect(page.locator(TITLE)).toHaveText('Acara');
        await expect(page.locator(BREADCRUMB)).toBeHidden();
        await expectNoHorizontalOverflow(page);
    });

    test('judul mengikuti halaman tujuan', async ({ page }) => {
        await page.goto('/profile', { waitUntil: 'domcontentloaded' });

        await expect(page.locator(TITLE)).toBeVisible();
        await expect(page.locator(TITLE)).toHaveText('Profil');
        await expectNoHorizontalOverflow(page);
    });
});
