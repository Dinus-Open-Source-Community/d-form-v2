import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 2, hasTouch: true });

const CARD = '[data-testid="field-card"]';

async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

test.describe('kartu field membuka drawer di mobile', () => {
    test('tap kartu membuka drawer dan tombol aksi tetap terlihat tanpa hover', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const duplicate = page.getByRole('button', { name: 'Gandakan field' }).first();
        const remove = page.getByRole('button', { name: 'Hapus field' }).first();

        await expect(duplicate).toBeVisible();
        await expect(remove).toBeVisible();

        const duplicateBox = await duplicate.boundingBox();

        if (duplicateBox === null) throw new Error('tombol gandakan tidak punya bounding box');

        // Target sentuh minimal 44px (R-03) diukur dari kotak tombol yang sebenarnya.
        expect(duplicateBox.width).toBeGreaterThanOrEqual(44);
        expect(duplicateBox.height).toBeGreaterThanOrEqual(44);

        await page
            .locator(CARD)
            .first()
            .tap({ position: { x: 24, y: 28 } });

        const sheet = page.getByRole('dialog');

        await expect(sheet).toBeVisible();
        await expect(sheet.locator('#f-title, input').first()).toBeVisible();

        await sheet.getByRole('button', { name: 'Selesai' }).tap();
        await expect(sheet).toHaveCount(0);
    });
});
