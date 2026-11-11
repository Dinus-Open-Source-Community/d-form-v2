import { expect, test } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test('FE_AUDIT_014 registers focus directive once and focuses the real login control', async ({ page }) => {
    await page.goto('/auth/login');
    await expect(page.locator('#login-email')).toBeFocused();
});
