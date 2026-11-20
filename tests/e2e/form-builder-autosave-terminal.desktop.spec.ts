import type { Page, TestInfo } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { cleanupOwnerFixture, createOwnerFixture, expect, ownerIdentityForTest, test } from './support/fixtures';
import { readEnvironment } from './support/environment';
import { isTinkerRecord, requireTinkerString } from './support/tinker';

/** Event milik admin yang login (draf hanya untuk pembuat acara). */
function createAdminOwnedEvent(testInfo: TestInfo): { eventId: string; cleanup: () => void } {
    const identity = ownerIdentityForTest(testInfo);
    const owner = createOwnerFixture(identity, readEnvironment().adminEmail);

    return { eventId: owner.eventId, cleanup: (): void => cleanupOwnerFixture(identity) };
}

/** Id draf milik admin pada satu event. */
function readDraftId(eventId: string): string {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('forms')->where('event_id', '${eventId}')->where('status', 'draft')->value('id'));`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    if (typeof parsed !== 'string' || parsed === '') {
        throw new Error(`draft id tidak ditemukan: ${raw.slice(0, 200)}`);
    }

    return parsed;
}

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

test.describe('autosave terminal (target hilang)', () => {
    test('404 saat tambah bagian: toast Indonesia + Muat ulang, tidak ada request lanjutan', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await page.goto(`/admin/events/${owned.eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toBeVisible();
            await waitForHydration(page);

            const draftId = readDraftId(owned.eventId);
            const settingsPath = new RegExp(`/admin/events/${owned.eventId}/forms/${draftId}$`);
            let settingsRequests = 0;

            await page.route(settingsPath, async (route): Promise<void> => {
                if (route.request().method() !== 'PUT') {
                    await route.continue();
                    return;
                }

                settingsRequests += 1;
                await route.fulfill({
                    status: 404,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        message: 'No query results for model [App\\Models\\Event].',
                        exception: 'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
                    }),
                });
            });

            const bodyBefore = await page.locator('body').innerText();

            expect(bodyBefore).not.toContain('No query results');

            // Kanal header: mengubah judul memicu satu PUT yang dijawab 404 (target hilang).
            await page.locator('#f-title').fill('Judul Setelah Event Hilang');

            // Toast terminal: kalimat Indonesia + aksi "Muat ulang".
            const toast = page.locator('[data-sonner-toast]').first();

            await expect(toast).toContainText('Draf atau acara ini sudah tidak tersedia');
            await expect(toast.getByRole('button', { name: 'Muat ulang' })).toBeVisible();

            // Tidak ada JSON/exception Laravel yang bocor ke DOM.
            const bodyAfter = await page.locator('body').innerText();

            expect(bodyAfter).not.toContain('No query results');
            expect(bodyAfter).not.toContain('NotFoundHttpException');
            expect(bodyAfter).not.toContain('"message"');
            expect(bodyAfter).not.toContain('(and ');

            // State tetap: judul yang diketik tidak hilang walau autosave berhenti.
            await expect(page.locator('#f-title')).toHaveValue('Judul Setelah Event Hilang');
            await expect(page.getByTestId('autosave-status')).toContainText(/Gagal menyimpan/);

            // Kill switch: edit berikutnya (header maupun fields) tidak mengirim request baru.
            await page.locator('#f-title').fill('Judul Setelah Terminal');
            await page.getByRole('button', { name: /tambah bagian setelah ini/i }).click();
            await page.waitForTimeout(1_500);

            expect(settingsRequests, 'tepat satu percobaan setelah target hilang').toBe(1);

            // Pemulihan: aksi Muat ulang benar-benar memuat ulang halaman.
            await toast.getByRole('button', { name: 'Muat ulang' }).click();

            await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
            await expect(page).toHaveURL(new RegExp(`/admin/events/${owned.eventId}/forms/create$`));
        } finally {
            owned.cleanup();
        }
    });
});
