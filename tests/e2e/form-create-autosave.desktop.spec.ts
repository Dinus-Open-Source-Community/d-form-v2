import type { Locator, Page, TestInfo } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { cleanupOwnerFixture, createOwnerFixture, expect, ownerIdentityForTest, test } from './support/fixtures';
import { readEnvironment } from './support/environment';
import { isTinkerRecord, requireTinkerString } from './support/tinker';

/**
 * Draf hanya boleh ditulis pembuat event (`viewerOwnsEvent`), jadi test draf memakai
 * event milik admin yang login; test 403 memakai `fixtureEvent` (milik user marker).
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

interface IFormRow {
    id: string;
    title: string;
    status: string;
}

/** Semua form satu event (dipakai memastikan draf tunggal dan status publish). */
function readForms(eventId: string): IFormRow[] {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('forms')->where('event_id', '${eventId}')->orderBy('created_at')->get(['id', 'title', 'status'])->all());`,
    ]);

    const parsed: unknown = JSON.parse(raw);

    if (!Array.isArray(parsed)) {
        throw new Error(`forms: stdout tinker bukan array: ${raw.slice(0, 200)}`);
    }

    return parsed.map((row: unknown): IFormRow => {
        if (!isTinkerRecord(row)) throw new Error('forms: baris bukan objek.');

        return {
            id: requireTinkerString(row, 'id', 'forms.id'),
            title: typeof row.title === 'string' ? row.title : '',
            status: typeof row.status === 'string' ? row.status : '',
        };
    });
}

/** Buat form draf kosong milik admin pada event ini (judul & deskripsi masih kosong). */
function createEmptyDraftForm(eventId: string): string {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `$form = \\App\\Models\\Form::create(['event_id' => '${eventId}', 'title' => '', 'description' => '', 'success_content' => null, 'closed_at' => null, 'visible_for' => ['public'], 'banner_url' => null, 'banner_caption' => null, 'metadata' => null, 'status' => 'draft', 'created_by' => auth()->id() ?? \\App\\Models\\User::where('email', 'superadmin@gmail.com')->value('id')]); echo json_encode($form->id);`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    if (typeof parsed !== 'string' || parsed === '') {
        throw new Error(`draft form tidak terbentuk: ${raw.slice(0, 200)}`);
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

async function openCreatePage(page: Page, eventId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/create`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible();
    await waitForHydration(page);
}

function settingsTab(page: Page): Locator {
    return page.getByRole('tab', { name: /pengaturan form/i });
}

function saveButton(page: Page): Locator {
    return page.getByRole('button', { name: 'Save Form' });
}

function fieldLabelInput(page: Page): Locator {
    return page.locator('input[placeholder^="Label pertanyaan"]').first();
}

/** Tambah satu field Short Text dari palette (drag diulang sekali bila drop tidak sampai). */
async function addShortTextField(page: Page): Promise<void> {
    await page.getByRole('button', { name: /text inputs/i }).click();

    const shortText = page.getByText('Short Text', { exact: true }).first();
    await expect(shortText).toBeVisible();

    const fieldRows = page.locator('.fb-field-row');
    const canvas = page
        .locator('section')
        .filter({ has: page.locator('#f-title') })
        .first();

    for (let attempt = 0; attempt < 2; attempt += 1) {
        await shortText.dragTo(canvas, { targetPosition: { x: 40, y: 60 }, timeout: 20_000 });

        try {
            await expect(fieldRows).toHaveCount(1, { timeout: 5_000 });

            return;
        } catch (error) {
            if (attempt === 1) throw error;
        }
    }
}

/** Isi header form: judul, subtitle, tanggal tutup, dan visibilitas Public. */
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

/**
 * Klik Save Form; bila server menjawab 422 karena tanggal tutup belum terpasang,
 * pilih tanggal lagi lalu submit ulang (maksimal dua percobaan).
 */
async function publishWithDateRetry(
    page: Page,
    eventId: string,
    formId: string,
    openSettings: (target: Page) => Locator
): Promise<void> {
    const settingsPath = new RegExp(`/admin/events/${eventId}/forms/${formId}$`);

    for (let attempt = 0; attempt < 2; attempt += 1) {
        const response = page.waitForResponse(
            (candidate): boolean =>
                candidate.request().method() === 'PUT' && settingsPath.test(new URL(candidate.url()).pathname),
            { timeout: 30_000 }
        );

        await saveButton(page).click();

        const result = await response;

        if (result.status() !== 422) return;

        await openSettings(page).click();
        await pickClosedAt(page);
    }
}

