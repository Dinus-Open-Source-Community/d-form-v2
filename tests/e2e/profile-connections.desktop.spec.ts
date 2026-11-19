import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { readEnvironment } from './support/environment';
import { expect, test } from './support/fixtures';
import { parseTinkerJsonRecord } from './support/tinker';

/**
 * Koneksi akun pada halaman profil: provider yang belum dikonfigurasi tidak
 * boleh menawarkan tombol aktif, alur hubungkan harus memakai callback URI yang
 * sudah terdaftar, callback tanpa state tidak boleh mengubah data, dan tanpa
 * koneksi tidak ada aksi putuskan yang bisa ditekan.
 *
 * Fokus spec ini adalah UI + redirect. Tombol "Hubungkan" tidak pernah diklik
 * karena akan meninggalkan aplikasi menuju provider.
 */

const CONFIG_PROVIDERS = ['github', 'google'] as const;

/** Ambil client id provider dari config Laravel untuk membuktikan fakta environment. */
function readConfiguredClientIds(): Record<(typeof CONFIG_PROVIDERS)[number], string> {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        "echo json_encode(['github' => config('services.github.client_id'), 'google' => config('services.google.client_id')]);",
    ]);

    const record = parseTinkerJsonRecord(raw, 'services config');

    const configured = (provider: string): string => {
        const value = record[provider];

        return typeof value === 'string' ? value.trim() : '';
    };

    return { github: configured('github'), google: configured('google') };
}

