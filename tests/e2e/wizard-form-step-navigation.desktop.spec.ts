import type { Locator, Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { expect, test } from './support/fixtures';
import { startRequestLog } from './support/network';
import { parseTinkerJsonRecord, requireTinkerString } from './support/tinker';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 2 });

const FIELDS_REQUEST_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+\/fields$/;

/** Input label pada kartu field kanvas builder. */
function fieldLabelInputs(page: Page): Locator {
    return page.locator('input[placeholder^="Label pertanyaan"]');
}

/** Buka step formulir wizard Create langsung via query draft; tunggu kanvas terhidrasi. */
async function openWizardFormStep(page: Page, eventId: string): Promise<void> {
    await page.goto(`/admin/events/create?step=forms&draftId=${eventId}`, { waitUntil: 'domcontentloaded' });
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

/** Label satu field dari DB: bukti edit terakhir tersimpan sebelum halaman ditinggalkan. */
function readFieldLabel(fieldId: string): string {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(['label' => Illuminate\\Support\\Facades\\DB::table('form_fields')->where('id', '${fieldId}')->value('label')]);`,
    ]);

    return requireTinkerString(parseTinkerJsonRecord(raw, 'field label'), 'label', 'field label');
}

test.describe('wizard Create: navigasi keluar step form', () => {
    test('tombol Kembali ke event menunggu snapshot autosave selesai', async ({ page, fixtureEvent, fixtureForm }) => {
        // `fixtureForm` wajib diminta: hanya fixture inilah yang membuat form + field di event.
        expect(fixtureForm.primary.formId).not.toBe('');
        let saveStarted = false;
        let saveCalls = 0;
        let saveBody = '';

        // Tahan response fields agar terlihat apakah navigasi benar-benar menunggu flush.
        await page.route(FIELDS_REQUEST_PATH, async (route): Promise<void> => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }

            saveStarted = true;
            saveCalls += 1;
            saveBody = route.request().postData() ?? '';

            await new Promise<void>((done: () => void): void => {
                setTimeout(done, 600);
            });
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ ok: true }),
            });
        });

        await openWizardFormStep(page, fixtureEvent.eventId);

        const labelInput = fieldLabelInputs(page).first();
        const newLabel = `Navigasi wizard ${fixtureEvent.ownerKey}`;

        await labelInput.fill(newLabel);

        const clickStartedAt = Date.now();

        await page.getByRole('button', { name: 'Kembali ke event' }).click();

        // Selama flush berjalan, URL wajib masih di step form (bukan sudah pindah).
        await page.waitForTimeout(250);
        expect(new URL(page.url()).searchParams.get('step')).toBe('forms');
        expect(saveStarted, 'flush autosave harus dipicu oleh navigasi').toBe(true);
        expect(saveBody).toContain(newLabel);

        await page.waitForURL(/[?&]step=event/, { timeout: 20_000 });
        expect(Date.now() - clickStartedAt, 'navigasi harus menunggu response save').toBeGreaterThanOrEqual(500);

        // Unmount setelah navigasi tidak boleh memicu putaran save kedua untuk snapshot sama.
        await page.waitForTimeout(1_000);
        expect(saveCalls, 'tepat satu putaran save per navigasi').toBe(1);
    });

    test('tombol Lewati menunggu flush dan menyimpan edit field terakhir', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const fieldId = fixtureForm.primary.fieldIds[0];

        if (fieldId === undefined) {
            throw new Error('fixture field pertama tidak tersedia.');
        }

        // Perlambat request (bukan stub) agar terlihat navigasi menunggu flush sebelum pindah.
        await page.route(FIELDS_REQUEST_PATH, async (route): Promise<void> => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }

            await new Promise<void>((done: () => void): void => {
                setTimeout(done, 400);
            });
            await route.continue();
        });

        await openWizardFormStep(page, fixtureEvent.eventId);

        const requestLog = startRequestLog(page);
        const editedLabel = `Lewati wizard ${fixtureEvent.ownerKey}`;

        try {
            await fieldLabelInputs(page).first().fill(editedLabel);
            await page.getByRole('button', { name: 'Lewati' }).click();

            // Masih di wizard selagi save berjalan: navigasi tidak boleh mendahului flush.
            await page.waitForTimeout(150);
            expect(new URL(page.url()).pathname).toBe('/admin/events/create');

            await page.waitForURL(
                (): boolean => new URL(page.url()).pathname === `/admin/events/${fixtureEvent.eventId}`,
                { timeout: 20_000 }
            );

            await expect.poll((): string => readFieldLabel(fieldId), { timeout: 30_000 }).toBe(editedLabel);

            await page.waitForTimeout(500);
            expect(requestLog.requestCount('POST', FIELDS_REQUEST_PATH), 'tepat satu putaran save').toBe(1);
        } finally {
            requestLog.stop();
        }
    });
});