async function fillFormHeader(page: Page, title: string): Promise<void> {
    await page.locator('#f-title').fill(title);
    await page.locator('#f-description').fill('Deskripsi autosave e2e');

    await settingsTab(page).click();
    await expect(page.locator('#create-closed-date')).toBeVisible();
    await pickClosedAt(page);

    // Draf baru sudah berisi `visible_for: ['public']`, jadi klik hanya bila belum terpilih.
    const publicOption = page.getByRole('button', { name: 'Public', exact: true });
    if ((await publicOption.getAttribute('aria-pressed')) !== 'true') await publicOption.click();
}

test.describe('create form: autosave draf + publish', () => {
    test('hydrasi draf setelah reload, publish membuka halaman edit tanpa badge Draf', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await openCreatePage(page, owned.eventId);

            // Draf dibuat sekali saat mount (idempoten), autosave belum menulis apa pun.
            await expect.poll((): number => readForms(owned.eventId).length, { timeout: 20_000 }).toBe(1);
            const draftId = readForms(owned.eventId)[0]?.id ?? '';
            expect(draftId).not.toBe('');

            await page.locator('#f-title').fill('Form Autosave E2E');
            await addShortTextField(page);
            await fillFormHeader(page, 'Form Autosave E2E');

            // Autosave menulis header + field ke draf yang sama.
            await expect(page.getByTestId('autosave-status')).toContainText('Tersimpan');
            await expect
                .poll((): string => readForms(owned.eventId)[0]?.title ?? '', { timeout: 20_000 })
                .toBe('Form Autosave E2E');

            // Reload: snapshot server memulihkan judul + field, tanpa save tambahan.
            const writesAfterReload: string[] = [];
            page.on('request', (request): void => {
                const path = new URL(request.url()).pathname;
                const isWrite = request.method() === 'PUT' || (request.method() === 'POST' && path.endsWith('/fields'));

                if (isWrite) writesAfterReload.push(`${request.method()} ${path}`);
            });

            await page.reload({ waitUntil: 'domcontentloaded' });
            await waitForHydration(page);

            await expect(page.locator('#f-title')).toHaveValue('Form Autosave E2E');
            await expect(fieldLabelInput(page)).toHaveValue('Short Text');
            await page.waitForTimeout(1_500);
            expect(writesAfterReload, 'hidrasi tidak boleh memicu autosave baru').toEqual([]);

            // Publish dari state hasil hidrasi. Bila picker tanggal belum menempel
            // (perilaku popover reka-ui), pilih ulang lalu coba publish sekali lagi.
            await publishWithDateRetry(page, owned.eventId, draftId, settingsTab);

            await expect(page).toHaveURL(new RegExp(`/admin/events/${owned.eventId}/forms/${draftId}$`), {
                timeout: 30_000,
            });

            expect(readForms(owned.eventId).map((row) => row.status)).toEqual(['published']);

            // Sidebar acara: satu form hasil publish, tanpa badge Draf.
            await page.goto(`/admin/events/${owned.eventId}`, { waitUntil: 'domcontentloaded' });

            await expect(page.getByRole('link', { name: 'Form Autosave E2E', exact: true })).toBeVisible();
            await expect(page.locator('[title="Belum dipublikasikan"]')).toHaveCount(0);
        } finally {
            owned.cleanup();
        }
    });

    test('anomali: simpan dengan header kosong → satu 422, toast Indonesia tanpa ringkasan, error di kedua tab', async ({
        page,
    }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            await openCreatePage(page, owned.eventId);

            let unprocessableCount = 0;
            page.on('response', (response): void => {
                if (response.request().method() === 'PUT' && response.status() === 422) {
                    unprocessableCount += 1;
                }
            });

            await saveButton(page).click();

            // Toast: pesan per-field Bahasa Indonesia, bukan ringkasan/JSON Laravel.
            const toast = page.locator('[data-sonner-toast]').first();

            await expect(toast).toContainText('Judul form wajib diisi.');
            await expect(toast).toContainText('Deskripsi form wajib diisi.');
            await expect(toast).toContainText('Pilih tanggal tutup form.');

            const bodyText = await page.locator('body').innerText();

            expect(bodyText).not.toContain('(and ');
            expect(bodyText).not.toContain('{"message"');
            expect(bodyText).not.toContain('Please enter');
            expect(bodyText).not.toContain('Please select');

            // Error pertama = judul → tab Editor, field judul & deskripsi ditandai.
            await expect(page.getByRole('tab', { name: 'Editor' })).toHaveAttribute('aria-selected', 'true');
            await expect(page.locator('#f-title-error')).toHaveText('Judul form wajib diisi.');
            await expect(page.locator('#f-description-error')).toHaveText('Deskripsi form wajib diisi.');

            // Tab pengaturan memuat error tanggal tutup dari bag yang sama.
            await settingsTab(page).click();
            await expect(page.locator('#create-closed-date')).toBeVisible();

            const settingsPanel = page.getByRole('tabpanel', { name: 'Pengaturan Form' });

            await expect(settingsPanel.locator('p.text-destructive').first()).toHaveText('Pilih tanggal tutup form.');

            // Tepat satu percobaan PUT: tidak ada badai request 422.
            await page.waitForTimeout(1_500);
            expect(unprocessableCount).toBe(1);
            expect(readForms(owned.eventId).map((row) => row.status)).toEqual(['draft']);
            await expect(page).toHaveURL(new RegExp(`/admin/events/${owned.eventId}/forms/create$`));

            // Lengkapi header → publish berhasil.
            await page.getByRole('tab', { name: 'Editor' }).click();
            await addShortTextField(page);
            await fillFormHeader(page, 'Form Setelah 422');

            await Promise.all([
                page.waitForURL(new RegExp(`/admin/events/${owned.eventId}/forms/[0-9a-f-]+$`), { timeout: 30_000 }),
                saveButton(page).click(),
            ]);

            expect(readForms(owned.eventId).map((row) => row.status)).toEqual(['published']);
        } finally {
            owned.cleanup();
        }
    });

    test('draf kosong: drag field tidak memicu 422 dan autosave memakai jalur parsial', async ({ page }) => {
        const owned = createAdminOwnedEvent(test.info());

        try {
            const draftId = createEmptyDraftForm(owned.eventId);
            const putBodies: Array<{ path: string; body: string; status: number }> = [];
            const fieldPosts: Array<{ body: string; status: number }> = [];
            const responseStatuses: number[] = [];

            page.on('response', async (response): Promise<void> => {
                const path = new URL(response.url()).pathname;
                const body = response.request().postData() ?? '';

                if (!path.includes('/admin/events/')) return;
                responseStatuses.push(response.status());

                if (response.request().method() === 'PUT') {
                    putBodies.push({ path, body, status: response.status() });
                    return;
                }

                if (response.request().method() === 'POST' && path.endsWith(`/forms/${draftId}/fields`)) {
                    fieldPosts.push({ body, status: response.status() });
                }
            });

            await page.goto(`/admin/events/${owned.eventId}/forms/${draftId}`, { waitUntil: 'domcontentloaded' });
            await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
            await waitForHydration(page);

            await addShortTextField(page);
            await expect(page.getByTestId('autosave-status')).toContainText('Tersimpan', { timeout: 20_000 });

            // Kanal fields murni: satu POST /fields, tanpa settings PUT.
            expect(fieldPosts).toHaveLength(1);
            expect(fieldPosts[0]?.status).toBe(200);

            const fieldBody = JSON.parse(fieldPosts[0]?.body ?? '{}') as Record<string, unknown>;

            expect(Object.keys(fieldBody)).toEqual(['fields']);
            expect(putBodies, 'drag field tidak boleh memicu PUT settings').toEqual([]);
            expect(responseStatuses, 'drag di draf tidak boleh menghasilkan 422').not.toContain(422);
            await expect(page.getByText('Please')).toHaveCount(0);

            // Judul saja → tersimpan di server (jalur autosave parsial) → reload memulihkannya.
            await page.locator('#f-title').fill('Draf Judul Saja');
            await expect
                .poll((): string => readForms(owned.eventId).find((row) => row.id === draftId)?.title ?? '', {
                    timeout: 20_000,
                })
                .toBe('Draf Judul Saja');

            await page.reload({ waitUntil: 'domcontentloaded' });
            await waitForHydration(page);
            await expect(page.locator('#f-title')).toHaveValue('Draf Judul Saja');
        } finally {
            owned.cleanup();
        }
    });

    test('non-creator: tanpa indikator autosave, simpan manual tetap membuat form', async ({ page, fixtureEvent }) => {
        // `fixtureEvent` milik user marker → admin yang login bukan pembuat acara → draf 403.
        const eventId = fixtureEvent.eventId;

        await openCreatePage(page, eventId);

        await expect(page.getByTestId('autosave-unavailable')).toBeVisible();
        await expect(page.getByTestId('autosave-status')).toHaveCount(0);

        await page.locator('#f-title').fill('Form Simpan Manual');
        await addShortTextField(page);
        await fillFormHeader(page, 'Form Simpan Manual');

        await Promise.all([
            page.waitForURL(new RegExp(`/admin/events/${eventId}/forms/[0-9a-f-]+$`), { timeout: 30_000 }),
            saveButton(page).click(),
        ]);

        const forms = readForms(eventId);

        expect(forms).toHaveLength(1);
        expect(forms[0]?.title).toBe('Form Simpan Manual');
        expect(forms[0]?.status).toBe('published');
    });
});
