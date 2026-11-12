import type { Locator, Page, TestInfo } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { cleanupOwnerFixture, createOwnerFixture, expect, ownerIdentityForTest, test } from './support/fixtures';
import { readEnvironment } from './support/environment';

/**
 * Jalur simpan Create form: draf server-side + publish lewat PUT.
 * Draf hanya boleh ditulis pembuat event, jadi event dibuat milik admin yang login.
 */
interface IAdminOwnedEvent {
    eventId: string;
    cleanup: () => void;
}

function createAdminOwnedEvent(testInfo: TestInfo): IAdminOwnedEvent {
    const identity = ownerIdentityForTest(testInfo);
    const owner = createOwnerFixture(identity, readEnvironment().adminEmail);

    return {
        eventId: owner.eventId,
        cleanup: (): void => cleanupOwnerFixture(identity),
    };
}

/** Id draf milik admin pada satu event (dibaca dari DB, bukan dari DOM). */
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

/** Status form pada satu event (publish harus mengubahnya menjadi `published`). */
function readStatuses(eventId: string): string[] {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('forms')->where('event_id', '${eventId}')->pluck('status')->all());`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    if (!Array.isArray(parsed)) {
        throw new Error(`statuses bukan array: ${raw.slice(0, 200)}`);
    }

    return parsed.map((status: unknown): string => (typeof status === 'string' ? status : ''));
}

/** Bukti satu baris form tersimpan untuk event ini (judul tidak kosong). */
function readFormTitles(eventId: string): string[] {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('forms')->where('event_id', '${eventId}')->pluck('title')->all());`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    if (!Array.isArray(parsed)) {
        throw new Error(`titles bukan array: ${raw.slice(0, 200)}`);
    }

    return parsed.map((title: unknown): string => (typeof title === 'string' ? title : ''));
}

/** Tombol simpan yang benar-benar terlihat di header tab (desktop). */
function saveControls(page: Page): Locator {
    return page.getByRole('button', { name: /^(Simpan|Save Form)$/ }).filter({ visible: true });
}

/** Tombol pratinjau yang benar-benar terlihat (header tab dan blok mobile berbagi aria-label). */
function previewControls(page: Page): Locator {
    return page.getByRole('button', { name: 'Pratinjau formulir' }).filter({ visible: true });
}

/** Tunggu Vue benar-benar mount; SSR sudah merender markup sebelum handler aktif. */
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

async function openCreatePage(page: Page, eventId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible();
    await waitForHydration(page);
}

/**
 * Isi syarat publish lewat UI: judul, subtitle, tanggal tutup, dan visibilitas.
 * Field tidak wajib untuk publish (PUT hanya memvalidasi header + daftar field opsional).
 */
/** Pilih tanggal tutup sampai benar-benar terpasang (popover bisa perlu dibuka ulang). */
async function pickClosedAt(page: Page): Promise<void> {
    for (let attempt = 0; attempt < 2; attempt += 1) {
        await page.locator('#create-closed-date').click();

        const cell = page.locator('[data-reka-calendar-cell-trigger]:not([disabled])').nth(15);

        await expect(cell).toBeVisible();
        await cell.click();

        const text = (await page.locator('#create-closed-date').innerText()).trim();

        if (text !== '' && !/pilih tanggal/i.test(text)) return;
    }

    throw new Error('tanggal tutup tidak terpasang setelah dua percobaan');
}

async function fillValidFormDraft(page: Page, title: string): Promise<void> {
    await page.locator('#f-title').fill(title);
    await page.locator('#f-description').fill('Deskripsi e2e P0-1');

    await page.getByRole('tab', { name: /pengaturan form/i }).click();
    await expect(page.locator('#create-closed-date')).toBeVisible();
    await pickClosedAt(page);

    // Draf baru sudah berisi `visible_for: ['public']`, jadi klik hanya bila belum terpilih.
    const publicOption = page.getByRole('button', { name: 'Public', exact: true });
    if ((await publicOption.getAttribute('aria-pressed')) !== 'true') await publicOption.click();
}

/** RegExp exact untuk PUT publish satu form. */
function publishPutPattern(eventId: string, formId: string): RegExp {
    return new RegExp(`/admin/events/${eventId}/forms/${formId}$`);
}

test.describe('P0-1 jalur simpan Create form', () => {
    test('P0-01-save-visible-with-hidden-titles', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await openCreatePage(page, owned.eventId);
            await fillValidFormDraft(page, 'P0-01 simpan create');

            // Prasyarat baris: halaman Create tidak lagi merender judul toolbar.
            await expect(page.getByText(/^New form for /)).toHaveCount(0);

            await expect(saveControls(page)).toHaveCount(1);
            await expect(saveControls(page).first()).toBeEnabled();
            await expect(previewControls(page)).toHaveCount(1);
            await expect(previewControls(page).first()).toBeDisabled();
        } finally {
            owned.cleanup();
        }
    });

    test('P0-02-single-publish-request', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await openCreatePage(page, owned.eventId);
            await fillValidFormDraft(page, 'P0-02 publish create');

            const draftId = readDraftId(owned.eventId);
            const publishPattern = publishPutPattern(owned.eventId, draftId);
            const publishResponses: number[] = [];
            page.on('response', (response): void => {
                if (response.request().method() === 'PUT' && publishPattern.test(new URL(response.url()).pathname)) {
                    publishResponses.push(response.status());
                }
            });

            await Promise.all([
                page.waitForURL(publishPattern, { timeout: 30_000 }),
                saveControls(page).first().click(),
            ]);

            // Publish = satu PUT; tidak ada request tambahan sesudahnya.
            await page.waitForTimeout(1_000);
            expect(publishResponses).toEqual([200]);
            expect(readStatuses(owned.eventId)).toEqual(['published']);
            expect(readFormTitles(owned.eventId)).toEqual(['P0-02 publish create']);
        } finally {
            owned.cleanup();
        }
    });

    test('P0-03-double-click-guard', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await openCreatePage(page, owned.eventId);
            await fillValidFormDraft(page, 'P0-03 double click');

            const draftId = readDraftId(owned.eventId);
            const publishPattern = publishPutPattern(owned.eventId, draftId);
            let publishCalls = 0;
            page.on('request', (request): void => {
                if (request.method() === 'PUT' && publishPattern.test(new URL(request.url()).pathname)) {
                    publishCalls += 1;
                }
            });

            await page.route(publishPattern, async (route): Promise<void> => {
                if (route.request().method() === 'PUT') {
                    await new Promise<void>((done: () => void): void => {
                        setTimeout(done, 1_200);
                    });
                }

                await route.continue();
            });

            await Promise.all([
                page.waitForURL(publishPattern, { timeout: 30_000 }),
                saveControls(page).first().dblclick(),
            ]);

            await page.waitForTimeout(1_000);
            expect(publishCalls, 'klik kedua tidak boleh memicu PUT baru').toBe(1);
            expect(readStatuses(owned.eventId)).toEqual(['published']);
        } finally {
            owned.cleanup();
        }
    });
});
