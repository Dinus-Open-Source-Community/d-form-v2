import type { Page, TestInfo } from '@playwright/test';
import { cleanupOwnerFixture, createOwnerFixture, expect, test } from './support/fixtures';
import { readEnvironment } from './support/environment';
import { readInertiaPage, readXsrfToken } from './support/network';
import { createOwnerIdentity, createRunId, type IOwnerIdentity } from './support/marker';

/**
 * AUDIT-P1-015-F5: seluruh spec admin di CI login sebagai `superadmin@gmail.com`,
 * sehingga jalur 403 non-super-admin tidak pernah dibuktikan. Spec ini login
 * sebagai admin seeded (role `admin`) lalu memeriksa bahwa `EventPolicy` membatasi
 * event ke `created_by` tanpa bypass super-admin.
 *
 * Kredensial dibaca lewat seam `readEnvironment()`; default-nya user seeded `UserSeeder`.
 */
const OWNED_EVENT_TEST_SLUG = 'admin-owned-event';

/** Login lewat HTTP (pola `auth.setup.ts`) lalu buktikan sesi benar-benar aktif di `/dashboard`. */
async function signInAsNonSuperAdmin(page: Page): Promise<void> {
    const environment = readEnvironment();

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });

    const xsrfToken = await readXsrfToken(page.context());

    await page.context().request.post('/auth/login', {
        form: { email: environment.nonAdminEmail, password: environment.nonAdminPassword },
        headers: { 'X-XSRF-TOKEN': xsrfToken, Accept: 'text/html' },
        maxRedirects: 0,
        failOnStatusCode: false,
    });

    // Dashboard 200 membuktikan login berhasil; tanpa ini 403 berikutnya tidak bermakna.
    const dashboardResponse = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    expect(new URL(page.url()).pathname).toBe('/dashboard');
    expect(dashboardResponse?.status() ?? 0).toBe(200);
}

/** Identitas fixture kedua: event yang dimiliki user non-super-admin sendiri. */
function createOwnedEventIdentity(testInfo: TestInfo): IOwnerIdentity {
    return createOwnerIdentity(createRunId(), {
        project: testInfo.project.name,
        specSlug: 'non-superadmin-403',
        testSlug: OWNED_EVENT_TEST_SLUG,
        retry: testInfo.retry,
    });
}

/**
 * Buka halaman admin event dan assert status persis.
 * Redirect akan mengubah URL akhir, jadi kecocokan pathname sekaligus membuktikan tidak ada redirect.
 */
async function expectEventPageStatus(page: Page, eventId: string, expectedStatus: number): Promise<void> {
    for (const path of [`/admin/events/${eventId}`, `/admin/events/${eventId}/edit`]) {
        const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

        expect(response?.status() ?? 0, `${path} harus ${expectedStatus}`).toBe(expectedStatus);
        expect(new URL(page.url()).pathname, `${path} tidak boleh redirect`).toBe(path);

        const rendered = await readInertiaPage(page);

        if (expectedStatus === 403) {
            expect(rendered.component, `${path} harus halaman Error Inertia`).toBe('Error');
        } else {
            expect(rendered.component.startsWith('Dashboard/Events/'), `${path} harus halaman event`).toBe(true);
        }
    }
}

test.describe('AUDIT-P1-015-F5 scoping event untuk admin non-super-admin', () => {
    // Project desktop memakai storageState super-admin; spec ini butuh sesi admin biasa.
    test.use({ storageState: { cookies: [], origins: [] } });

    test('403 pada event milik user lain dan 200 pada event sendiri', async ({ page, fixtureOwner }): Promise<void> => {
        test.setTimeout(90_000);

        await signInAsNonSuperAdmin(page);

        const ownIdentity = createOwnedEventIdentity(test.info());
        const ownEvent = createOwnerFixture(ownIdentity, readEnvironment().nonAdminEmail);

        try {
            // Event milik marker user: admin non-super-admin tidak punya bypass `created_by`.
            await expectEventPageStatus(page, fixtureOwner.eventId, 403);

            // Kontrol positif: event yang `created_by`-nya admin ini tetap terbuka.
            await expectEventPageStatus(page, ownEvent.eventId, 200);
        } finally {
            cleanupOwnerFixture(ownIdentity);
        }
    });
});
