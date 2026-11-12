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

interface IFormRow {
    id: string;
    title: string;
}

function readForms(eventId: string): IFormRow[] {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('forms')->where('event_id', '${eventId}')->orderBy('created_at')->get(['id', 'title'])->all());`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    if (!Array.isArray(parsed)) throw new Error(`forms: bukan array: ${raw.slice(0, 200)}`);

    return parsed.map((row: unknown): IFormRow => {
        if (!isTinkerRecord(row)) throw new Error('forms: baris bukan objek');

        return {
            id: requireTinkerString(row, 'id', 'forms.id'),
            title: typeof row.title === 'string' ? row.title : '',
        };
    });
}

/** Jumlah field tersimpan pada satu form (dibaca dari DB). */
function readFieldCount(formId: string): number {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('form_fields')->where('form_id', '${formId}')->whereNull('deleted_at')->count());`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    return typeof parsed === 'number' ? parsed : 0;
}

/** Jumlah section tersimpan di metadata form (dibaca dari DB). */
function readSectionCount(formId: string): number {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `$f = \\Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->value('metadata'); echo json_encode(count(json_decode((string) $f, true)['sections'] ?? []));`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    return typeof parsed === 'number' ? parsed : 0;
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

/** Hitung request autosave (PUT settings / POST fields) sejak dipanggil. */
function countAutosaveRequests(page: Page, eventId: string): { count: () => number; reset: () => void } {
    const pattern = new RegExp(`/admin/events/${eventId}/forms/[0-9a-f-]+(/fields)?$`);
    let total = 0;

    page.on('request', (request): void => {
        if (request.method() === 'GET') return;
        if (pattern.test(new URL(request.url()).pathname)) total += 1;
    });

    return { count: (): number => total, reset: (): void => undefined };
}

test.describe('autosave dua kanal', () => {
    test('section saja = 0 request; field pertama = satu POST /fields tanpa data header', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await page.goto(`/admin/events/${owned.eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toBeVisible();
            await waitForHydration(page);

            const formId = readForms(owned.eventId)[0]?.id ?? '';
            expect(formId).not.toBe('');

            const requests: Array<{ method: string; path: string; body: string }> = [];

            page.on('request', (request): void => {
                if (request.method() === 'GET') return;

                const path = new URL(request.url()).pathname;

                if (!path.includes(`/forms/${formId}`)) return;

                requests.push({ method: request.method(), path, body: request.postData() ?? '' });
            });

            const sectionsBefore = readSectionCount(formId);

            // Section saja: tidak ada request autosave sama sekali.
            await page.getByRole('button', { name: /tambah bagian setelah ini/i }).click();
            await expect(page.getByText('Bagian 2', { exact: true })).toBeVisible();
            await page.waitForTimeout(1_500);

            expect(requests, 'tambah section tidak boleh memicu autosave').toEqual([]);
            expect(readSectionCount(formId)).toBe(sectionsBefore);

            // Field pertama membuka kanal fields: satu POST /fields tanpa data header.
            await page.getByRole('button', { name: /text inputs/i }).click();
            const shortText = page.getByText('Short Text', { exact: true }).first();
            const canvas = page
                .locator('section')
                .filter({ has: page.locator('#f-title') })
                .first();

            for (let attempt = 0; attempt < 2; attempt += 1) {
                await shortText.dragTo(canvas, { targetPosition: { x: 40, y: 60 }, timeout: 20_000 });

                try {
                    await expect(page.locator('.fb-field-row')).toHaveCount(1, { timeout: 5_000 });
                    break;
                } catch (error) {
                    if (attempt === 1) throw error;
                }
            }

            await expect(page.getByTestId('autosave-status')).toContainText('Tersimpan', { timeout: 20_000 });

            const fieldsRequests = requests.filter((entry) => entry.path.endsWith('/fields'));

            expect(fieldsRequests).toHaveLength(1);
            expect(fieldsRequests[0]?.method).toBe('POST');

            const body = JSON.parse(fieldsRequests[0]?.body ?? '{}') as Record<string, unknown>;

            // Payload fields murni: hanya kunci `fields`.
            expect(Object.keys(body)).toEqual(['fields']);
            expect(Array.isArray(body.fields)).toBe(true);

            // Kanal fields tidak menyentuh endpoint settings (tidak ada header/section).
            expect(requests.every((entry) => !entry.path.endsWith(`/forms/${formId}`))).toBe(true);
            expect(readSectionCount(formId)).toBe(sectionsBefore);

            // Field bertahan setelah reload; section tidak ikut di-autosave.
            await page.reload({ waitUntil: 'domcontentloaded' });
            await waitForHydration(page);
            await expect(page.locator('.fb-field-row')).toHaveCount(1);
            expect(readSectionCount(formId)).toBe(sectionsBefore);
        } finally {
            owned.cleanup();
        }
    });

    test('hapus field terakhir → satu POST /fields berisi daftar kosong', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await page.goto(`/admin/events/${owned.eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toBeVisible();
            await waitForHydration(page);

            const formId = readForms(owned.eventId)[0]?.id ?? '';

            // Tambah satu field lalu hapus.
            await page.getByRole('button', { name: /text inputs/i }).click();
            const shortText = page.getByText('Short Text', { exact: true }).first();
            const canvas = page
                .locator('section')
                .filter({ has: page.locator('#f-title') })
                .first();

            for (let attempt = 0; attempt < 2; attempt += 1) {
                await shortText.dragTo(canvas, { targetPosition: { x: 40, y: 60 }, timeout: 20_000 });

                try {
                    await expect(page.locator('.fb-field-row')).toHaveCount(1, { timeout: 5_000 });
                    break;
                } catch (error) {
                    if (attempt === 1) throw error;
                }
            }

            await expect.poll((): number => readFieldCount(formId), { timeout: 20_000 }).toBe(1);

            // Hapus field terakhir: POST /fields dengan daftar kosong (bukan PUT settings).
            const emptySnapshot = page.waitForRequest(
                (request): boolean =>
                    request.method() === 'POST' &&
                    new URL(request.url()).pathname.endsWith(`/forms/${formId}/fields`) &&
                    (request.postData() ?? '').includes('"fields":[]'),
                { timeout: 20_000 }
            );

            await page.getByRole('button', { name: 'Hapus field' }).first().click();
            await expect(page.locator('.fb-field-row')).toHaveCount(0);

            const deleteRequest = await emptySnapshot;
            const deleteBody = deleteRequest.postDataJSON() as Record<string, unknown>;

            expect(deleteBody).toEqual({ fields: [] });

            await expect.poll((): number => readFieldCount(formId), { timeout: 20_000 }).toBe(0);

            await page.reload({ waitUntil: 'domcontentloaded' });
            await waitForHydration(page);
            await expect(page.locator('.fb-field-row')).toHaveCount(0);
        } finally {
            owned.cleanup();
        }
    });

    test('mengubah Title hanya menyimpan header (tanpa payload field)', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await page.goto(`/admin/events/${owned.eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toBeVisible();
            await waitForHydration(page);

            const formId = readForms(owned.eventId)[0]?.id ?? '';
            const settingsPath = `/admin/events/${owned.eventId}/forms/${formId}`;
            const fieldsRequests: string[] = [];

            page.on('request', (request): void => {
                const path = new URL(request.url()).pathname;

                if (request.method() === 'POST' && path.endsWith(`${settingsPath}/fields`)) {
                    fieldsRequests.push(path);
                }
            });

            const headerPutPromise = page.waitForRequest(
                (request): boolean =>
                    request.method() === 'PUT' && new URL(request.url()).pathname.endsWith(settingsPath),
                { timeout: 20_000 }
            );

            await page.locator('#f-title').fill('Judul Saja');

            const headerPut = await headerPutPromise;
            const headerBody = headerPut.postDataJSON() as Record<string, unknown>;

            // Payload kanal header: judul + deskripsi (draf), tanpa field/metadata.
            expect(Object.keys(headerBody).sort()).toEqual(['autosave', 'description', 'title']);
            expect(fieldsRequests).toEqual([]);

            await expect
                .poll((): string => readForms(owned.eventId)[0]?.title ?? '', { timeout: 20_000 })
                .toBe('Judul Saja');

            await page.reload({ waitUntil: 'domcontentloaded' });
            await waitForHydration(page);
            await expect(page.locator('#f-title')).toHaveValue('Judul Saja');
        } finally {
            owned.cleanup();
        }
    });
});
