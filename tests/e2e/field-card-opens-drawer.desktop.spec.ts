import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 2, hasTouch: false });

const CARD = '[data-testid="field-card"]';
const CARD_TRIGGER = /^Ubah field/;

/** Buka builder edit dan tunggu Vue ter-hidrasi sebelum berinteraksi dengan handler klik. */
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

test.describe('kartu field membuka drawer', () => {
    test('klik permukaan kartu membuka drawer, Escape menutup, ikon pengaturan hilang', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        // Ikon pengaturan lama sudah tidak ada; kartu sendiri menjadi trigger.
        await expect(page.getByRole('button', { name: 'Pengaturan field', exact: true })).toHaveCount(0);
        await expect(page.locator(CARD)).toHaveCount(2);

        // Label dan teks bantu bukan input lagi: satu jalur edit, yaitu drawer.
        await expect(page.locator('input[placeholder^="Label pertanyaan"]')).toHaveCount(0);
        await expect(page.getByText(/Field 1/).first()).toBeVisible();

        // Klik di area konten kartu (bukan tombol aksi) membuka drawer.
        await page
            .locator(CARD)
            .first()
            .click({ position: { x: 24, y: 28 } });

        const sheet = page.getByRole('dialog');

        await expect(sheet).toBeVisible();
        await expect(sheet.getByText('Pengaturan field', { exact: true })).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(sheet).toHaveCount(0);
    });

    test('kartu bisa diaktifkan dengan keyboard Enter/Space dan punya focus ring', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const trigger = page.getByRole('button', { name: CARD_TRIGGER }).first();

        await trigger.focus();
        await expect(trigger).toBeFocused();

        const focusRing = await trigger.evaluate((element: HTMLElement): string => {
            const style = getComputedStyle(element);

            return `${style.boxShadow} ${style.outlineStyle}`;
        });

        expect(focusRing).not.toBe('none none');

        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');

        await page.keyboard.press(' ');
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');
    });

    test('tombol duplikat dan hapus tidak membuka drawer', async ({ page, fixtureEvent, fixtureForm }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        await page.getByRole('button', { name: 'Gandakan field' }).first().click();

        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(page.locator(CARD)).toHaveCount(3);

        await page.getByRole('button', { name: 'Hapus field' }).first().click();

        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(page.locator(CARD)).toHaveCount(2);
    });

    test('kartu tidak menyebabkan overflow horizontal di seluruh breakpoint', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        for (const width of [320, 360, 390, 414, 640, 768, 1024, 1280, 1536, 1920]) {
            await page.setViewportSize({ width, height: 900 });

            const overflow = await page.evaluate((): number => {
                const root = document.documentElement;

                return root.scrollWidth - root.clientWidth;
            });

            expect(overflow, `overflow horizontal pada ${width}px`).toBeLessThanOrEqual(1);

            const cardVisible = await page.locator(CARD).first().isVisible();

            expect(cardVisible, `kartu tidak terlihat pada ${width}px`).toBe(true);
        }
    });
});
