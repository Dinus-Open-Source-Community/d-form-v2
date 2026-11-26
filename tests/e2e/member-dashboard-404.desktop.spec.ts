import { expect, test } from './support/fixtures';
import {
    cleanupJoinedMemberRegistrationFixture,
    createJoinedMemberRegistrationFixture,
    signInJoinedMember,
    type IJoinedMemberRegistrationFixture,
} from './support/joinedMemberRegistration';

/**
 * Dasbor organizer hanya untuk `events.list`. Peserta (role `member`) harus
 * mendapat 404 langsung di `/dashboard` tanpa redirect, sementara portal
 * peserta `/joined` tetap 200.
 */
test.describe('member dashboard access', () => {
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

    test('returns 404 on /dashboard without redirect and keeps /joined reachable', async ({ page }) => {
        const currentFixture = fixture;
        expect(currentFixture).not.toBeNull();
        if (currentFixture === null) return;

        await signInJoinedMember(page, currentFixture);

        // Login peserta mendarat di portal, bukan dasbor organizer.
        await expect(page).toHaveURL('/joined');

        const dashboardResponse = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        expect(dashboardResponse?.status() ?? 0).toBe(404);
        expect(new URL(page.url()).pathname, '/dashboard tidak boleh redirect untuk peserta').toBe('/dashboard');

        const joinedResponse = await page.goto('/joined', { waitUntil: 'domcontentloaded' });

        expect(joinedResponse?.status() ?? 0).toBe(200);
        expect(new URL(page.url()).pathname).toBe('/joined');
    });
});
