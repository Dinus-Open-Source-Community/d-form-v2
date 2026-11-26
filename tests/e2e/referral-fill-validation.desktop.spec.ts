import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { claimEventForAdmin } from './support/eventAdmin';
import { expect, test } from './support/fixtures';

test.use({ fixtureFormCount: 1, fixtureFieldCount: 3, hasTouch: false });

const REFERRAL_LABEL = 'Kode referral (opsional)';

/** Form siap diisi: tanggal tutup terisi, referral aktif, dan satu kode valid. */
function prepareReferralForm(formId: string, code: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${formId}')->update(['closed_at' => now()->addWeek(), 'referral_enabled' => true, 'updated_at' => now()]);` +
            `Illuminate\\Support\\Facades\\DB::table('referral_codes')->insert(['id' => (string) \\Illuminate\\Support\\Str::uuid(), 'form_id' => '${formId}', 'code' => '${code}', 'prefix' => 'E2E', 'body' => 'REF', 'generation_mode' => 'manual', 'created_by' => null, 'assigned_user_id' => null, 'attribution_name' => 'E2E', 'usage_limit' => 10, 'usage_count' => 0, 'starts_at' => null, 'expires_at' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);`,
    ]);
}

async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
}

test.describe('Halaman isi: blok kode referral', () => {
    test('blok di akhir form dan validasi live mengikuti debounce', async ({ page, fixtureEvent, fixtureForm }) => {
        const formId = fixtureForm.primary.formId;
        const code = `E2E-${String(Date.now()).slice(-8)}`;

        claimEventForAdmin(fixtureEvent.eventId);
        prepareReferralForm(formId, code);

        await page.goto(`/joined/${fixtureEvent.eventId}/forms/${formId}/fill`, {
            waitUntil: 'domcontentloaded',
        });
        await waitForHydration(page);

        const referralLabel = page.getByText(REFERRAL_LABEL, { exact: false });
        await expect(referralLabel).toBeVisible();

        // Default `end`: blok berada setelah seluruh field form.
        const layout = await page.evaluate((labelText: string): { referral: number; fieldTops: number[] } => {
            const labels = Array.from(document.querySelectorAll('label'));
            const referral = labels.find((element) => (element.textContent ?? '').includes(labelText));
            const fieldTops = labels
                .filter((element) => !(element.textContent ?? '').includes(labelText))
                .map((element) => Math.round(element.getBoundingClientRect().top))
                .filter((top) => top > 0);

            return {
                referral: referral === undefined ? -1 : Math.round(referral.getBoundingClientRect().top),
                fieldTops,
            };
        }, REFERRAL_LABEL);

        expect(layout.fieldTops.length).toBeGreaterThan(0);
        expect(layout.referral).toBeGreaterThan(Math.max(...layout.fieldTops));

        const input = page.locator('input[autocomplete="off"][spellcheck="false"]');
        const checks: string[] = [];

        page.on('request', (request) => {
            if (request.url().includes('referral-code/check')) checks.push(request.postData() ?? '');
        });

        // Beberapa ketikan cepat hanya menghasilkan satu request setelah debounce 500 ms.
        await input.click();
        await input.type('TIDAKADA', { delay: 40 });
        await expect(page.getByText('Kode referral tidak ditemukan.')).toBeVisible({ timeout: 5_000 });
        expect(checks.length).toBe(1);

        await expect(input).toHaveClass(/border-destructive/);

        await input.fill('');
        await input.type(code, { delay: 30 });
        await expect(page.getByText('Kode referral valid.')).toBeVisible({ timeout: 5_000 });
        await expect(input).toHaveClass(/border-success/);
    });
});
