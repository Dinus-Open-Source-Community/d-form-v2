import { expect, test } from '../support/fixtures';

test.describe('authenticated logout', () => {
    test('sends the logout POST and blocks the dashboard after logout', async ({ page }) => {
        await page.route('**/storage/**', (route) => route.abort());
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('button', { name: 'Keluar' })).toBeVisible();

        const logoutRequestPromise = page.waitForRequest((request) => request.url().endsWith('/auth/logout'));
        const logoutResponsePromise = page.waitForResponse((response) => response.url().endsWith('/auth/logout'));
        const clickPromise = page.getByRole('button', { name: 'Keluar' }).click();
        const logoutRequest = await logoutRequestPromise;
        const logoutResponse = await logoutResponsePromise;
        await clickPromise;

        expect(logoutRequest.method()).toBe('POST');
        expect([302, 303]).toContain(logoutResponse.status());
        await expect(page).toHaveURL(/\/(login|)$/);

        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/auth\/login(?:\?.*)?$/);
    });
});
