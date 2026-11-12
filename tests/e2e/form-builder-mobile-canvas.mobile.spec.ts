import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1, hasTouch: true });

const ADD_SECTION_LABEL = /tambah bagian setelah ini/i;

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

async function openEditCanvas(page: Page, eventId: string, formId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/${formId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

test.describe('kanvas builder mobile', () => {
    test('Maks. karakter memakai keypad numerik, berhenti di batas tipe, dan drawer tidak fokus input', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openEditCanvas(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        // Buka editor field (short text dari fixture).
        await page.getByRole('button', { name: 'Pengaturan field' }).first().tap();

        const sheet = page.getByRole('dialog');

        await expect(sheet).toBeVisible();

        // Drawer hanya terbuka: fokus tidak diarahkan ke input mana pun.
        const focusedInSheet = await page.evaluate((): string => {
            const active = document.activeElement;

            if (!(active instanceof HTMLElement)) return '';

            return active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' ? 'INPUT' : active.tagName;
        });

        expect(focusedInSheet).not.toBe('INPUT');

        const maxLength = sheet.getByPlaceholder('Tanpa batas');

        await expect(maxLength).toBeVisible();
        await expect(maxLength).toHaveAttribute('inputmode', 'numeric');
        await expect(maxLength).toHaveAttribute('type', 'text');
        await expect(maxLength).toHaveAttribute('pattern', '[0-9]*');
        await expect(maxLength).toHaveAttribute('autocomplete', 'off');
        await expect(sheet.locator('input[type="number"]')).toHaveCount(0);

        // Teks bantuan memuat batas tipe (short text = 1..1000).
        await expect(
            sheet.getByText('Minimal 1, maksimal 1.000 karakter. Kosongkan jika tidak dibatasi.')
        ).toBeVisible();

        // Mengetik melebihi batas berhenti di 1000 (bukan melompat ke 100000).
        await maxLength.fill('1234567');
        await expect(maxLength).toHaveValue('1000');

        await maxLength.fill('12a3');
        await expect(maxLength).toHaveValue('123');

        // Kosong = tanpa batas.
        await maxLength.fill('');
        await expect(maxLength).toHaveValue('');

        // Tutup drawer lebih dulu agar FAB `+` bisa di-tap.
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toHaveCount(0);

        // Long text memakai batas 10000.
        await page.getByRole('button', { name: 'Add field' }).tap();
        await page
            .getByRole('dialog')
            .getByRole('button', { name: /Long Text/ })
            .tap();
        await page.getByRole('button', { name: 'Pengaturan field' }).last().tap();

        const longSheet = page.getByRole('dialog');

        await expect(
            longSheet.getByText('Minimal 1, maksimal 10.000 karakter. Kosongkan jika tidak dibatasi.')
        ).toBeVisible();

        const longMaxLength = longSheet.getByPlaceholder('Tanpa batas');

        await longMaxLength.fill('123456');
        await expect(longMaxLength).toHaveValue('10000');
    });

    test('tombol tambah bagian bisa di-tap di area FAB setelah kanvas digulir penuh', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openEditCanvas(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const canvas = page.locator('[data-testid="form-builder-canvas-scroll"]');

        await canvas.evaluate((element: Element): void => {
            element.scrollTo({ top: element.scrollHeight });
        });
        await page.waitForTimeout(300);

        const addSection = page.getByRole('button', { name: ADD_SECTION_LABEL });

        await expect(addSection).toBeVisible();

        const box = await addSection.boundingBox();

        if (box === null) throw new Error('tombol tambah bagian tidak punya bounding box');

        // Tiga titik: kiri, tengah, kanan (area FAB) — tidak boleh tertutup elemen lain.
        const points = [box.x + 12, box.x + box.width / 2, box.x + box.width - 12];

        for (const x of points) {
            const hit = await page.evaluate(
                (point: { x: number; y: number }): string => {
                    const element = document.elementFromPoint(point.x, point.y);

                    return element?.closest('button')?.getAttribute('aria-label') ?? element?.tagName ?? '';
                },
                { x, y: box.y + box.height / 2 }
            );

            expect(hit, `titik x=${Math.round(x)} harus mengenai tombol tambah bagian`).toBe(
                'Tambah bagian setelah ini'
            );
        }

        // Tap di tepi kanan (area FAB) harus tetap menambah bagian.
        const sectionsBefore = await page.getByRole('navigation', { name: 'Navigasi bagian form' }).count();

        await page.touchscreen.tap(box.x + box.width - 12, box.y + box.height / 2);

        await expect(page.getByText('Bagian 2', { exact: true })).toBeVisible();
        expect(sectionsBefore).toBeLessThanOrEqual(1);
    });
});
