import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { renameFieldViaDrawer } from './support/builderFieldCard';
import { expect, test } from './support/fixtures';
import { startRequestLog } from './support/network';
import { parseTinkerJsonRecord, requireTinkerString } from './support/tinker';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 2 });

const FIELDS_REQUEST_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+\/fields$/;
const HEADER_PUT_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+$/;

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
        const saveBodies: string[] = [];

        // Tahan response fields agar flush yang sedang berjalan terlihat menahan navigasi.
        await page.route(FIELDS_REQUEST_PATH, async (route): Promise<void> => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }

            saveStarted = true;
            saveBodies.push(route.request().postData() ?? '');

            await new Promise<void>((done: () => void): void => {
                setTimeout(done, 1_500);
            });
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ ok: true }),
            });
        });

        await openWizardFormStep(page, fixtureEvent.eventId);

        const newLabel = `Navigasi wizard ${fixtureEvent.ownerKey}`;

        // Edit field lewat drawer; request autosave bisa sudah mulai saat drawer ditutup,
        // jadi tungguannya dipasang lebih dulu agar flush yang in-flight tidak terlewat.
        const flushStarted = page.waitForRequest(
            (request): boolean =>
                request.method() === 'POST' && FIELDS_REQUEST_PATH.test(new URL(request.url()).pathname),
            { timeout: 20_000 }
        );

        await renameFieldViaDrawer(page, 0, newLabel);
        await flushStarted;

        const clickStartedAt = Date.now();

        await page.getByRole('button', { name: 'Kembali ke event' }).click();

        // Selama flush berjalan, URL wajib masih di step form (bukan sudah pindah).
        await page.waitForTimeout(250);
        expect(new URL(page.url()).searchParams.get('step')).toBe('forms');
        expect(saveStarted, 'flush autosave harus berjalan saat navigasi diklik').toBe(true);
        expect(saveBodies.every((body: string): boolean => body.includes(newLabel))).toBe(true);

        await page.waitForURL(/[?&]step=event/, { timeout: 20_000 });
        expect(Date.now() - clickStartedAt, 'navigasi harus menunggu response save').toBeGreaterThanOrEqual(500);

        // Navigasi saat flush in-flight boleh memicu satu putaran tambahan: race ini sudah
        // terdokumentasi di CONTEXT.md 6.4 (request tidak dibatalkan/diantrekan). Yang wajib
        // dijaga: edit terakhir ikut di setiap payload dan tetap tersimpan setelah keluar.
        await page.waitForTimeout(1_000);
        expect(saveBodies.length, 'minimal satu putaran save').toBeGreaterThanOrEqual(1);
    });

    test('tombol Lewati membawa edit field terakhir keluar dari wizard', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const fieldId = fixtureForm.primary.fieldIds[0];

        if (fieldId === undefined) {
            throw new Error('fixture field pertama tidak tersedia.');
        }

        await openWizardFormStep(page, fixtureEvent.eventId);

        const requestLog = startRequestLog(page);
        const editedLabel = `Lewati wizard ${fixtureEvent.ownerKey}`;

        try {
            // Satu-satunya jalur edit field sekarang drawer, jadi edit dibuat sebelum navigasi.
            await renameFieldViaDrawer(page, 0, editedLabel);
            await page.getByRole('button', { name: 'Lewati', exact: true }).click();

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
