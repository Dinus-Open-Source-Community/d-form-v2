import type { Locator, Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { countFixtureRows, expect, test } from './support/fixtures';
import { startRequestLog, stubStoreResponse } from './support/network';
import { parseTinkerJsonRecord, requireTinkerString } from './support/tinker';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 2 });

const AUTOSAVE_SAVED_TEXT = 'Tersimpan';
const FIELDS_REQUEST_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+\/fields$/;
/** Kanal header: PUT settings form (judul/deskripsi). */
const HEADER_PUT_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+$/;
/**
 * Toast kegagalan: HEAD memakai teks hardcoded, working tree bisa memakai pesan
 * body 500 mentah atau versi humanized (`Field tidak dapat disimpan.`), plus aksi
 * `Coba lagi`. Regex menutup ketiganya tanpa mengikat satu versi copy.
 */
const AUTOSAVE_FAILURE_TOAST = /Gagal menyimpan|Fields cannot be saved|Field tidak dapat disimpan|Coba lagi/;

/**
 * Buka builder `edit` (redirect ke `show`) dan tunggu kanvas siap.
 * Fixture membuat `closed_at = NULL`, sedangkan autosave PUT mewajibkannya;
 * isi tanggal tutup lebih dulu agar PUT tidak 422 dan status `Tersimpan` muncul.
 */
async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'updated_at' => now()]);`,
    ]);

    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });

    // SSR: `#f-title` sudah ada di HTML sebelum hidrasi; tunggu Vue benar-benar mount
    // agar input/event handler aktif dan nilai server tidak menimpa isian test.
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

