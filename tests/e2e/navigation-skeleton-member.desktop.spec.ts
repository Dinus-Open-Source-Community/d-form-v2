import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import {
    cleanupJoinedMemberRegistrationFixture,
    createJoinedMemberRegistrationFixture,
    signInJoinedMember,
    type IJoinedMemberRegistrationFixture,
} from './support/joinedMemberRegistration';

/** Menunda hanya request Inertia agar skeleton sempat terlihat. */
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

function sidebarMenu(page: Page) {
    return sidebar(page).locator('[data-slot="sidebar-content"]');
}

function skeleton(page: Page) {
    return page.getByTestId('navigation-skeleton');
}

test.describe('navigation skeleton for member', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    let fixture: IJoinedMemberRegistrationFixture | null = null;

    test.beforeEach(() => {
        fixture = createJoinedMemberRegistrationFixture();
    });

    test.afterEach(() => {
        if (fixture !== null) {
            cleanupJoinedMemberRegistrationFixture(fixture);
            fixture = null;
        }
    });

    test('uses the joined list and event detail skeletons without a dashboard entry point', async ({ page }) => {
        const currentFixture = fixture;
        expect(currentFixture).not.toBeNull();
        if (currentFixture === null) return;

        await signInJoinedMember(page, currentFixture);

        // Peserta mendarat di portal, bukan dasbor organizer.
        await expect(page).toHaveURL('/joined');

        // Sidebar peserta hanya memuat menu portal: tidak ada tautan dasbor.
        await expect(sidebarMenu(page).locator('a[href="/dashboard"]')).toHaveCount(0);
        await expect(sidebarMenu(page).getByText('Beranda', { exact: true })).toHaveCount(0);
        await expect(sidebarMenu(page).locator('a[href="/joined"]')).toHaveCount(1);
        await expect(sidebarMenu(page).locator('a[href="/browse"]')).toHaveCount(1);

        // Logo sidebar juga mengarah ke portal peserta.
        await expect(sidebar(page).getByRole('link', { name: 'DForm' }).first()).toHaveAttribute('href', '/joined');

        await delayInertiaRequests(page, 700);

        await sidebarMenu(page).locator('a[href="/browse"]').first().click();
        await expect(skeleton(page)).toBeVisible();
        await expect(skeleton(page)).toHaveAttribute('data-skeleton-key', 'user-events');
        await expect(skeleton(page)).toBeHidden({ timeout: 15000 });
        await expect(page).toHaveURL('/browse');

        await sidebarMenu(page).locator('a[href="/joined"]').first().click();
        await expect(skeleton(page)).toBeVisible();
        await expect(skeleton(page)).toHaveAttribute('data-skeleton-key', 'user-events');
        await expect(skeleton(page)).toBeHidden({ timeout: 15000 });
        await expect(page).toHaveURL('/joined');

        await page.locator('a[href^="/joined/"]').first().click();
        await expect(skeleton(page)).toBeVisible();
        await expect(skeleton(page)).toHaveAttribute('data-skeleton-key', 'user-event-detail');
        await expect(skeleton(page)).toBeHidden({ timeout: 15000 });
        await expect(page).toHaveURL(/\/joined\/[^/]+$/);
    });
});
