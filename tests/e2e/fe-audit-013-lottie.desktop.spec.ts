import { expect, test } from '@playwright/test';

/**
 * E2E FE-AUDIT-013: halaman landing adalah pemakai Lottie terberat. Vendor
 * `vue3-lottie` harus dimuat sebagai chunk terpisah saat komponen dirender,
 * bukan ikut entry, dan animasi JSON harus benar-benar sampai ke browser.
 *
 * Dampak CSP production tidak diuji di sini (di luar scope environment test).
 */
test.describe('FE-AUDIT-013 lottie lazy loading', () => {
    test('landing memuat chunk lottie dan animasi setelah render', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        const lottieChunk = page.waitForResponse((response) => response.url().includes('vue3-lottie'));
        const lottieAnimation = page.waitForResponse((response) =>
            response.url().endsWith('/lotties/landing-hero.json')
        );

        const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);

        const illustration = page.getByRole('img', { name: 'Ilustrasi pengisian checklist formulir' });
        await expect(illustration).toBeVisible();

        expect((await lottieChunk).ok()).toBe(true);
        expect((await lottieAnimation).ok()).toBe(true);
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    });
});
