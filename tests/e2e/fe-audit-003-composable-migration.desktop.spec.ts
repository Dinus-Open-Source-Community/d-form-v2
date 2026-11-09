import { expect, test } from './support/fixtures';

/**
 * Regression for FE-AUDIT-003: composables moved from `utils/composables` to
 * `hooks/`. The suite exercises the two heaviest consumers that had no direct
 * E2E coverage before the move: the dashboard layout chain (`useAuth`,
 * `useDashboardTopbar`, `usePageFlashToast`) and the global QR scan page
 * (`useGlobalQrScanPage`).
 */
test.describe('FE-AUDIT-003 composable migration', () => {
    test('dashboard renders and applies topbar state from moved hooks', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        const response = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        expect(response?.status()).toBe(200);
        await expect(page.locator('body')).toBeVisible();

        // `useDashboardTopbar.setTopbar` drives the shell heading: if the moved
        // module graph broke, this title would not render.
        await expect(page.getByRole('heading', { name: 'Ringkasan acara', exact: true })).toBeVisible();
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    });

    test('scan page rejects an invalid manual code without a 5xx or crash', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        const response = await page.goto('/admin/scan', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);

        await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

        const manualInput = page.getByPlaceholder('OPREC-2026-00001 atau kode registrasi event');
        await manualInput.fill('TIDAK-VALID-000X');

        const submitResponse = page.waitForResponse(
            (res) => res.request().method() === 'POST' && new URL(res.url()).pathname === '/admin/scan',
        );

        await page.getByRole('button', { name: 'Proses check-in' }).click();

        const result = await submitResponse;

        // Invalid manual input is a validation error, never a server failure.
        expect(result.status()).toBe(422);

        // The hook must surface recoverable feedback instead of swallowing the error.
        await expect(page.getByText('Tidak dapat diproses', { exact: true })).toBeVisible();
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    });
});
