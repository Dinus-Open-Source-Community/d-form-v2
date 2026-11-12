import { expect, test, type Locator, type Page } from '@playwright/test';
import { runArtisan } from './support/artisan';
import { readEnvironment } from './support/environment';
import { startRequestLog } from './support/network';

// Halaman auth adalah guest-only; state admin project akan di-redirect 302.
test.use({ storageState: { cookies: [], origins: [] } });

const EMAIL_AVAILABILITY_PATTERN = /\/auth\/email-availability$/;
const STATUS_SELECTOR = '[data-email-availability]';
// Akun admin selalu ada di dataset E2E; email stress bisa tidak ikut ter-seed.
const TAKEN_EMAIL = readEnvironment().adminEmail;
const SETTLE_TIMEOUT_MS = 2_000;

/** Kosongkan cache limiter agar kuota availability 20/menit tidak bocor antar test. */
function resetThrottleBuckets(): void {
    runArtisan('\\Illuminate\\Support\\Facades\\Cache::flush();');
}

/** Container input email register, agar tidak tertukar dengan input login yang ikut ter-mount. */
function registerEmailGroup(page: Page): Locator {
    return page.locator('[data-slot="input-group"]').filter({ has: page.locator('#register-email') });
}

/**
 * Isi input email panel register yang sedang tersembunyi lalu kirim event `input`
 * seperti autofill/script. `inert` hanya memblokir interaksi pengguna, bukan dispatch programatik.
 */
async function injectHiddenRegisterEmail(page: Page, email: string): Promise<void> {
    await page.locator('#register-email').evaluate<void, string, HTMLInputElement>((element, value) => {
        element.value = value;
        element.dispatchEvent(new Event('input', { bubbles: true }));
    }, email);
}

/**
 * Perlambat respons availability agar transisi `checking` sempat teramati.
 * Dipasang setelah request pertama selesai, sebelum request berikutnya diharapkan.
 */
async function slowAvailabilityResponse(page: Page): Promise<void> {
    await page.route(EMAIL_AVAILABILITY_PATTERN, async (route) => {
        await new Promise<void>((resolve) => {
            setTimeout(resolve, 600);
        });
        await route.continue();
    });
}

test.describe('auth tabs request count', () => {
    test.beforeEach(() => {
        resetThrottleBuckets();
    });

    test('tab Masuk aktif tidak mengirim request availability dari panel register tersembunyi', async ({ page }) => {
        const requests = startRequestLog(page);

        try {
            await page.goto('/auth/login');
            await page.locator('#register-email').waitFor({ state: 'attached' });

            expect(requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN)).toBe(0);

            // Panel register ter-mount tetapi tidak terpilih: perubahan nilainya tidak boleh memicu efek.
            const injectedEmail = `e2e-hidden-${Date.now()}@example.test`;
            await injectHiddenRegisterEmail(page, injectedEmail);
            await expect
                .poll(() => requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN), { timeout: SETTLE_TIMEOUT_MS })
                .toBe(0);

            // Kontrol positif: nilai injeksi benar-benar masuk ke v-model, sehingga nol di atas tidak vakum.
            await page.getByRole('tab', { name: 'Daftar' }).click();
            await expect
                .poll(() => requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN), { timeout: SETTLE_TIMEOUT_MS })
                .toBe(1);
            await expect(registerEmailGroup(page).locator(STATUS_SELECTOR)).toHaveAttribute(
                'data-email-availability',
                'available',
                { timeout: 5_000 }
            );
        } finally {
            requests.stop();
        }
    });

    test('pindah ke tab Daftar lalu mengetik email valid memicu tepat satu request', async ({ page }) => {
        const requests = startRequestLog(page);

        try {
            await page.goto('/auth/login');

            await page.getByRole('tab', { name: 'Daftar' }).click();
            await expect(page.getByRole('tab', { name: 'Daftar' })).toHaveAttribute('data-state', 'active');

            expect(requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN)).toBe(0);

            await page.locator('#register-email').fill(`e2e-tabs-available-${Date.now()}@example.test`);

            const status = registerEmailGroup(page).locator(STATUS_SELECTOR);
            await expect(status).toHaveAttribute('data-email-availability', 'available', { timeout: 5_000 });

            // Satu `fill` = satu event input = satu request setelah debounce.
            expect(requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN)).toBe(1);
        } finally {
            requests.stop();
        }
    });

    test('email baru yang diisi saat panel tersembunyi tidak mempertahankan status email lama', async ({ page }) => {
        const requests = startRequestLog(page);

        try {
            await page.goto('/auth/login');
            await page.getByRole('tab', { name: 'Daftar' }).click();

            const status = registerEmailGroup(page).locator(STATUS_SELECTOR);
            const firstEmail = `e2e-stale-a-${Date.now()}@example.test`;

            await page.locator('#register-email').fill(firstEmail);
            await expect(status).toHaveAttribute('data-email-availability', 'available', { timeout: 5_000 });
            expect(requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN)).toBe(1);

            await page.getByRole('tab', { name: 'Masuk' }).click();
            await slowAvailabilityResponse(page);

            // Email B (terpakai) diisi saat panel register tersembunyi.
            await injectHiddenRegisterEmail(page, TAKEN_EMAIL);
            expect(requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN)).toBe(1);

            await page.getByRole('tab', { name: 'Daftar' }).click();

            // B dicek ulang: status harus melewati `checking`, bukan mempertahankan `available` milik A.
            await expect(status).toHaveAttribute('data-email-availability', 'checking', { timeout: 3_000 });
            await expect(status).toHaveAttribute('data-email-availability', 'taken', { timeout: 5_000 });
            await expect
                .poll(() => requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN), { timeout: SETTLE_TIMEOUT_MS })
                .toBe(2);
        } finally {
            requests.stop();
        }
    });

    test('state form register tetap terjaga setelah pindah tab', async ({ page }) => {
        await page.goto('/auth/login');

        await page.getByRole('tab', { name: 'Daftar' }).click();

        const name = `E2E Keep Alive ${Date.now()}`;
        await page.locator('#register-name').fill(name);

        await page.getByRole('tab', { name: 'Masuk' }).click();
        await page.getByRole('tab', { name: 'Daftar' }).click();

        await expect(page.locator('#register-name')).toHaveValue(name);
    });

    test('email tidak valid pada tab Daftar tidak memicu request availability', async ({ page }) => {
        const requests = startRequestLog(page);

        try {
            await page.goto('/auth/login');

            await page.getByRole('tab', { name: 'Daftar' }).click();
            await page.locator('#register-email').fill('bukan-email');
            await expect
                .poll(() => requests.requestCount('POST', EMAIL_AVAILABILITY_PATTERN), { timeout: SETTLE_TIMEOUT_MS })
                .toBe(0);
        } finally {
            requests.stop();
        }
    });
});
