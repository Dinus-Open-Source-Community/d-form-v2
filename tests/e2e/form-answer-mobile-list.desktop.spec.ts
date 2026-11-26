import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 2, fixtureSubmissionCount: 1, hasTouch: true });

const MOBILE = { width: 375, height: 812 };
const DESKTOP = { width: 1280, height: 900 };

function answersUrl(eventId: string, formId: string): string {
    return `/admin/events/${eventId}/forms/${formId}?tab=jawaban`;
}

async function openAnswers(page: Page, eventId: string, formId: string): Promise<void> {
    await page.goto(answersUrl(eventId, formId), { waitUntil: 'domcontentloaded' });
    await expect(page.getByTestId('form-answer-mobile-list')).toBeAttached({ timeout: 20_000 });
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

test.describe('jawaban: hanya daftar di mobile', () => {
    test('mobile menampilkan daftar baris, bukan tabel, dan membuka detail dari baris', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await page.setViewportSize(MOBILE);
        await openAnswers(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const list = page.getByTestId('form-answer-mobile-list');

        await expect(list).toBeVisible();
        await expect(page.getByRole('listitem', { name: /Lihat detail jawaban nomor/ })).toHaveCount(1);
        await expect(page.locator('main table')).toBeHidden();

        const overflow = await page.evaluate((): number => {
            const root = document.documentElement;

            return root.scrollWidth - root.clientWidth;
        });

        expect(overflow, 'tanpa overflow horizontal di 375px').toBeLessThanOrEqual(1);

        const firstRow = page.getByRole('listitem', { name: /Lihat detail jawaban nomor 1/ });

        await firstRow.click({ position: { x: 180, y: 32 } });
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await firstRow.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');
    });

    test('tombol Export berada di sisi kanan pada mobile', async ({ page, fixtureEvent, fixtureForm }) => {
        await page.setViewportSize(MOBILE);
        await openAnswers(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const exportButton = page.getByRole('button', { name: /export/i }).first();

        await expect(exportButton).toBeVisible();

        const geometry = await exportButton.evaluate((element: HTMLElement): { center: number; viewport: number } => {
            const rect = element.getBoundingClientRect();

            return { center: rect.left + rect.width / 2, viewport: document.documentElement.clientWidth };
        });

        expect(geometry.center, 'tombol export harus di separuh kanan viewport').toBeGreaterThan(geometry.viewport / 2);
    });

    test('desktop tetap memakai tabel dan menyembunyikan daftar', async ({ page, fixtureEvent, fixtureForm }) => {
        await page.setViewportSize(DESKTOP);
        await openAnswers(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        await expect(page.locator('main table')).toBeVisible();
        await expect(page.getByTestId('form-answer-mobile-list')).toBeHidden();
    });
});
