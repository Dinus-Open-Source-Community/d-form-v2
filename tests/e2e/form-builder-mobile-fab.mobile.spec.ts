import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1 });

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

test.describe('FAB kanvas mobile', () => {
    test('bulat, mengambang di atas bar aksi, dan tidak memfokuskan input pencarian', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
        await waitForHydration(page);

        const fab = page.getByRole('button', { name: 'Add field' });

        await expect(fab).toBeVisible();

        // Bulat penuh: radius efektif = setengah lebar (size-14 = 56px).
        const geometry = await fab.evaluate(
            (element): { radius: number; width: number; height: number; top: number; bottom: number } => {
                const style = getComputedStyle(element);
                const box = element.getBoundingClientRect();
                const radius = Math.min(Number.parseFloat(style.borderTopLeftRadius) || 0, box.width / 2);

                return { radius, width: box.width, height: box.height, top: box.top, bottom: box.bottom };
            }
        );

        expect(geometry.width).toBeGreaterThanOrEqual(44);
        expect(geometry.height).toBeGreaterThanOrEqual(44);
        expect(geometry.radius).toBeGreaterThanOrEqual(geometry.width / 2 - 1);

        // Tidak menempel tepi bawah dan tidak menabrak bar aksi `Pratinjau`/`Simpan`.
        const viewport = page.viewportSize();
        const actionBarTop = (await page.getByRole('button', { name: 'Simpan', exact: true }).boundingBox())?.y ?? 0;

        expect(viewport === null ? 0 : geometry.bottom).toBeLessThanOrEqual((viewport?.height ?? 0) - 8);
        expect(geometry.bottom).toBeLessThanOrEqual(actionBarTop - 4);

        // Tap → sheet terbuka tanpa memfokuskan input pencarian (keyboard tidak muncul).
        await fab.click();

        const sheet = page.getByRole('dialog');

        await expect(sheet).toBeVisible();

        const focusedInSheet = await page.evaluate((): string => {
            const active = document.activeElement;

            if (!(active instanceof HTMLElement)) return '';

            return active.getAttribute('placeholder') ?? active.tagName;
        });

        expect(focusedInSheet).not.toBe('Search components...');

        // Pencarian tetap berfungsi saat diketuk, dan field terpilih masuk ke kanvas.
        const search = page.getByPlaceholder('Search components...');

        await search.fill('short');

        const shortText = sheet.getByRole('button', { name: /Short Text/ });

        await expect(shortText).toBeVisible();
        await shortText.click();
        await expect(sheet).toHaveCount(0);
        await expect(page.locator('.fb-field-row')).toHaveCount(2);
    });
});
