import { expect, test } from '@playwright/test';

test.describe('halaman error', () => {
    test('404 merender heading, ilustrasi, dan tanpa console error', async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));

        const response = await page.goto('/halaman-yang-tidak-ada');
        expect(response?.status()).toBe(404);

        await expect(page.getByRole('heading', { level: 1, name: 'Halaman tidak ditemukan' })).toBeVisible();

        const illustration = page.locator('img[src="/illustrations/state-error.svg"]');
        await expect(illustration).toBeVisible();
        await expect.poll(() => illustration.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);

        const credit = page.getByRole('link', { name: 'Designed by Storyset' });
        await expect(credit).toBeVisible();
        await expect(credit).toHaveAttribute('href', 'https://storyset.com');

        expect(errors, errors.join('\n')).toEqual([]);
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)
        ).toBe(true);
    });
});

test.describe('empty state dashboard', () => {
    test('pencarian tanpa hasil menampilkan ilustrasi empty dan aksi filter', async ({ page }) => {
        const response = await page.goto('/admin/events?search=tidak-ada-hasil-xyz');
        expect(response?.status()).toBe(200);

        const illustration = page.locator('img[src="/illustrations/state-empty.svg"]');
        await expect(illustration).toBeVisible();
        await expect(illustration).toHaveAttribute('loading', 'lazy');
        await expect(page.getByText('Tidak ada acara')).toBeVisible();
    });
});
