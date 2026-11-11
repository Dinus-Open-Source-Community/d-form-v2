import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { expect, test } from './support/fixtures';
import { parseTinkerJsonRecord, requireTinkerString } from './support/tinker';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1 });

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

/** Stel `metadata.maxLength` field langsung di DB (simulasi data lama di luar batas). */
function setFieldMaxLength(fieldId: string, value: number): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$meta = json_decode((string) \\Illuminate\\Support\\Facades\\DB::table('form_fields')->where('id', '${fieldId}')->value('metadata'), true) ?: []; $meta['maxLength'] = ${value}; \\Illuminate\\Support\\Facades\\DB::table('form_fields')->where('id', '${fieldId}')->update(['metadata' => json_encode($meta)]);`,
    ]);
}

/** Baca `metadata.maxLength` tersimpan. */
function readFieldMaxLength(fieldId: string): number | null {
    const raw = runArtisanCommand([
        'tinker',
        '--execute',
        `$meta = json_decode((string) \\Illuminate\\Support\\Facades\\DB::table('form_fields')->where('id', '${fieldId}')->value('metadata'), true) ?: []; echo json_encode($meta['maxLength'] ?? null);`,
    ]);
    const parsed: unknown = JSON.parse(raw);

    return typeof parsed === 'number' ? parsed : null;
}

test.describe('Maks. karakter: data lama di luar batas', () => {
    test('nilai 50000 ditandai error dan tidak diubah sampai user mengetik', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const fieldId = fixtureForm.primary.fieldIds[0] ?? '';

        expect(fieldId).not.toBe('');
        setFieldMaxLength(fieldId, 50_000);

        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
        await waitForHydration(page);

        // Palet kanvas: buka editor field (desktop memakai panel properti field).
        await page.getByRole('button', { name: 'Pengaturan field' }).first().click();

        const maxLength = page.getByPlaceholder('Tanpa batas');

        await expect(maxLength).toBeVisible();
        // Nilai mentah ditampilkan apa adanya (tidak di-clamp saat dibuka).
        await expect(maxLength).toHaveValue('50000');
        await expect(maxLength).toHaveAttribute('aria-invalid', 'true');
        await expect(page.getByText(/di luar batas/i)).toBeVisible();

        // Membuka drawer tidak mengubah metadata tersimpan.
        expect(readFieldMaxLength(fieldId)).toBe(50_000);

        // Setelah user mengetik, baru di-clamp ke batas tipe (short text 1000).
        await maxLength.fill('1234567');
        await expect(maxLength).toHaveValue('1000');
        await expect(maxLength).not.toHaveAttribute('aria-invalid', 'true');
        await expect.poll((): number | null => readFieldMaxLength(fieldId), { timeout: 20_000 }).toBe(1000);
    });

    test('metadata field tidak berubah hanya karena drawer dibuka', async ({ page, fixtureEvent, fixtureForm }) => {
        const fieldId = fixtureForm.primary.fieldIds[0] ?? '';

        // Fixture tidak menyimpan maxLength: membuka drawer tidak boleh menambahkannya.
        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
        await waitForHydration(page);

        await page.getByRole('button', { name: 'Pengaturan field' }).first().click();
        await expect(page.getByPlaceholder('Tanpa batas')).toHaveValue('');

        await page.keyboard.press('Escape');
        await page.waitForTimeout(1_000);

        expect(readFieldMaxLength(fieldId)).toBeNull();
    });
});

/** Pastikan helper tinker membaca record dengan benar (kontrak fixture). */
test('fixture field id terbaca', () => {
    const record = parseTinkerJsonRecord('{"id":"x"}', 'fixture');

    expect(requireTinkerString(record, 'id', 'fixture')).toBe('x');
});
