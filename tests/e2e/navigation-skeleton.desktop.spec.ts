import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

/**
 * Menunda hanya request Inertia agar skeleton sempat terlihat tanpa memperlambat
 * pemuatan dokumen pertama.
 */
async function delayInertiaRequests(page: Page, ms: number): Promise<void> {
    await page.route('**/*', async (route) => {
        if (route.request().headers()['x-inertia'] !== undefined) {
            await new Promise((resolve) => setTimeout(resolve, ms));
        }

        await route.continue();
    });
}

function sidebar(page: Page) {
    return page.locator('[data-slot="sidebar"]').first();
}

function skeleton(page: Page) {
    return page.getByTestId('navigation-skeleton');
}

/** Grup "Acara" memakai `v-show`; submenu harus dibuka sebelum tautannya bisa diklik. */
async function openEventsGroup(page: Page): Promise<void> {
    const groupButton = sidebar(page).getByRole('button', { name: 'Acara' });

    if ((await groupButton.getAttribute('aria-expanded')) !== 'true') {
        await groupButton.click();
    }
}

test.describe('navigation skeleton for admin', () => {
    test('shows the target skeleton while the sidebar stays mounted', async ({ page }) => {
        await page.goto('/dashboard');
        await delayInertiaRequests(page, 700);
        await openEventsGroup(page);

        await sidebar(page).locator('a[href="/admin/events"]').first().click();

        await expect(skeleton(page)).toBeVisible();
        await expect(skeleton(page)).toHaveAttribute('data-skeleton-key', 'events-index');
        await expect(sidebar(page)).toBeVisible();
        await expect(skeleton(page)).toBeHidden({ timeout: 15000 });
        await expect(page).toHaveURL('/admin/events');
    });

    test('shows the event detail skeleton from a card click', async ({ page }) => {
        await page.goto('/admin/events');
        await delayInertiaRequests(page, 700);

        await page
            .locator('a[href^="/admin/events/"]:not([href*="create"]):not([href*="archived"])')
            .first()
            .click();

        await expect(skeleton(page)).toBeVisible();
        await expect(skeleton(page)).toHaveAttribute('data-skeleton-key', 'events-show');
        await expect(skeleton(page)).toBeHidden({ timeout: 15000 });
        await expect(page).toHaveURL(/\/admin\/events\/[^/]+$/);
    });

    test('keeps the skeleton hidden for pagination reloads', async ({ page }) => {
        await page.goto('/admin/events');
        await delayInertiaRequests(page, 700);

        const nextPage = page.getByRole('button', { name: 'Halaman berikutnya' });

        if ((await nextPage.count()) === 0) {
            test.skip(true, 'butuh lebih dari satu halaman acara untuk menguji pagination');

            return;
        }

        await nextPage.first().click();
        await page.waitForTimeout(400);

        await expect(skeleton(page)).toBeHidden();
        await expect(page).toHaveURL(/page=2/);
    });

    test('does not leave a skeleton behind on browser back', async ({ page }) => {
        // Dua navigasi dokumen dulu agar ada entri history sebelum kunjungan Inertia.
        await page.goto('/dashboard');
        await page.goto('/admin/events');
        await delayInertiaRequests(page, 700);
        await page
            .locator('a[href^="/admin/events/"]:not([href*="create"]):not([href*="archived"])')
            .first()
            .click();
        await expect(skeleton(page)).toBeHidden({ timeout: 15000 });

        await page.goBack();

        // Entri history mana pun yang dipulihkan Inertia, overlay tidak boleh tertinggal.
        await expect(page).not.toHaveURL(/\/admin\/events\/[^/]+$/);
        await expect(skeleton(page)).toBeHidden();
        await expect(page.getByTestId('dashboard-content')).toBeVisible();
    });
});
