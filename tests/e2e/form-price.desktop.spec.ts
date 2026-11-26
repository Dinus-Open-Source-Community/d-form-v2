import type { Locator, Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { claimEventForAdmin } from './support/eventAdmin';
import { closeFieldDrawer } from './support/builderFieldCard';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1, hasTouch: false });
test.describe.configure({ timeout: 60_000 });

const PRICE_LABEL = 'Payment';
const BASE_PRICE_TEXT = 'Rp 95.000';
const DISCOUNTED_PRICE_TEXT = 'Rp 76.000';
const DISCOUNT_LABEL = 'Diskon 20%';
const DISCOUNT_AMOUNT = '-Rp 19.000';
const CODE = 'E2E-DISC';
const CLIPBOARD_KEY = 'e2e-copied-referral-code';

/** Salin memakai stub: konteks aman + clipboard palsu yang menulis ke localStorage. */
async function stubClipboard(page: Page): Promise<void> {
    await page.addInitScript((key: string): void => {
        Object.defineProperty(window, 'isSecureContext', { configurable: true, value: true });
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText: async (text: string): Promise<void> => {
                    localStorage.setItem(key, text);
                },
            },
        });
    }, CLIPBOARD_KEY);
}

/** Form siap diisi: satu field harga, referral aktif, dan kode dengan diskon 20 persen. */
function preparePricedForm(formId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'referral_enabled' => true, 'updated_at' => now()]);` +
            `Illuminate\\Support\\Facades\\DB::table('form_fields')->insert(['id' => (string) \\Illuminate\\Support\\Str::uuid(), 'form_id' => '${formId}', 'input_type' => 'input', 'label' => '${PRICE_LABEL}', 'description' => null, 'name' => 'payment_e2e', 'order' => 0, 'metadata' => json_encode(['builderType' => 'price', 'type' => 'text', 'price' => 95000]), 'created_at' => now(), 'updated_at' => now()]);` +
            `Illuminate\\Support\\Facades\\DB::table('referral_codes')->insert(['id' => (string) \\Illuminate\\Support\\Str::uuid(), 'form_id' => '${formId}', 'code' => '${CODE}', 'prefix' => 'E2E', 'body' => 'DISC', 'generation_mode' => 'manual', 'created_by' => null, 'assigned_user_id' => null, 'attribution_name' => 'E2E', 'usage_limit' => 10, 'usage_count' => 0, 'starts_at' => null, 'expires_at' => null, 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 20, 'created_at' => now(), 'updated_at' => now()]);`,
    ]);
}

/** Aktifkan referral lewat DB agar test tidak memakai kuota throttle tulis saat setup. */
function enableReferral(formId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['referral_enabled' => true, 'updated_at' => now()]);`,
    ]);
}

async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 30_000 }
    );
}

