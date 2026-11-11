import { mkdir } from 'node:fs/promises';
import type { Locator, Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 3, hasTouch: false });

const SHOT_DIR = '/tmp/opencode/builder-drag-fix/screens';
const MARKER = '[data-drag-indicator]';
const CHROME = '[data-drag-chrome]';
const GAP = '[data-drop-gap]';
const GRIP = '[title^="Seret untuk memindahkan urutan"]';
const LABEL_INPUT = 'input[placeholder^="Label pertanyaan"]';

/** Buka builder edit dan tunggu Vue benar-benar ter-hidrasi agar handler drag aktif. */
async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'updated_at' => now()]);`,
    ]);

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

/** Mulai drag native dari titik tengah elemen tanpa melepas tombol mouse. */
async function pressOn(page: Page, source: Locator): Promise<void> {
    const box = await source.boundingBox();

    if (box === null) throw new Error('elemen sumber drag tidak punya bounding box');

    const x = box.x + box.width / 2;
    const y = box.y + box.height / 2;

    await page.mouse.move(x, y);
    await page.mouse.down();
    // Nudge kecil memastikan Chromium memulai drag dari elemen sumber, bukan dari tujuan.
    await page.mouse.move(x + 8, y + 8, { steps: 2 });
    await page.waitForTimeout(80);
}

/** Geser pointer langsung ke titik tengah elemen tujuan (tanpa melewati gap lain). */
async function moveOnto(page: Page, target: Locator): Promise<void> {
    const box = await target.boundingBox();

    if (box === null) throw new Error('elemen tujuan drag tidak punya bounding box');

    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 1 });
    await page.waitForTimeout(150);
}

/** Kumpulkan error konsol dan exception halaman untuk assertion kebersihan run. */
function collectErrors(page: Page): string[] {
    const errors: string[] = [];

    page.on('pageerror', (error: Error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error' && !message.text().startsWith('Failed to load resource')) {
            errors.push(`console: ${message.text()}`);
        }
    });

    return errors;
}

async function openTextInputs(page: Page): Promise<Locator> {
    await page.getByRole('button', { name: /text inputs/i }).click();

    return page.getByText('Short Text', { exact: true }).first();
}

test.describe('builder drag state', () => {
    test('palette ke gap: marker muncul, drop menyisipkan field, chrome hilang', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const errors = collectErrors(page);

        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const source = await openTextInputs(page);
        const gaps = page.locator(GAP);

        await expect(gaps).toHaveCount(4);

        await pressOn(page, source);
        await moveOnto(page, gaps.nth(1));

        await expect(page.locator(MARKER)).toBeVisible();
        await expect(page.locator(CHROME)).toHaveCount(1);

        await page.mouse.up();

        await expect(page.locator(MARKER)).toHaveCount(0);
        await expect(page.locator(CHROME)).toHaveCount(0);

        const labels = page.locator(LABEL_INPUT);

        await expect(labels).toHaveCount(4);
        await expect(labels.nth(1)).toHaveValue('Short Text');
        expect(errors, errors.join('\n')).toEqual([]);
    });

    test('reorder: drop mengubah urutan dan marker hilang', async ({ page, fixtureEvent, fixtureForm }) => {
        const errors = collectErrors(page);

        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const grips = page.locator(GRIP);

        await expect(grips).toHaveCount(3);
        await expect(page.locator(LABEL_INPUT)).toHaveCount(3);

        // Field ketiga digulir ke area pandang dulu agar sumber dan gap tujuan sama-sama terlihat.
        await grips.nth(2).scrollIntoViewIfNeeded();
        await page.waitForTimeout(200);

        await pressOn(page, grips.nth(2));
        await moveOnto(page, page.locator(GAP).nth(1));

        await expect(page.locator(MARKER)).toBeVisible();

        await page.mouse.up();

        await expect(page.locator(MARKER)).toHaveCount(0);
        await expect(page.locator(CHROME)).toHaveCount(0);

        const labels = page.locator(LABEL_INPUT);

        await expect(labels.nth(0)).toHaveValue(/^Field 1\b/);
        await expect(labels.nth(1)).toHaveValue(/^Field 3\b/);
        await expect(labels.nth(2)).toHaveValue(/^Field 2\b/);
        expect(errors, errors.join('\n')).toEqual([]);
    });

    test('bergerak antar anak kanvas tidak menghapus marker (tanpa flicker)', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const source = await openTextInputs(page);
        const markers = page.locator(MARKER);

        await pressOn(page, source);
        await moveOnto(page, page.locator(GAP).nth(1));
        await expect(markers).toHaveCount(1);

        // Lewati kartu field (anak kanvas): dragleave internal tidak boleh mereset marker.
        await moveOnto(page, page.locator(LABEL_INPUT).nth(0));
        await expect(markers).toHaveCount(1);

        await moveOnto(page, page.locator(GAP).nth(0));
        await expect(markers).toHaveCount(1);
        await expect(page.locator('[data-drag-indicator="gap"]')).toHaveCount(1);

        await page.mouse.up();
        await expect(markers).toHaveCount(0);
    });

    test('palette dilepas di luar kanvas: marker dan ring tidak tersangkut', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const errors = collectErrors(page);

        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const source = await openTextInputs(page);

        await pressOn(page, source);
        await moveOnto(page, page.locator(GAP).nth(1));
        await expect(page.locator(MARKER)).toBeVisible();

        await page.mouse.move(60, 420, { steps: 15 });
        await page.waitForTimeout(150);
        await page.mouse.up();

        await expect(page.locator(MARKER)).toHaveCount(0);
        await expect(page.locator(CHROME)).toHaveCount(0);
        await expect(page.locator(LABEL_INPUT)).toHaveCount(3);
        expect(errors, errors.join('\n')).toEqual([]);
    });

    test('Escape saat drag menghentikan sesi dan membersihkan marker', async ({ page, fixtureEvent, fixtureForm }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const source = await openTextInputs(page);

        await pressOn(page, source);
        await moveOnto(page, page.locator(GAP).nth(1));
        await expect(page.locator(MARKER)).toBeVisible();

        await page.keyboard.press('Escape');
        await page.waitForTimeout(150);
        await page.mouse.up();

        await expect(page.locator(MARKER)).toHaveCount(0);
        await expect(page.locator(CHROME)).toHaveCount(0);
        await expect(page.locator(LABEL_INPUT)).toHaveCount(3);
    });

    test('drop gagal tanpa crypto.randomUUID tetap membersihkan marker', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        // Simulasi akses lewat http://<LAN-IP>: secure context tidak tersedia.
        await page.addInitScript(() => {
            Reflect.deleteProperty(Crypto.prototype, 'randomUUID');
        });

        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const errors = collectErrors(page);
        const source = await openTextInputs(page);

        await pressOn(page, source);
        await moveOnto(page, page.locator(GAP).nth(1));
        await expect(page.locator(MARKER)).toBeVisible();

        await page.mouse.up();

        // Marker wajib hilang dan field baru tetap tersisip tanpa randomUUID.
        await expect(page.locator(MARKER)).toHaveCount(0);
        await expect(page.locator(CHROME)).toHaveCount(0);
        await expect(page.locator(LABEL_INPUT)).toHaveCount(4);

        await mkdir(SHOT_DIR, { recursive: true });
        await page.screenshot({ path: `${SHOT_DIR}/after-no-uuid.png` });

        // Lane `createUuid` sudah mengganti seluruh pemanggilan `crypto.randomUUID`, jadi
        // drop harus sukses tanpa error konsol sekalipun secure context tidak tersedia.
        expect(errors, errors.join('\n')).toEqual([]);
    });
});