/** Baca label satu field langsung dari DB untuk membuktikan save gagal tidak menulis. */
function readFieldLabel(fieldId: string): string {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(['label' => Illuminate\\Support\\Facades\\DB::table('form_fields')->where('id', '${fieldId}')->value('label')]);`,
    ]);

    return requireTinkerString(parseTinkerJsonRecord(raw, 'field label'), 'label', 'field label');
}

/** Input label field di kanvas; field baru non-required memakai placeholder "(opsional)". */
function fieldLabelInputs(page: Page): Locator {
    return page.locator('input[placeholder^="Label pertanyaan"]');
}

/** Area drop di kanvas: section yang memuat input judul. */
function canvasForTitle(page: Page): Locator {
    return page
        .locator('section')
        .filter({ has: page.locator('#f-title') })
        .first();
}

/** Tambah satu field Short Text lewat drag dari palette. */
async function addShortTextField(page: Page): Promise<void> {
    await page.getByRole('button', { name: /text inputs/i }).click();
    await page.getByText('Short Text', { exact: true }).first().dragTo(canvasForTitle(page));
}

/** Tunggu autosave selesai: status `Tersimpan` baru muncul setelah POST fields + PUT form sukses. */
async function waitForSaved(page: Page, timeout: number = 60_000): Promise<void> {
    await expect(page.getByText(AUTOSAVE_SAVED_TEXT, { exact: true })).toBeVisible({ timeout });
}

/** Reload penuh; retry menyerap ERR_ABORTED transien saat navigasi bertabrakan dengan request in-flight. */
async function reloadBuilder(page: Page): Promise<void> {
    await expect(async (): Promise<void> => {
        await page.reload({ waitUntil: 'domcontentloaded' });
    }).toPass({ timeout: 20_000 });
}

test.describe('builder autosave', () => {
    test('happy: judul header tersimpan dan persist setelah reload', async ({ page, fixtureEvent, fixtureForm }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const newTitle = `Autosave header ${fixtureEvent.ownerKey}`;

        await page.locator('#f-title').fill(newTitle);
        await waitForSaved(page);
        await reloadBuilder(page);

        await expect(page.locator('#f-title')).toHaveValue(newTitle);
    });

    test('happy: field baru tersimpan, jumlah DB bertambah, dan persist setelah reload', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const beforeCount = countFixtureRows(fixtureEvent.marker).counts.form_fields;

        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const labelInputs = fieldLabelInputs(page);
        await expect(labelInputs).toHaveCount(2);

        await addShortTextField(page);
        await expect(labelInputs).toHaveCount(3);
        await expect(labelInputs.last()).toHaveValue('Short Text');

        // Server single-threaded: beri waktu simpan yang lebih panjang sebelum menilai DB.
        await waitForSaved(page);
        await expect
            .poll((): number => countFixtureRows(fixtureEvent.marker).counts.form_fields, { timeout: 30_000 })
            .toBe(beforeCount + 1);

        await reloadBuilder(page);
        await expect(fieldLabelInputs(page)).toHaveCount(3);
        await expect(fieldLabelInputs(page).last()).toHaveValue('Short Text');
    });

    test('failure: snapshot 500 menampilkan toast, kanvas tetap, DB tidak berubah', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await stubStoreResponse(page, FIELDS_REQUEST_PATH, {
            status: 500,
            contentType: 'application/json',
            body: JSON.stringify({ ok: false, message: 'Fields cannot be saved' }),
        });

        const beforeCount = countFixtureRows(fixtureEvent.marker).counts.form_fields;

        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const firstLabel = fieldLabelInputs(page).first();
        const originalLabel = await firstLabel.inputValue();
        const editedLabel = `Renamed by e2e ${fixtureEvent.ownerKey}`;

        await firstLabel.fill(editedLabel);

        // Toast gagal tidak boleh bergantung pada teks status AutosaveStatus (hanya ada di working tree).
        await expect(page.locator('[data-sonner-toast]').filter({ hasText: AUTOSAVE_FAILURE_TOAST })).toBeVisible();

        // UI optimistik tetap mempertahankan nilai baru walau save gagal.
        await expect(firstLabel).toHaveValue(editedLabel);

        const fieldId = fixtureForm.primary.fieldIds[0];

        if (fieldId === undefined) {
            throw new Error('fixture field pertama tidak tersedia.');
        }

        // Bukti DB: rename gagal tidak boleh menulis label baru.
        expect(readFieldLabel(fieldId), 'label DB tidak berubah').toBe(originalLabel);
        expect(countFixtureRows(fixtureEvent.marker).counts.form_fields, 'rename tidak menambah field').toBe(
            beforeCount
        );

        await reloadBuilder(page);
        await expect(fieldLabelInputs(page).first()).toHaveValue(originalLabel);
    });

    test('anomali: dua edit judul dalam debounce hanya mengirim satu snapshot header', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const requestLog = startRequestLog(page);
        const firstTitle = `Debounce A ${fixtureEvent.ownerKey}`;
        const secondTitle = `Debounce B ${fixtureEvent.ownerKey}`;
        const edits: readonly [string, string] = [firstTitle, secondTitle];

        try {
            // Dua isian 150 ms terpisah: masih di dalam debounce 800 ms, dan `fill`
            // memakai event yang sama dengan interaksi user (lebih stabil dari dispatch manual).
            await page.locator('#f-title').fill(edits[0]);
            await page.waitForTimeout(150);
            await page.locator('#f-title').fill(edits[1]);

            await expect(page.locator('#f-title')).toHaveValue(secondTitle);
            await waitForSaved(page);
            // Tunggu > debounce 800 ms: snapshot kedua yang ter-jadwal ulang akan
            // terkirim dalam jendela ini dan menggagalkan assertion jumlah request.
            await page.waitForTimeout(1_600);

            // Judul = kanal header, jadi yang dihitung adalah satu PUT settings.
            expect(requestLog.requestCount('PUT', HEADER_PUT_PATH)).toBe(1);
            expect(requestLog.requestCount('POST', FIELDS_REQUEST_PATH)).toBe(0);
        } finally {
            requestLog.stop();
        }
    });
});
