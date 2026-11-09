import { randomUUID } from 'node:crypto';
import { expect, test } from './support/fixtures';
import { readInertiaPage, readPageProps, readXsrfToken } from './support/network';

/**
 * AUDIT-P1-015 — perilaku default modul broadcast saat `FEATURE_BROADCAST=false`.
 *
 * `routes/web/admin/broadcasting.php` berhenti sebelum mendaftarkan route saat flag
 * mati, sehingga `/admin/broadcasts*` harus 404 (bukan 403). Sidebar hanya merender
 * link saat `auth.user.can_access_broadcast === true`.
 *
 * Tanpa suffix `.desktop`: spec harus berlaku sama di project desktop dan mobile.
 * Jalur enabled sudah ditangani `broadcast-dispatch-claim.desktop.spec.ts`.
 */
const broadcastFeatureEnabled = process.env.E2E_FEATURE_BROADCAST === 'true';

interface IBroadcastGateUser {
    can_access_broadcast: boolean;
}

interface IDashboardGateProps {
    auth: { user: IBroadcastGateUser | null };
}

test.describe('AUDIT-P1-015 broadcast gate (FEATURE_BROADCAST=false)', () => {
    test.skip(
        broadcastFeatureEnabled,
        'FEATURE_BROADCAST aktif di server ini; jalur enabled ditangani broadcast-dispatch-claim.desktop.spec.ts.'
    );

    test('route broadcast tidak terdaftar dan halaman 404 dirender sebagai Error', async ({ page }) => {
        for (const path of ['/admin/broadcasts', '/admin/broadcasts/create']) {
            const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

            expect(response?.status(), `${path} harus 404 saat flag mati`).toBe(404);
            expect((await readInertiaPage(page)).component, `${path} harus halaman Error Inertia`).toBe('Error');
        }
    });

    test('dashboard menyembunyikan menu dan prop can_access_broadcast=false', async ({ page }) => {
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        // Tunggu layout terhidrasi agar absennya link bukan sekadar render tertunda.
        await expect(page.getByTestId('dashboard-content')).toBeVisible();

        // Di mobile sidebar adalah sheet tertutup, jadi absennya link tidak
        // membuktikan gate. Prop `can_access_broadcast` adalah bukti yang mengikat
        // untuk kedua project.
        if (test.info().project.name === 'chromium-desktop') {
            await expect(page.locator('a[href*="/admin/broadcasts"]')).toHaveCount(0);
            await expect(page.locator('a', { hasText: /Email Broadcasting/i })).toHaveCount(0);
        }

        const props = await readPageProps<IDashboardGateProps>(page);
        expect(props.auth.user, 'user admin harus terautentikasi').not.toBeNull();
        expect(props.auth.user?.can_access_broadcast).toBe(false);
    });

    test('bypass POST store dan GET detail UUID acak tetap 404 tanpa 5xx', async ({ page }) => {
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        const xsrfToken = await readXsrfToken(page.context());

        const storeResponse = await page.request.post('/admin/broadcasts', {
            headers: {
                Accept: 'text/html',
                'X-XSRF-TOKEN': xsrfToken,
            },
            failOnStatusCode: false,
            maxRedirects: 0,
        });

        expect(storeResponse.status(), 'POST store tidak terdaftar').toBe(404);

        const detailResponse = await page.request.get(`/admin/broadcasts/${randomUUID()}`, {
            headers: { Accept: 'text/html' },
            failOnStatusCode: false,
            maxRedirects: 0,
        });

        expect(detailResponse.status(), 'GET detail UUID acak tidak terdaftar').toBe(404);
    });
});
