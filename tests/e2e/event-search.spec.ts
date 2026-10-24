import { expect, test } from '@playwright/test';
import { runArtisan } from './support/artisan';

test.describe('pencarian acara publik', () => {
    test.beforeEach(() => {
        runArtisan('\\Illuminate\\Support\\Facades\\Cache::flush();');
    });

    test('pesan "terlalu umum" muncul lalu hilang setelah kata kunci diganti', async ({ page }) => {
        // Marker negative-cache dibuat lewat API publik service, bukan lewat query berat.
        runArtisan(
            "$s = app(\\App\\Services\\Event\\PublicEventSearch::class); " +
                "\\Illuminate\\Support\\Facades\\Cache::put($s->cacheKey((string) \\App\\Services\\Event\\PublicEventSearch::terms('event'), 1), ['too_broad' => true], 60);",
        );

        await page.goto('/events?q=event', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('Kata kunci terlalu umum, coba lebih spesifik.')).toBeVisible();

        const input = page.locator('input[placeholder="Cari acara..."]');
        await input.fill('5387');
        await page.waitForURL(/q=5387/);

        await expect(page.getByText('Kata kunci terlalu umum, coba lebih spesifik.')).toBeHidden();
        await expect(page.locator('#event-list h3').first()).toBeVisible();
    });

    test('429 tampil sebagai pesan ramah, bukan modal Inertia', async ({ page }) => {
        await page.goto('/events', { waitUntil: 'domcontentloaded' });

        let blocked = false;
        for (let attempt = 0; attempt < 80 && !blocked; attempt++) {
            const response = await page.request.get('/events');
            blocked = response.status() === 429;
        }
        expect(blocked).toBe(true);

        await page.locator('input[placeholder="Cari acara..."]').fill('5387');

        // Pesan dari aplikasi (bukan halaman error Laravel), shell tetap ada, tanpa modal Inertia.
        await expect(page.getByText(/Terlalu banyak pencarian/)).toBeVisible();
        await expect(page.locator('input[placeholder="Cari acara..."]')).toBeVisible();
        await expect(page.locator('#inertia-error-dialog')).toHaveCount(0);
    });
});
