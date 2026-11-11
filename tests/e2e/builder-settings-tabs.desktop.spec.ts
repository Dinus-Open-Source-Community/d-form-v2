import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1 });

/** Nama accordion palette lama; setelah varian inline dihapus, tombol ini tidak boleh ada. */
const PALETTE_SETTINGS_NAME = /^pengaturan form$/i;

/** Tab pengaturan form: satu-satunya surface settings builder. */
function settingsTab(page: Page): Locator {
    return page.getByRole('tab', { name: /pengaturan form/i });
}

/** Tombol simpan header tab halaman create (satu-satunya aksi simpan di desktop). */
function createSaveButton(page: Page): Locator {
    return page.getByRole('button', { name: 'Save Form' });
}

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

async function openCreatePage(page: Page, eventId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible();
    await waitForHydration(page);
}

async function openWizardFormStep(page: Page, eventId: string): Promise<void> {
    await page.goto(`/admin/events/create?step=forms&draftId=${eventId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

test.describe('pengaturan form sebagai tab', () => {
    test('halaman create: tab Editor + Pengaturan Form, palette tanpa accordion', async ({ page, fixtureEvent }) => {
        await openCreatePage(page, fixtureEvent.eventId);

        await expect(page.getByRole('tab', { name: 'Editor' })).toHaveAttribute('aria-selected', 'true');
        await expect(settingsTab(page)).toBeVisible();

        // Verifikasi negatif: accordion "Pengaturan form" lama tidak lagi dirender di palette.
        await expect(page.getByRole('button', { name: PALETTE_SETTINGS_NAME })).toHaveCount(0);

        // Satu surface settings: panel baru tampil setelah tab aktif.
        await expect(page.locator('#create-closed-date')).toBeHidden();
        await settingsTab(page).click();
        await expect(page.locator('#create-closed-date')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Public', exact: true })).toBeVisible();

        // Aksi utama tetap terjangkau dari tab pengaturan.
        await expect(createSaveButton(page)).toBeEnabled();
    });

    test('step formulir wizard: pengaturan juga tab dan navigasi wizard utuh', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        // `fixtureForm` wajib diminta: step forms hanya hidup saat draft event punya form.
        expect(fixtureForm.primary.formId).not.toBe('');

        await openWizardFormStep(page, fixtureEvent.eventId);

        await expect(settingsTab(page)).toBeVisible();
        await expect(page.getByRole('button', { name: PALETTE_SETTINGS_NAME })).toHaveCount(0);
        await expect(page.locator('#wizard-closed-date')).toBeHidden();

        await settingsTab(page).click();
        await expect(page.locator('#wizard-closed-date')).toBeVisible();

        // Navigasi wizard tidak berubah: keluar, lewati, pratinjau, dan selesai tetap tersedia.
        await expect(page.getByRole('button', { name: 'Kembali ke event' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Lewati' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Pratinjau formulir' }).filter({ visible: true })).toHaveCount(1);
        await expect(page.getByRole('button', { name: 'Selesai' })).toBeVisible();
    });
});
