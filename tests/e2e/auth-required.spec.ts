import { expect, test } from './support/fixtures';

/**
 * H-auth-required: project terautentikasi membuktikan `/dashboard` merender shell admin.
 * Tidak pernah mengharapkan `/login`; kegagalan sesi terlihat sebagai shell yang tidak muncul.
 */
test('H-auth-required: dashboard menampilkan authenticated shell', async ({ page }): Promise<void> => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    expect(new URL(page.url()).pathname).toBe('/dashboard');
    await expect(page.getByTestId('authenticated-shell')).toBeVisible();
});

/** Route root auth wajib langsung meninggalkan halaman auth untuk sesi aktif. */
test('H-auth-required: user aktif tidak dapat membuka root auth', async ({ page }): Promise<void> => {
    await page.goto('/auth', { waitUntil: 'domcontentloaded' });

    expect(new URL(page.url()).pathname).toBe('/');
});
