import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1 });

/** Strip breadcrumb topbar dirender sebagai elemen dengan `aria-label="Breadcrumb"`. */
const BREADCRUMB_STRIP = '[aria-label="Breadcrumb"]';

/** Segment `Formulir` statis (halaman daftar form sudah dihapus) — harus span, bukan anchor. */
const STATIC_FORMS_CRUMB = `${BREADCRUMB_STRIP} [data-breadcrumb-static="true"]`;

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

test.describe('breadcrumb halaman builder', () => {
    test('halaman edit form: breadcrumb tampil, Formulir statis tanpa anchor', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
        await waitForHydration(page);

        await expect(page.locator(BREADCRUMB_STRIP)).toBeVisible();
        await expect(page.locator(BREADCRUMB_STRIP)).toContainText('Formulir');

        // Segmen Formulir = teks statis, bukan link.
        await expect(page.locator(STATIC_FORMS_CRUMB)).toHaveCount(1);
        await expect(page.locator(`${BREADCRUMB_STRIP} a`).filter({ hasText: 'Formulir' })).toHaveCount(0);
    });

    test('halaman create form: breadcrumb tampil, Formulir statis tanpa anchor', async ({ page, fixtureEvent }) => {
        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('#f-title')).toBeVisible();
        await waitForHydration(page);

        await expect(page.locator(BREADCRUMB_STRIP)).toBeVisible();
        await expect(page.locator(STATIC_FORMS_CRUMB)).toHaveCount(1);
        await expect(page.locator(`${BREADCRUMB_STRIP} a`).filter({ hasText: 'Formulir' })).toHaveCount(0);

        // Crumb lain tetap link (mis. "Acara").
        await expect(page.locator(`${BREADCRUMB_STRIP} a`).filter({ hasText: 'Acara' })).toHaveCount(1);
    });

    test('halaman tanpa formulir tetap memakai breadcrumb berisi link', async ({ page }) => {
        await page.goto('/admin/events', { waitUntil: 'domcontentloaded' });

        await expect(page.getByRole('heading', { level: 1, name: 'Acara' })).toBeVisible();
        await expect(page.locator(BREADCRUMB_STRIP)).toHaveCount(1);
        await expect(page.locator(STATIC_FORMS_CRUMB)).toHaveCount(0);
    });
});
