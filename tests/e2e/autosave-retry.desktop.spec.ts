import type { Page, Request, TestInfo } from '@playwright/test';
import { runArtisan } from './support/artisan';
import { readEnvironment } from './support/environment';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    defaultFixtureRequest,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';
import { startRequestLog } from './support/network';
import type { IOwnerIdentity } from './support/marker';

type TWriteKind = 'fields-post' | 'form-put';

interface IWriteEvent {
    id: number;
    kind: TWriteKind;
    phase: 'start' | 'end';
    at: number;
}

interface IWriteMonitor {
    events: IWriteEvent[];
    /** Jumlah request tulis yang belum mengirim `requestfinished`/`requestfailed`. */
    openCount: () => number;
    maxConcurrent: () => number;
    stop: () => void;
}

interface IWriteInterval {
    start: number;
    end: number;
}

/**
 * Toleransi batas request. `requestfinished` untuk respons yang sudah selesai
 * dapat dipancarkan beberapa milidetik setelah `request` berikutnya muncul,
 * sehingga tanpa toleransi serialisasi sempurna tampak overlap ~1 ms. Overlap
 * nyata pada skenario ini berdurasi sepanjang request (POST tertahan 1200 ms,
 * PUT puluhan-ratusan ms), jauh di atas toleransi. Konsekuensinya overlap
 * lebih pendek dari 20 ms tidak terdeteksi — di luar skenario yang diuji.
 */
const WRITE_BOUNDARY_TOLERANCE_MS = 20;

interface IAdminOwnedFormFixture {
    identity: IOwnerIdentity;
    eventId: string;
    formId: string;
}

const FORM_ID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/** Escape satu path agar bisa dipakai sebagai RegExp exact-match. */
function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Path halaman Show form (editor + jawaban + settings). */
function showFormPath(eventId: string, formId: string, search: string = ''): string {
    return `/admin/events/${eventId}/forms/${formId}${search}`;
}

/** RegExp exact untuk POST field snapshot autosave. */
function fieldsPostPattern(eventId: string, formId: string): RegExp {
    return new RegExp(`${escapeRegExp(`/admin/events/${eventId}/forms/${formId}/fields`)}$`);
}

/** RegExp exact untuk PUT header/metadata form. */
function formPutPattern(eventId: string, formId: string): RegExp {
    return new RegExp(`${escapeRegExp(`/admin/events/${eventId}/forms/${formId}`)}$`);
}

/**
 * Harness `fixtureOwner` membuat event milik user marker, sehingga admin yang
 * login (storageState) mendapat 403. Buat event/form milik admin langsung lewat
 * helper fixture, lalu bersihkan dengan marker yang sama.
 */
function createAdminOwnedForm(testInfo: TestInfo): IAdminOwnedFormFixture {
    const identity = ownerIdentityForTest(testInfo);
    const ownerEmail = readEnvironment().adminEmail;
    const owner = createOwnerFixture(identity, ownerEmail);
    const forms = createFormsFixture(owner, {
        ...defaultFixtureRequest(identity, 1, 1, 0),
        ownerEmail,
    });
    const primary = forms[0];

    if (primary === undefined) {
        cleanupOwnerFixture(identity);
        throw new Error('e2e:fixture returned no form.');
    }

    return { identity, eventId: owner.eventId, formId: primary.formId };
}

/**
 * Fixture membuat form dengan `closed_at = null`, sedangkan autosave mengirim
 * `closed_at` kosong ke endpoint update yang mewajibkannya (`required|date`).
 * Tanpa nilai tutup, setiap PUT akan 422 karena header, bukan karena judul yang
 * sedang diuji. Isi kolom itu sekali agar sisa skenario menguji perilaku autosave
 * yang sebenarnya. Bukan bagian dari kode produksi.
 */
function primeFormClosedAt(formId: string): void {
    if (!FORM_ID_PATTERN.test(formId)) {
        throw new Error(`Unexpected fixture form id "${formId}".`);
    }

    runArtisan(
        `\\App\\Models\\Form::whereKey('${formId}')->update(['closed_at' => \\Illuminate\\Support\\Carbon::now()->addDay()]);`,
    );
}

/** Buka halaman Show form di tab editor dan pastikan input judul kanvas siap. */
async function openShowEditor(page: Page, eventId: string, formId: string): Promise<void> {
    const response = await page.goto(showFormPath(eventId, formId), { waitUntil: 'domcontentloaded' });
    const status = response?.status() ?? 0;

    if (status !== 200) {
        const body = await page.locator('body').innerText().catch((): string => '');
        throw new Error(`Show form returned HTTP ${status}: ${body.slice(0, 300)}`);
    }

    try {
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    } catch (error) {
        const body = await page.locator('body').innerText().catch((): string => '');
        throw new Error(`#f-title missing at ${page.url()}: ${body.slice(0, 500)}; cause: ${String(error)}`);
    }
}

/** Judul unik per test/retry; selalu memuat marker fixture. */
function uniqueTitle(marker: string, label: string): string {
    return `Autosave ${label} [${marker}]`;
}

