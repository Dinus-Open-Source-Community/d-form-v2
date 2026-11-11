import type { Locator, Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1 });

const FIELDS_REQUEST_PATH = /\/admin\/events\/[^/]+\/forms\/[^/]+\/fields$/;

/** Bentuk field pada payload `POST {form}/fields` yang dipakai assertion id. */
interface IBackendFieldPayload {
    id: string;
    name: string;
    label: string;
    metadata?: { sectionId?: string; builderType?: string; optionChoices?: { id: string }[] };
}

/** Entri log payload fields; satu entri = satu request autosave. */
interface IFieldsPayloadLog {
    payloads: IBackendFieldPayload[][];
}

function isFieldsPayload(value: unknown): value is { fields: unknown[] } {
    if (typeof value !== 'object' || value === null) return false;

    return Array.isArray(Reflect.get(value, 'fields'));
}

function isBackendFieldPayload(value: unknown): value is IBackendFieldPayload {
    if (typeof value !== 'object' || value === null) return false;

    return typeof Reflect.get(value, 'id') === 'string' && typeof Reflect.get(value, 'name') === 'string';
}

/** Parse body request autosave; body non-JSON diabaikan, bukan kegagalan test. */
function readFieldsPayload(raw: string | null): IBackendFieldPayload[] {
    if (raw === null) return [];

    let parsed: unknown;

    try {
        parsed = JSON.parse(raw);
    } catch {
        return [];
    }

    if (!isFieldsPayload(parsed)) return [];

    return parsed.fields.filter(isBackendFieldPayload);
}

/** Rekam setiap payload fields yang dikirim builder; dipakai untuk membuktikan id terisi. */
function captureFieldsPayloads(page: Page): IFieldsPayloadLog {
    const log: IFieldsPayloadLog = { payloads: [] };

    page.on('request', (request) => {
        if (request.method() !== 'POST') return;
        if (!FIELDS_REQUEST_PATH.test(new URL(request.url()).pathname)) return;

        const fields = readFieldsPayload(request.postData());
        if (fields.length > 0) log.payloads.push(fields);
    });

    return log;
}

/** Payload terbaru yang memenuhi `predicate`, atau `null` bila belum ada. */
function findFieldsPayload(
    log: IFieldsPayloadLog,
    predicate: (fields: IBackendFieldPayload[]) => boolean
): IBackendFieldPayload[] | null {
    for (let index = log.payloads.length - 1; index >= 0; index -= 1) {
        if (predicate(log.payloads[index])) return log.payloads[index];
    }

    return null;
}

/**
 * Hapus `crypto.randomUUID` sebelum bundle aplikasi dieksekusi. Insecure context
 * (HTTP LAN) tidak menyediakan method ini; menghapusnya membuat test mereproduksi
 * kondisi produksi tanpa memerlukan origin non-secure.
 */
