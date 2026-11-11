import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1, hasTouch: true });

const MARKER = '[data-drag-indicator]';
const CHROME = '[data-drag-chrome]';
const GRIP = '[title^="Seret untuk memindahkan urutan"]';

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

async function openCanvas(page: Page, eventId: string, formId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/${formId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

test.describe('builder drag state (mobile)', () => {
    test('jalur sentuh memakai tap-to-add dan tidak memunculkan chrome drag', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openCanvas(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        // Grip reorder desktop disembunyikan di mobile; tidak ada drag HTML5 untuk sentuh.
        await expect(page.locator(GRIP).first()).toBeHidden();

        const labels = page.locator('input[placeholder^="Label pertanyaan"]');

        await expect(labels).toHaveCount(1);

        await page.getByRole('button', { name: 'Add field' }).tap();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: /Short Text/ })
            .tap();

        await expect(labels).toHaveCount(2);
        await expect(page.locator(MARKER)).toHaveCount(0);
        await expect(page.locator(CHROME)).toHaveCount(0);

        // Tap field untuk membuka editor tidak boleh meninggalkan marker.
        await page.getByRole('button', { name: 'Edit' }).first().tap();
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator(MARKER)).toHaveCount(0);
        await expect(page.locator(CHROME)).toHaveCount(0);
    });
});