async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/${formId}/edit`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
    await page.getByTestId('form-builder-canvas').scrollIntoViewIfNeeded();
}

/** Buka drawer lewat nama kartu, bukan indeks, karena urutan drop tidak stabil. */
async function openDrawerByName(page: Page, name: string): Promise<Locator> {
    await page.getByRole('button', { name }).click();

    const sheet = page.getByRole('dialog');
    await sheet.waitFor({ state: 'visible' });

    return sheet;
}

test.describe('Harga form dan diskon referral', () => {
    // Kuota `form-referral-check` (60/menit per IP) tersimpan di cache; reset agar
    // beberapa run berurutan tidak menghasilkan 429 dan UI menampilkan gagal cek.
    test.beforeEach(() => {
        runArtisanCommand(['cache:clear']);
    });

    test('halaman isi menampilkan harga dasar lalu harga diskon setelah kode valid', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const formId = fixtureForm.primary.formId;

        claimEventForAdmin(fixtureEvent.eventId);
        preparePricedForm(formId);

        await page.goto(`/joined/${fixtureEvent.eventId}/forms/${formId}/fill`, { waitUntil: 'domcontentloaded' });
        await waitForHydration(page);

        await expect(page.getByText(PRICE_LABEL, { exact: true })).toBeVisible();
        await expect(page.getByText(BASE_PRICE_TEXT)).toBeVisible();
        await expect(page.getByText(DISCOUNT_LABEL)).toHaveCount(0);

        const input = page.locator('input[autocomplete="off"][spellcheck="false"]');

        await input.fill(CODE.toLowerCase());
        await expect(page.getByText(DISCOUNTED_PRICE_TEXT)).toBeVisible({ timeout: 5_000 });
        await expect(page.getByText(DISCOUNT_LABEL)).toBeVisible();
        await expect(page.getByText(DISCOUNT_AMOUNT)).toBeVisible();
        await expect(page.getByText(BASE_PRICE_TEXT)).toBeVisible();

        await input.fill('E2E-ZZZZ');
        await expect(page.getByText('Kode referral tidak ditemukan.')).toBeVisible({ timeout: 5_000 });
        await expect(page.getByText(DISCOUNT_LABEL)).toHaveCount(0);
        await expect(page.getByText(BASE_PRICE_TEXT)).toBeVisible();

        await input.fill('');
        await expect(page.getByText(DISCOUNT_LABEL)).toHaveCount(0);
        await expect(page.getByText(BASE_PRICE_TEXT)).toBeVisible();
    });

    test('input diskon hanya menerima angka dan persen maksimal 100', async ({ page, fixtureEvent, fixtureForm }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        enableReferral(fixtureForm.primary.formId);

        const formPath = `/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}?tab=settings`;

        await page.goto(formPath, { waitUntil: 'domcontentloaded' });

        await page.getByRole('button', { name: 'Tambah kode' }).click();

        const sheet = page.getByRole('dialog');
        await sheet.waitFor({ state: 'visible' });

        const input = sheet.locator('#referral-discount-value');
        await expect(input).toHaveAttribute('type', 'text');
        await expect(input).toHaveAttribute('inputmode', 'numeric');
        await expect(sheet.getByText('Persen diskon')).toBeVisible();
        await expect(sheet.locator('[data-slot="input-group-addon"]').getByText('%')).toBeVisible();
        await expect(sheet.getByText(/Diskon bisa diubah|Nominal minimal/)).toHaveCount(0);

        await input.click();
        await input.pressSequentially('150');
        await expect(input).toHaveValue('100');

        await input.fill('');
        await input.pressSequentially('2a0');
        await expect(input).toHaveValue('20');

        await input.fill('');
        await input.pressSequentially('0');
        await expect(input).toHaveValue('');

        await sheet.getByRole('button', { name: 'Rupiah' }).click();
        await expect(sheet.getByText('Nominal diskon', { exact: true })).toBeVisible();
        await expect(sheet.getByText('Nominal diskon (Rp)')).toHaveCount(0);
        await expect(sheet.locator('[data-slot="input-group-addon"]').getByText('Rp')).toBeVisible();
        await input.fill('150');
        await expect(input).toHaveValue('150');
    });

    test('tombol salin kode menyalin, menampilkan toast, dan berubah ke centang', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        enableReferral(fixtureForm.primary.formId);
        await stubClipboard(page);

        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}?tab=settings`, {
            waitUntil: 'domcontentloaded',
        });

        await page.getByRole('button', { name: 'Tambah kode' }).click();

        const sheet = page.getByRole('dialog');
        await sheet.waitFor({ state: 'visible' });
        await sheet.locator('#referral-attribution-name').fill('E2E');
        await sheet.locator('#referral-prefix').fill('CODE');
        await sheet.getByRole('button', { name: 'Manual' }).click();
        await sheet.locator('#referral-body').fill('CPY1');
        await sheet.getByRole('button', { name: 'Simpan' }).click();

        await expect(page.getByText('CODE-CPY1', { exact: true })).toBeVisible();

        const copyButton = page.getByRole('button', { name: 'Salin kode CODE-CPY1' });
        await expect(copyButton).toHaveAttribute('data-copy-feedback', 'idle');

        await copyButton.click();

        await expect(copyButton).toHaveAttribute('data-copy-feedback', 'confirmed');
        await expect(page.locator('[data-sonner-toast]').filter({ hasText: 'Kode referral disalin.' })).toBeVisible();
        await expect
            .poll(() => page.evaluate((key: string): string | null => localStorage.getItem(key), CLIPBOARD_KEY))
            .toBe('CODE-CPY1');

        await expect(copyButton).toHaveAttribute('data-copy-feedback', 'idle', { timeout: 5_000 });
    });

    test('builder menyimpan harga pada field Price dan tidak menampilkannya di heading', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const formId = fixtureForm.primary.formId;

        claimEventForAdmin(fixtureEvent.eventId);
        await openBuilder(page, fixtureEvent.eventId, formId);

        const savedFields = page.waitForResponse(
            (response): boolean =>
                response.request().method() === 'POST' &&
                response.url().endsWith('/fields') &&
                (response.request().postData() ?? '').includes('"price":95000')
        );

        await page.getByRole('button', { name: /content/i }).click();

        // Heading tidak punya input harga lagi; semua harga berpusat di tipe Price.
        await page.getByText('Heading', { exact: true }).first().dragTo(page.getByTestId('form-builder-canvas'));
        await expect(page.getByTestId('field-card')).toHaveCount(2);

        const headingSheet = await openDrawerByName(page, 'Ubah field Heading');
        await expect(headingSheet.getByLabel('Harga (Rp)')).toHaveCount(0);
        await closeFieldDrawer(page);

        await page.getByText('Price', { exact: true }).first().dragTo(page.getByTestId('form-builder-canvas'));
        await expect(page.getByTestId('field-card')).toHaveCount(3);

        const sheet = await openDrawerByName(page, 'Ubah field Price');
        const priceInput = sheet.getByLabel('Harga (Rp)');

        await priceInput.fill('95000');
        await closeFieldDrawer(page);

        expect((await savedFields).status()).toBe(200);
        await expect(page.getByText('Tersimpan', { exact: true })).toBeVisible({ timeout: 30_000 });

        await page.reload({ waitUntil: 'domcontentloaded' });
        await waitForHydration(page);
        await page.getByTestId('form-builder-canvas').scrollIntoViewIfNeeded();

        const reopened = await openDrawerByName(page, 'Ubah field Price');

        await expect(reopened.getByLabel('Harga (Rp)')).toHaveValue('95000');
    });
});
