import { expect, test } from './support/fixtures';

test.use({ storageState: { cookies: [], origins: [] } });

/**
 * H-auth-guest-redirect: baseline perilaku aplikasi — tamu yang membuka `/dashboard`
 * diarahkan ke halaman login (`/auth/login`) oleh middleware `auth`.
 */
test('H-auth-guest-redirect: tamu diarahkan ke halaman login', async ({ page }): Promise<void> => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveURL(/\/auth\/login$/);
    await expect(page.getByTestId('authenticated-shell')).toHaveCount(0);
});