/** Hitung akun admin yang punya provider id terisi; bukti tidak ada perubahan data. */
function countAdminProviderIds(provider: (typeof CONFIG_PROVIDERS)[number]): number {
    const column = provider === 'github' ? 'github_id' : 'google_id';
    const emailLiteral = JSON.stringify(readEnvironment().adminEmail);
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo App\\Models\\User::query()->where('email', ${emailLiteral})->whereNotNull('${column}')->count();`,
    ]);

    return Number(raw.trim());
}

async function openProfile(page: Page): Promise<void> {
    const response = await page.goto('/profile', { waitUntil: 'domcontentloaded' });

    expect(response?.status() ?? 0).toBe(200);
    await expect(page.getByTestId('profile-section-connections')).toBeVisible({ timeout: 20_000 });
}

test.describe('profil: koneksi akun', () => {
    test('fakta environment: client id GitHub terisi, Google kosong', () => {
        const clientIds = readConfiguredClientIds();

        expect(clientIds.github, 'GITHUB_CLIENT_ID harus terisi untuk menguji alur hubungkan').not.toBe('');
        expect(clientIds.google, 'GOOGLE_CLIENT_ID harus kosong pada environment ini').toBe('');

        // Prasyarat status baris: admin belum terhubung ke provider mana pun.
        expect(countAdminProviderIds('github'), 'admin tidak boleh terhubung ke GitHub').toBe(0);
        expect(countAdminProviderIds('google'), 'admin tidak boleh terhubung ke Google').toBe(0);
    });

    /*
     * Callback diuji sebelum alur hubungkan GitHub: memulai bind menyimpan intent
     * di session bersama, dan callback berikutnya akan memakai intent itu.
     */
    test('callback tanpa state saat login: kembali ke #koneksi, toast error, tanpa perubahan data', async ({ page }) => {
        const callbackResponse = page.waitForResponse(
            (response): boolean => new URL(response.url()).pathname === '/auth/github/callback'
        );

        // Navigasi browser mengikuti redirect seperti callback provider sungguhan.
        await page.goto('/auth/github/callback?code=x', { waitUntil: 'domcontentloaded' });

        expect((await callbackResponse).status(), 'callback harus redirect, bukan error server').toBe(302);

        const landed = new URL(page.url());

        expect(landed.pathname, 'kegagalan bind harus kembali ke profil').toBe('/profile');
        expect(landed.hash).toBe('#koneksi');

        await expect(
            page
                .locator('[data-sonner-toast]')
                .filter({ hasText: 'Sesi menghubungkan akun tidak valid atau sudah kedaluwarsa. Silakan coba lagi.' })
        ).toBeVisible({ timeout: 15_000 });

        const githubRow = page.getByTestId('profile-connection-github');

        await expect(githubRow).toContainText('Belum terhubung');
        await expect(githubRow.getByTestId('profile-connection-connect')).toBeEnabled();

        // Tidak ada state koneksi yang berubah setelah callback gagal.
        expect(countAdminProviderIds('github'), 'callback tanpa state tidak boleh menyimpan github_id').toBe(0);
        expect(countAdminProviderIds('google'), 'callback tanpa state tidak boleh menyimpan google_id').toBe(0);
    });

    test('GitHub: hubungkan mengarah ke authorize provider dengan callback URI terdaftar', async ({ page }) => {
        const response = await page.request.get('/profile/connections/github', {
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        expect(response.status()).toBe(302);

        const location = new URL(response.headers()['location'] ?? '');

        expect(location.origin).toBe('https://github.com');
        expect(location.pathname).toBe('/login/oauth/authorize');

        const redirectUri = location.searchParams.get('redirect_uri') ?? '';

        expect(redirectUri.endsWith('/auth/github/callback'), `redirect_uri tidak sesuai: ${redirectUri}`).toBe(true);
        expect(location.searchParams.get('client_id') ?? '').not.toBe('');
        expect(location.searchParams.get('state') ?? '', 'state wajib ada untuk bind').not.toBe('');
    });

    test('Google: baris tanpa tombol hubungkan dan route langsung tidak ke provider', async ({ page }) => {
        await openProfile(page);

        const googleRow = page.getByTestId('profile-connection-google');

        await expect(googleRow).toBeVisible();
        await expect(googleRow).toContainText('Google');

        // Provider belum dikonfigurasi: status polos, tanpa tombol mati sama sekali.
        await expect(googleRow.getByTestId('profile-connection-blocked')).toHaveText('Belum tersedia di server ini.');
        await expect(googleRow).not.toContainText('Belum terhubung');
        await expect(googleRow.getByTestId('profile-connection-connect')).toHaveCount(0);
        await expect(googleRow.getByRole('button', { name: 'Hubungkan', exact: true })).toHaveCount(0);

        // Provider yang dikonfigurasi tetap menawarkan tombol aktif sebagai pembanding.
        const githubConnect = page.getByTestId('profile-connection-github').getByTestId('profile-connection-connect');

        await expect(githubConnect).toBeVisible();
        await expect(githubConnect).toBeEnabled();
        await expect(githubConnect).toContainText('Hubungkan');

        /*
         * Navigasi lewat browser (bukan request mentah) karena flash toast baru
         * dirender pada permukaan halaman yang menggantikan redirect; ini juga
         * membuktikan server tidak mengirim user ke halaman consent Google.
         */
        const redirectResponse = page.waitForResponse(
            (response): boolean => new URL(response.url()).pathname === '/profile/connections/google'
        );

        await page.goto('/profile/connections/google', { waitUntil: 'domcontentloaded' });

        expect((await redirectResponse).status(), 'provider belum dikonfigurasi harus redirect, bukan 500').toBe(302);

        const landed = new URL(page.url());

        expect(landed.hostname, 'tidak boleh mendarat di provider').not.toBe('accounts.google.com');
        expect(landed.hostname).not.toContain('google.com');
        expect(landed.pathname).toBe('/profile');
        expect(landed.hash).toBe('#koneksi');

        await expect(
            page.locator('[data-sonner-toast]').filter({ hasText: 'Belum tersedia di server ini.' })
        ).toBeVisible({ timeout: 15_000 });
    });

    test('tanpa provider terhubung tidak ada tombol putuskan yang aktif', async ({ page }) => {
        await openProfile(page);

        // Google belum dikonfigurasi: status "Belum tersedia" dan tanpa aksi apa pun.
        const googleRow = page.getByTestId('profile-connection-google');

        await expect(googleRow).toBeVisible();
        await expect(googleRow.getByTestId('profile-connection-blocked')).toHaveText('Belum tersedia di server ini.');
        await expect(googleRow.getByRole('button')).toHaveCount(0);

        // GitHub dikonfigurasi tetapi belum terhubung: hanya tombol hubungkan.
        const githubRow = page.getByTestId('profile-connection-github');

        await expect(githubRow).toBeVisible();
        await expect(githubRow).toContainText('Belum terhubung');
        await expect(githubRow.getByTestId('profile-connection-connect')).toBeEnabled();
        await expect(githubRow.getByTestId('profile-connection-disconnect')).toHaveCount(0);

        await expect(page.getByTestId('profile-connection-disconnect')).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Putuskan' })).toHaveCount(0);
    });
});
