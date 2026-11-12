import { expect, test } from './support/fixtures';

test.describe('FLOW-P2-006 topbar ownership', () => {
    test('replaces title on dashboard navigation and clears state before logout', async ({ page }) => {
        await page.goto('/admin/events', { waitUntil: 'domcontentloaded' });
        const heading = page.getByRole('heading', { level: 1 });
        await expect(heading).toBeVisible();
        const firstTitle = await heading.textContent();
        expect(firstTitle?.trim()).toBeTruthy();

        await page.getByRole('link', { name: 'Pengguna' }).click();
        await expect(page).toHaveURL(/\/admin\/users(?:\?.*)?$/);
        const nextHeading = page.getByRole('heading', { level: 1 });
        await expect(nextHeading).toBeVisible();
        const nextTitle = await nextHeading.textContent();
        expect(nextTitle?.trim()).toBeTruthy();
        expect(nextTitle?.trim()).not.toBe(firstTitle?.trim());

        await page.getByRole('button', { name: 'Keluar' }).click();
        await expect(page).toHaveURL(/\/(login|)$/);
        await expect(page.getByRole('button', { name: 'Keluar' })).toHaveCount(0);
    });
});
