import { expect, test } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';

// Callback OAuth selalu dimulai dari guest; state login admin tidak relevan di sini.
test.use({ storageState: { cookies: [], origins: [] } });

const PROVIDERS = ['google', 'github'] as const;

/** Hitung baris users yang cocok lewat tinker; dipakai untuk membuktikan tidak ada efek samping DB. */
function countUsersWhere(condition: string): number {
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        `echo App\\Models\\User::query()->${condition}->count();`,
    ]);

    return Number(output.trim());
}

/** Ambil path redirect dari response 302 tanpa mengikuti Location ke host eksternal. */
function redirectPath(location: string | undefined): string {
    return new URL(location ?? '', 'http://localhost').pathname;
}

test.describe('oauth provider redirect', () => {
    test('google meminta scope openid/email/profile dan callback uri yang benar', async ({ page }) => {
        const response = await page.request.get('/auth/google', {
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        expect(response.status()).toBe(302);

        const location = new URL(response.headers()['location'] ?? '');

        expect(location.origin).toBe('https://accounts.google.com');
        expect(location.searchParams.get('scope')?.split(' ')).toEqual(
            expect.arrayContaining(['openid', 'email', 'profile'])
        );
        expect(location.searchParams.get('redirect_uri')).toContain('/auth/google/callback');
    });

    test('github meminta scope user:email dan callback uri yang benar', async ({ page }) => {
        const response = await page.request.get('/auth/github', {
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        expect(response.status()).toBe(302);

        const location = new URL(response.headers()['location'] ?? '');

        expect(location.origin).toBe('https://github.com');
        expect(location.pathname).toBe('/login/oauth/authorize');
        expect(location.searchParams.get('scope')).toBe('user:email');
        expect(location.searchParams.get('redirect_uri')).toContain('/auth/github/callback');
    });
});

test.describe('oauth callback anomaly', () => {
    for (const provider of PROVIDERS) {
        test(`${provider} callback tanpa state ditolak tanpa membuat sesi`, async ({ page }) => {
            const response = await page.request.get(`/auth/${provider}/callback`, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });

            expect(response.status()).toBe(302);
            expect(redirectPath(response.headers()['location'])).toBe('/auth/login');

            await page.goto('/auth/login');

            await expect(
                page
                    .locator('[data-sonner-toast]')
                    .filter({ hasText: new RegExp(`gagal masuk dengan ${provider}`, 'i') })
            ).toBeVisible({ timeout: 15_000 });

            const dashboard = await page.request.get('/dashboard', {
                maxRedirects: 0,
                failOnStatusCode: false,
            });

            expect(dashboard.status()).toBe(302);
            expect(redirectPath(dashboard.headers()['location'])).toBe('/auth/login');
        });

        test(`${provider} callback dengan state palsu ditolak tanpa server error`, async ({ page }) => {
            const response = await page.request.get(`/auth/${provider}/callback?state=forged-state&code=forged-code`, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });

            expect(response.status()).toBe(302);
            expect(redirectPath(response.headers()['location'])).toBe('/auth/login');
        });
    }

    test('callback oauth tidak pernah membuat email sintetis github.local', () => {
        expect(countUsersWhere("where('email', 'like', '%@github.local')")).toBe(0);
    });
});
