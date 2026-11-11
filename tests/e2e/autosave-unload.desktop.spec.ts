import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { expect, test } from './support/fixtures';
import { startRequestLog } from './support/network';
import { parseTinkerJsonRecord, requireTinkerString } from './support/tinker';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 2 });

const FIELDS_REQUEST_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+\/fields$/;

/**
 * Fixture membuat `closed_at = NULL` sedangkan autosave PUT mewajibkannya, jadi
 * tanggal tutup diisi lebih dulu agar snapshot header tidak ditolak 422.
 */
async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'updated_at' => now()]);`,
    ]);

    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });

    // SSR sudah merender input sebelum hidrasi; tunggu Vue mount agar handler input aktif.
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

/** Judul form langsung dari DB: bukti snapshot terakhir benar-benar tersimpan, bukan hanya di UI. */
function readFormTitle(formId: string): string {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(['title' => Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->value('title')]);`,
    ]);

    return requireTinkerString(parseTinkerJsonRecord(raw, 'form title'), 'title', 'form title');
}

test.describe('autosave saat halaman ditinggalkan', () => {
    test('refresh di dalam debounce tetap menyimpan judul terakhir', async ({ page, fixtureEvent, fixtureForm }) => {
        const formId = fixtureForm.primary.formId;

        await openBuilder(page, fixtureEvent.eventId, formId);

        const newTitle = `Unload guard ${fixtureEvent.ownerKey}`;
        const requestLog = startRequestLog(page);
        const startedAt = Date.now();

        try {
            await page.locator('#f-title').fill(newTitle);

            // Prasyarat bukti: reload dijalankan sebelum debounce 800 ms habis.
            expect(Date.now() - startedAt, 'refresh harus terjadi di dalam jendela debounce').toBeLessThan(800);
            await page.reload({ waitUntil: 'domcontentloaded' });

            // Request keepalive dan GET reload bisa berbalapan di server single-thread;
            // bukti utama adalah nilai di DB, bukan urutan render.
            await expect.poll((): string => readFormTitle(formId), { timeout: 30_000 }).toBe(newTitle);

            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toHaveValue(newTitle);
            await page.waitForTimeout(500);

            // Playwright tidak lagi mengatribusikan request `keepalive` ke `page` setelah
            // dokumen ditinggalkan, jadi hitungan di sini hanya menangkap XHR/axios.
            // Nol POST membuktikan save tidak lewat jalur XHR dan debounce yang menunggu
            // tidak ter-resume; keberhasilan guard dibuktikan nilai DB di atas.
            expect(requestLog.requestCount('POST', FIELDS_REQUEST_PATH), 'tanpa putaran XHR').toBe(0);
        } finally {
            requestLog.stop();
        }
    });

    test('reload form bersih tidak mengirim snapshot tambahan', async ({ page, fixtureEvent, fixtureForm }) => {
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const requestLog = startRequestLog(page);

        try {
            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
            await page.waitForTimeout(1_200);

            expect(requestLog.requestCount('POST', FIELDS_REQUEST_PATH), 'form bersih tidak perlu save').toBe(0);
        } finally {
            requestLog.stop();
        }
    });
});