/**
 * Catat start/end request tulis (`POST fields` dan `PUT form`) untuk membuktikan
 * serialisasi. "end" memakai `requestfinished`/`requestfailed` agar respons benar
 * benar selesai sebelum request berikutnya dianggap mulai.
 */
function monitorWrites(page: Page, fieldsPattern: RegExp, putPattern: RegExp): IWriteMonitor {
    const requestIds = new Map<Request, number>();
    const events: IWriteEvent[] = [];
    let nextId = 0;

    const kindOf = (request: Request): TWriteKind | null => {
        const url = request.url();
        if (request.method() === 'POST' && fieldsPattern.test(url)) return 'fields-post';
        if (request.method() === 'PUT' && putPattern.test(url)) return 'form-put';
        return null;
    };

    const onRequest = (request: Request): void => {
        const kind = kindOf(request);
        if (kind === null) return;
        nextId += 1;
        requestIds.set(request, nextId);
        events.push({ id: nextId, kind, phase: 'start', at: Date.now() });
    };

    const onSettled = (request: Request): void => {
        const kind = kindOf(request);
        if (kind === null) return;
        const id = requestIds.get(request);
        if (id === undefined) return;
        requestIds.delete(request);
        events.push({ id, kind, phase: 'end', at: Date.now() });
    };

    page.on('request', onRequest);
    page.on('requestfinished', onSettled);
    page.on('requestfailed', onSettled);

    return {
        events,
        openCount: (): number =>
            events.filter((entry: IWriteEvent): boolean => entry.phase === 'start').length -
            events.filter((entry: IWriteEvent): boolean => entry.phase === 'end').length,
        /**
         * Puncak konkurensi dari interval request yang berpasangan. Start digeser
         * sebesar toleransi batas agar `requestfinished` yang tertinggal beberapa
         * milidetik tidak dihitung sebagai overlap; overlap nyata tetap terdeteksi.
         */
        maxConcurrent: (): number => {
            const openStarts = new Map<number, number>();
            const intervals: IWriteInterval[] = [];

            for (const entry of events) {
                if (entry.phase === 'start') {
                    openStarts.set(entry.id, entry.at);
                    continue;
                }

                const start = openStarts.get(entry.id);
                if (start === undefined) continue;
                openStarts.delete(entry.id);
                intervals.push({ start, end: entry.at });
            }

            const boundaries = intervals.flatMap((interval: IWriteInterval) => [
                { at: interval.start + WRITE_BOUNDARY_TOLERANCE_MS, delta: 1 },
                { at: interval.end, delta: -1 },
            ]);

            boundaries.sort((a, b): number => a.at - b.at || a.delta - b.delta);

            let running = 0;
            let peak = 0;

            for (const boundary of boundaries) {
                running = Math.max(0, running + boundary.delta);
                peak = Math.max(peak, running);
            }

            return peak;
        },
        stop: (): void => {
            page.off('request', onRequest);
            page.off('requestfinished', onSettled);
            page.off('requestfailed', onSettled);
        },
    };
}

/** Ringkas event tulis jadi timeline relatif untuk pesan kegagalan konkurensi. */
function describeWriteEvents(events: IWriteEvent[]): string {
    const first = events[0];
    if (first === undefined) return 'no write events';

    return events
        .map((entry: IWriteEvent): string => `${entry.at - first.at}ms ${entry.kind} ${entry.phase}`)
        .join(' | ');
}