async function disableRandomUuid(page: Page): Promise<void> {
    await page.addInitScript((): void => {
        const cryptoObject: unknown = Reflect.get(globalThis, 'crypto');
        if (typeof cryptoObject !== 'object' || cryptoObject === null) return;

        Reflect.deleteProperty(cryptoObject, 'randomUUID');

        const prototype: unknown = Object.getPrototypeOf(cryptoObject);
        if (typeof prototype === 'object' && prototype !== null) {
            Reflect.deleteProperty(prototype, 'randomUUID');
        }
    });
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

/** Buka builder edit (redirect ke show) dengan tanggal tutup terisi agar autosave sah. */
async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'updated_at' => now()]);`,
    ]);

    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

function fieldRows(page: Page): Locator {
    return page.locator('.fb-field-row');
}

function canvasForTitle(page: Page): Locator {
    return page
        .locator('section')
        .filter({ has: page.locator('#f-title') })
        .first();
}

/** Tambah field dari palette lewat pencarian + drag (jalur desktop). */
async function addPaletteField(page: Page, searchTerm: string, label: string): Promise<Locator> {
    await page.getByPlaceholder('Cari komponen…').fill(searchTerm);
    await page.getByText(label, { exact: true }).first().dragTo(canvasForTitle(page));

    const row = fieldRows(page).last();
    await expect(row).toBeVisible();

    return row;
}

test.describe('builder tanpa crypto.randomUUID (HTTP LAN)', () => {
    test('tambah field, duplikat, tambah bagian, dan tambah opsi tetap membuat id valid', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        test.setTimeout(90_000);

        const pageErrors: string[] = [];
        const cryptoConsoleErrors: string[] = [];

        page.on('pageerror', (error) => pageErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error' && /TypeError|randomUUID|crypto/i.test(message.text())) {
                cryptoConsoleErrors.push(message.text());
            }
        });

        await disableRandomUuid(page);
        const payloadLog = captureFieldsPayloads(page);
        await openBuilder(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        // Bukti init script benar-benar berlaku sebelum app boot.
        const randomUuidType = await page.evaluate((): string => typeof globalThis.crypto.randomUUID);
        expect(randomUuidType, 'crypto.randomUUID harus tidak tersedia di halaman').toBe('undefined');

        await expect(fieldRows(page)).toHaveCount(1);

        // 1. Field baru dari palette: `createFormBuilderField` membuat id + nama field.
        await addPaletteField(page, 'Short Text', 'Short Text');
        await expect(fieldRows(page)).toHaveCount(2);

        // 2. Duplikat field: `createFormBuilderFieldList` membuat id salinan baru.
        await fieldRows(page).last().getByRole('button', { name: 'Gandakan field' }).click();
        await expect(fieldRows(page)).toHaveCount(3);

        // 3. Section baru: `useFormBuilderWorkspace.addSection` membuat id section.
        await page.getByRole('button', { name: /tambah bagian setelah ini/i }).click();
        await expect(page.getByText('Bagian 2', { exact: true })).toBeVisible();

        // 4. Dropdown + opsi: field baru, opsi default factory, lalu opsi tambahan FieldEditor.
        await addPaletteField(page, 'Dropdown', 'Dropdown');
        await fieldRows(page).last().getByRole('button', { name: 'Kelola opsi' }).click();
        await page.getByRole('button', { name: 'Tambah opsi' }).click();

        // Payload final harus memuat 4 field dan dropdown dengan 2 opsi.
        await expect
            .poll(
                () => findFieldsPayload(payloadLog, (fields) => fields.length === 4 && hasTwoOptionDropdown(fields)),
                {
                    timeout: 30_000,
                }
            )
            .not.toBeNull();

        const finalFields = findFieldsPayload(
            payloadLog,
            (fields) => fields.length === 4 && hasTwoOptionDropdown(fields)
        );

        if (finalFields === null) throw new Error('payload fields final tidak ditemukan');

        expect(pageErrors, 'tidak ada error runtime di halaman').toEqual([]);
        expect(cryptoConsoleErrors, 'tidak ada console error crypto/TypeError').toEqual([]);

        // Setiap field punya id + nama teknis non-kosong dan id unik.
        for (const field of finalFields) {
            expect(field.id.length, `id field "${field.label}" tidak boleh kosong`).toBeGreaterThan(0);
            expect(field.name.length, `name field "${field.label}" tidak boleh kosong`).toBeGreaterThan(0);
        }
        expect(new Set(finalFields.map((field) => field.id)).size).toBe(4);

        const dropdown = finalFields.find((field) => field.metadata?.builderType === 'dropdown');

        if (dropdown === undefined) throw new Error('field dropdown tidak ada di payload');

        // Opsi dropdown (factory + tambahan) tersimpan di metadata.optionChoices.
        const optionIds = (dropdown.metadata?.optionChoices ?? []).map((option) => option.id);

        expect(optionIds.length).toBeGreaterThanOrEqual(2);
        for (const optionId of optionIds) {
            expect(optionId.length, 'id opsi dropdown tidak boleh kosong').toBeGreaterThan(0);
        }
        expect(new Set(optionIds).size).toBe(optionIds.length);

        // Field dropdown ditambahkan setelah section baru: sectionId membuktikan id section terisi.
        expect(dropdown.metadata?.sectionId ?? '', 'sectionId field baru tidak boleh kosong').not.toBe('');
    });
});

function hasTwoOptionDropdown(fields: IBackendFieldPayload[]): boolean {
    return fields.some(
        (field) => field.metadata?.builderType === 'dropdown' && (field.metadata.optionChoices ?? []).length >= 2
    );
}
