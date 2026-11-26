import type { Locator, Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { claimEventForAdmin } from './support/eventAdmin';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 3, hasTouch: false });

const REFERRAL_BLOCK = '[data-testid="form-builder-referral-block"]';
const FIELD_CARD = '[data-testid="field-card"]';

/** Form siap disimpan: tanggal tutup terisi dan referral aktif agar blok tampil. */
function prepareForm(formId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'referral_enabled' => true, 'updated_at' => now()]);`,
    ]);
}

async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    prepareForm(formId);

    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
    await page.getByTestId('form-builder-canvas').scrollIntoViewIfNeeded();
}

/** Urutan vertikal blok terhadap field: `true` bila blok berada setelah field itu. */
async function blockIsAfter(block: Locator, field: Locator): Promise<boolean> {
    const blockBox = await block.boundingBox();
    const fieldBox = await field.boundingBox();

    if (blockBox === null || fieldBox === null) throw new Error('elemen tidak punya bounding box');

    return blockBox.y > fieldBox.y;
}

test.describe('Builder: posisi blok kode referral', () => {
    test('blok tampil di akhir, bisa digeser, dan posisinya tersimpan', async ({ page, fixtureEvent, fixtureForm }) => {
        const formId = fixtureForm.primary.formId;

        claimEventForAdmin(fixtureEvent.eventId);
        await openBuilder(page, fixtureEvent.eventId, formId);

        const block = page.locator(REFERRAL_BLOCK);
        const fields = page.locator(FIELD_CARD);

        await expect(block).toBeVisible();
        await expect(block).toContainText('Kode referral');
        await expect(block).toContainText('Blok otomatis dari fitur referral');
        expect(await fields.count()).toBe(3);

        // Default `end`: blok berada setelah field terakhir.
        expect(await blockIsAfter(block, fields.nth(2))).toBe(true);

        const saved = page.waitForResponse(
            (response): boolean =>
                response.request().method() === 'PUT' &&
                (response.request().postData() ?? '').includes('referral_position')
        );

        await page.getByRole('button', { name: 'Naikkan blok kode referral' }).click();
        expect((await saved).status()).toBe(200);

        // Satu langkah naik: blok sekarang di antara field kedua dan ketiga.
        expect(await blockIsAfter(block, fields.nth(1))).toBe(true);
        expect(await blockIsAfter(block, fields.nth(2))).toBe(false);

        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.getByTestId('form-builder-canvas').scrollIntoViewIfNeeded();

        const reloadedBlock = page.locator(REFERRAL_BLOCK);
        const reloadedFields = page.locator(FIELD_CARD);

        await expect(reloadedBlock).toBeVisible();
        expect(await blockIsAfter(reloadedBlock, reloadedFields.nth(1))).toBe(true);
        expect(await blockIsAfter(reloadedBlock, reloadedFields.nth(2))).toBe(false);
    });

    test('blok tidak tampil saat referral nonaktif', async ({ page, fixtureEvent, fixtureForm }) => {
        const formId = fixtureForm.primary.formId;

        claimEventForAdmin(fixtureEvent.eventId);
        prepareForm(formId);
        runArtisanCommand([
            'tinker',
            '--execute',
            `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['referral_enabled' => false, 'updated_at' => now()]);`,
        ]);

        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${formId}/edit`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });

        await expect(page.locator(REFERRAL_BLOCK)).toHaveCount(0);
    });
});
