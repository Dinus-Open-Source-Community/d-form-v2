import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';

/**
 * Regresi FE-AUDIT-004-F4: `Cache::flush()` di satu spec tidak boleh mematikan
 * sesi admin pada spec berikutnya saat server memakai session driver `file`.
 *
 * Dengan driver sesi Redis, `Cache::flush()` menghapus session store yang sama;
 * set `E2E_SESSION_DRIVER=redis` agar spec ini di-skip (lihat README).
 */
test.describe('H-session-isolation: cache flush tidak memutus sesi admin', () => {
    test.skip(
        process.env.E2E_SESSION_DRIVER === 'redis',
        'Redis session driver stores sessions in the cache store flushed by Cache::flush().'
    );

    test('flush cache lalu tetap terautentikasi', async ({ page }): Promise<void> => {
        runArtisanCommand(['tinker', '--execute', '\\Illuminate\\Support\\Facades\\Cache::flush();']);

        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        expect(new URL(page.url()).pathname).toBe('/dashboard');
    });

    test('spec berikutnya masih terautentikasi setelah flush sebelumnya', async ({ page }): Promise<void> => {
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        expect(new URL(page.url()).pathname).toBe('/dashboard');
    });
});
