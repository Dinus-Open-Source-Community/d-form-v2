import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1 });

/** Endpoint settings form; autosave memakai PUT ke path ini. */
const FORM_SETTINGS_PUT = /\/admin\/events\/[^/]+\/forms\/[^/]+$/;

/** Tunggu Vue benar-benar mount; SSR sudah merender markup sebelum handler aktif. */
async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

/**
 * Buka tab pengaturan halaman edit. `closed_at` wajib terisi (fixture menyisakan NULL,
 * PUT settings akan 422) dan `visible_for` dinormalkan ke `['public']` agar status awal
 * tombol visibility deterministik.
 */
async function openEditSettingsTab(page: Page, eventId: string, formId: string): Promise<void> {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'visible_for' => json_encode(['public']), 'updated_at' => now()]);`,
    ]);

    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
    await page.getByRole('tab', { name: /pengaturan form/i }).click();
}

test.describe('visibility form di halaman edit', () => {
    test('klik opsi menandai pilihan, tersimpan, dan bertahan setelah reload', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const formId = fixtureForm.primary.formId;

        await openEditSettingsTab(page, fixtureEvent.eventId, formId);

        const participant = page.getByRole('button', { name: 'Participant', exact: true });
        await expect(participant).toHaveAttribute('aria-pressed', 'false');

        // Visibilitas bukan bagian kanal autosave header/fields: tidak ada PUT saat klik.
        let readonlyPuts = 0;
        page.on('request', (request): void => {
            if (request.method() === 'PUT' && FORM_SETTINGS_PUT.test(new URL(request.url()).pathname)) {
                readonlyPuts += 1;
            }
        });

        await participant.click();
        await expect(participant).toHaveAttribute('aria-pressed', 'true');

        await page.waitForTimeout(1_200);
        expect(readonlyPuts, 'visibilitas menunggu simpan manual').toBe(0);

        // Simpan manual (Save All) mempersist header + visibilitas.
        const settingsSaved = page.waitForResponse(
            (response): boolean =>
                response.request().method() === 'PUT' && FORM_SETTINGS_PUT.test(new URL(response.url()).pathname),
            { timeout: 30_000 }
        );

        await page.getByRole('button', { name: 'Save All' }).click();
        // Inertia menjawab 303 (redirect) untuk PUT manual; 200 juga sah bila tanpa redirect.
        expect([200, 303]).toContain((await settingsSaved).status());

        // Persistensi dibuktikan dari server, bukan dari state klien. Reload memakai
        // query string `?tab=settings` yang disinkronkan Show, jadi panel bisa langsung tampil.
        await page.reload({ waitUntil: 'domcontentloaded' });
        await waitForHydration(page);

        const settingsTabButton = page.getByRole('tab', { name: /pengaturan form/i });

        if ((await settingsTabButton.getAttribute('aria-selected')) !== 'true') {
            await settingsTabButton.click();
        }

        await expect(page.getByRole('button', { name: 'Participant', exact: true })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        await expect(page.getByRole('button', { name: 'Public', exact: true })).toHaveAttribute('aria-pressed', 'true');
    });
});
