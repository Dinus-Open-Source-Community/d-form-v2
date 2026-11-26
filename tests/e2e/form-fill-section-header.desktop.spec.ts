import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { claimEventForAdmin } from './support/eventAdmin';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1, hasTouch: false });

async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 30_000 }
    );
}

/** Siapkan form bisa diisi tanpa menghapus marker fixture di metadata. */
function openFormSetup(formId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'updated_at' => now()]);`,
    ]);
}

/** Tambah dua bagian bernama kustom, sambil mempertahankan metadata fixture lain. */
function setTwoNamedSections(formId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$meta = json_decode(Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->value('metadata'), true) ?: [];` +
            `$meta['sections'] = [['id' => 'section-1', 'title' => 'Data Peserta'], ['id' => 'section-2', 'title' => 'Berkas Pendukung']];` +
            `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['metadata' => json_encode($meta), 'updated_at' => now()]);`,
    ]);
}

test.describe('Halaman isi: header bagian', () => {
    test('header hanya tampil saat ada lebih dari satu bagian dan memakai nama kustom', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const formId = fixtureForm.primary.formId;

        claimEventForAdmin(fixtureEvent.eventId);
        openFormSetup(formId);

        await page.goto(`/joined/${fixtureEvent.eventId}/forms/${formId}/fill`, {
            waitUntil: 'domcontentloaded',
        });
        await waitForHydration(page);

        // Satu bagian: strip header tidak ditampilkan.
        await expect(page.getByText('Bagian 1', { exact: true })).toHaveCount(0);

        setTwoNamedSections(formId);

        await page.reload({ waitUntil: 'domcontentloaded' });
        await waitForHydration(page);

        await expect(page.getByText('Data Peserta', { exact: true })).toBeVisible();
        await expect(page.getByText('Bagian 1 dari 2', { exact: true })).toBeVisible();
    });
});