test.describe('AUDIT-P1-014 autosave retry dan single-flight', () => {
    test('halaman error lalu retry menyimpan judul dan bertahan setelah reload', async ({ page }) => {
        const fixture = createAdminOwnedForm(test.info());
        primeFormClosedAt(fixture.formId);

        const fieldsPattern = fieldsPostPattern(fixture.eventId, fixture.formId);
        let fieldsPostAttempts = 0;
        await page.route(fieldsPattern, async (route): Promise<void> => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }

            fieldsPostAttempts += 1;
            if (fieldsPostAttempts === 1) {
                await route.fulfill({
                    status: 500,
                    contentType: 'application/json',
                    body: JSON.stringify({ message: 'boom' }),
                });
                return;
            }

            await route.continue();
        });

        const requestLog = startRequestLog(page);
        const title = uniqueTitle(fixture.identity.marker, 'retry');

        try {
            await openShowEditor(page, fixture.eventId, fixture.formId);
            const status = page.getByTestId('autosave-status');
            await page.locator('#f-title').fill(title);

            await expect(status.getByText('Gagal menyimpan otomatis')).toBeVisible();
            const retryButton = page.getByRole('button', { name: 'Coba lagi menyimpan otomatis' });
            await expect(retryButton).toBeVisible();

            await retryButton.click();
            await expect(status.getByText('Tersimpan')).toBeVisible();
            await expect(page.locator('#f-title')).toHaveValue(title);

            // Dihitung sebelum reload: hidrasi setelah reload bisa memicu save tambahan.
            expect(requestLog.requestCount('POST', fieldsPattern)).toBe(2);

            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toHaveValue(title);
        } finally {
            requestLog.stop();
            await page.unroute(fieldsPattern);
            cleanupOwnerFixture(fixture.identity);
        }
    });

    test('judul kosong memicu 422 lalu judul valid tersimpan tanpa menyimpan nilai kosong', async ({
        page,
    }) => {
        const fixture = createAdminOwnedForm(test.info());
        primeFormClosedAt(fixture.formId);

        const putPattern = formPutPattern(fixture.eventId, fixture.formId);
        const requestLog = startRequestLog(page);
        const validTitle = uniqueTitle(fixture.identity.marker, 'valid');

        try {
            const status = page.getByTestId('autosave-status');
            await openShowEditor(page, fixture.eventId, fixture.formId);
            await page.locator('#f-title').fill('');
            await expect(status.getByText('Gagal menyimpan otomatis')).toBeVisible();
            await expect.poll((): number[] => requestLog.responseStatuses(putPattern)).toContain(422);

            await page.locator('#f-title').fill(validTitle);
            await expect(status.getByText('Tersimpan')).toBeVisible();
            await expect(page.locator('#f-title')).toHaveValue(validTitle);

            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toHaveValue(validTitle);
            expect(await page.locator('#f-title').inputValue()).not.toBe('');
        } finally {
            requestLog.stop();
            cleanupOwnerFixture(fixture.identity);
        }
    });

    test('manual save saat autosave berjalan tidak overlap dan respons terakhir menang', async ({
        page,
    }) => {
        const fixture = createAdminOwnedForm(test.info());
        primeFormClosedAt(fixture.formId);

        const fieldsPattern = fieldsPostPattern(fixture.eventId, fixture.formId);
        const putPattern = formPutPattern(fixture.eventId, fixture.formId);

        await page.route(fieldsPattern, async (route): Promise<void> => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }

            await new Promise<void>((done: () => void): void => {
                setTimeout(done, 1200);
            });
            await route.continue();
        });

        const monitor = monitorWrites(page, fieldsPattern, putPattern);
        const title = uniqueTitle(fixture.identity.marker, 'manual');

        try {
            await openShowEditor(page, fixture.eventId, fixture.formId);
            await page.locator('#f-title').fill(title);

            await expect
                .poll((): number =>
                    monitor.events.filter(
                        (entry: IWriteEvent): boolean =>
                            entry.kind === 'fields-post' && entry.phase === 'start',
                    ).length,
                )
                .toBeGreaterThanOrEqual(1);

            await page.getByRole('button', { name: 'Save All' }).click();

            // Kondisi terminal: semua write sudah settle DAN manual PUT (PUT kedua)
            // sudah dimulai. Ini mencegah poll lolos di jeda antara POST dan PUT.
            await expect
                .poll(
                    (): number => {
                        const starts = monitor.events.filter(
                            (entry: IWriteEvent): boolean => entry.phase === 'start',
                        ).length;
                        const ends = monitor.events.filter(
                            (entry: IWriteEvent): boolean => entry.phase === 'end',
                        ).length;
                        const putStarts = monitor.events.filter(
                            (entry: IWriteEvent): boolean =>
                                entry.kind === 'form-put' && entry.phase === 'start',
                        ).length;

                        return starts === ends && putStarts >= 2 ? ends : -1;
                    },
                    { timeout: 15_000 },
                )
                .toBeGreaterThanOrEqual(3);

            expect(monitor.openCount()).toBe(0);
            expect(monitor.maxConcurrent(), describeWriteEvents(monitor.events)).toBe(1);
            await expect(
                page.getByTestId('autosave-status').getByText('Tersimpan'),
            ).toBeVisible();

            const putStarts = monitor.events.filter(
                (entry: IWriteEvent): boolean => entry.kind === 'form-put' && entry.phase === 'start',
            );
            const putEndsById = new Map<number, number>(
                monitor.events
                    .filter(
                        (entry: IWriteEvent): boolean =>
                            entry.kind === 'form-put' && entry.phase === 'end',
                    )
                    .map((entry: IWriteEvent): [number, number] => [entry.id, entry.at]),
            );

            expect(putStarts.length).toBeGreaterThanOrEqual(2);

            for (let index = 1; index < putStarts.length; index += 1) {
                const currentStart = putStarts[index];
                const previousStart = putStarts[index - 1];
                if (currentStart === undefined || previousStart === undefined) {
                    throw new Error('Expected PUT start events for the manual save.');
                }

                const previousEnd = putEndsById.get(previousStart.id);
                if (previousEnd === undefined) {
                    throw new Error(`PUT ${previousStart.id} has no end event.`);
                }

                // Toleransi yang sama dengan pengukuran konkurensi: event end/start
                // boleh tertukar beberapa milidetik, tapi manual PUT tidak boleh
                // benar-benar berjalan selama PUT autosave.
                expect(currentStart.at + WRITE_BOUNDARY_TOLERANCE_MS).toBeGreaterThanOrEqual(previousEnd);
            }

            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toHaveValue(title);
        } finally {
            monitor.stop();
            await page.unroute(fieldsPattern);
            cleanupOwnerFixture(fixture.identity);
        }
    });
});
