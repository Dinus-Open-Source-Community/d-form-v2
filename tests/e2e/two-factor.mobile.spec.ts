import type { Page } from '@playwright/test';
import { runArtisan } from './support/artisan';
import { expect, test } from './support/fixtures';
import { createRunId } from './support/marker';

/**
 * Tantangan PIN pada layar sempit: enam kotak, target sentuh minimal 44px, tanpa
 * overflow horizontal pada 360px dan 412px, serta tempelan 6 digit yang tidak
 * mengirim apa pun sebelum pengguna menekan "Masuk".
 *
 * Akun + PIN disiapkan lewat tinker (setup) dan dihapus di `afterAll`.
 */
const runId = process.env.E2E_RUN_ID ?? createRunId();
const PASSWORD = 'Rahasia123';
const PIN = '246810';
const EMAIL_PREFIX = `e2e-pin-mobile-${runId.toLowerCase()}`;
const PIN_EMAIL = `${EMAIL_PREFIX}@example.test`;
const OVERFLOW_TOLERANCE_PX = 1;
const MIN_CONTROL_SIZE_PX = 44;

test.use({ storageState: { cookies: [], origins: [] } });
test.describe.configure({ timeout: 120_000 });

test.beforeAll(() => {
    const code = [
        `$user = \\App\\Models\\User::query()->create(['name' => 'E2E PIN Mobile', 'email' => '${PIN_EMAIL}', 'password' => '${PASSWORD}', 'email_verified_at' => now()]);`,
        `$user->syncRoles(['member']);`,
        `app(\\App\\Services\\Auth\\TwoFactorPinService::class)->enable($user, '${PIN}');`,
    ].join(' ');

    runArtisan(code);
});

test.afterAll(() => {
    runArtisan(
        `\\App\\Models\\User::withTrashed()->where('email', 'like', '${EMAIL_PREFIX}%')->forceDelete();`
    );
});

/** Login lewat form lalu tunggu halaman tantangan PIN tampil. */
async function openChallenge(page: Page): Promise<void> {
    await page.goto('/auth/login');
    await expect(page.locator('#login-email')).toBeVisible({ timeout: 20_000 });
    await page.locator('#login-email').fill(PIN_EMAIL);
    await page.locator('#login-password').fill(PASSWORD);

    const loginForm = page.locator('form').filter({ has: page.locator('#login-email') });
    await loginForm.getByRole('button', { name: /masuk/i }).click();

    await expect(page).toHaveURL(/\/auth\/two-factor/, { timeout: 30_000 });
    await expect(page.getByTestId('two-factor-page')).toBeVisible();
}

/** `scrollWidth - clientWidth` dokumen: > 0 berarti ada overflow horizontal. */
function documentOverflow(page: Page): Promise<number> {
    return page.evaluate((): number => {
        const scroller = document.scrollingElement ?? document.documentElement;

        return scroller.scrollWidth - scroller.clientWidth;
    });
}

/** `scrollWidth - clientWidth` kontainer halaman tantangan. */
function challengeOverflow(page: Page): Promise<number> {
    return page.evaluate((): number => {
        const container = document.querySelector('[data-testid="two-factor-page"]');

        if (container === null) return -1;

        return container.scrollWidth - container.clientWidth;
    });
}

/** Tempel teks ke kotak pertama memakai event paste (clipboard OS tidak tersedia di headless). */
async function pasteIntoFirstBox(page: Page, value: string): Promise<void> {
    await page.evaluate((text: string) => {
        const input = document.querySelector('[data-testid="two-factor-pin"] input[aria-label="Digit 1 dari 6"]');

        if (!(input instanceof HTMLInputElement)) throw new Error('Kotak PIN pertama tidak ditemukan.');

        const data = new DataTransfer();
        data.setData('text/plain', text);
        input.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true }));
    }, value);
}

test.describe('tantangan PIN di layar sempit', () => {
    test('enam kotak PIN, kontrol minimal 44px, dan tanpa overflow pada 360/412', async ({ page }) => {
        await openChallenge(page);

        const boxes = page.getByTestId('two-factor-pin').getByRole('textbox');
        await expect(boxes).toHaveCount(6);
        await expect(boxes.first()).toHaveAccessibleName('Digit 1 dari 6');

        for (const width of [360, 412]) {
            await page.setViewportSize({ width, height: 800 });

            await expect.poll(() => documentOverflow(page), { timeout: 10_000 }).toBeLessThanOrEqual(OVERFLOW_TOLERANCE_PX);
            await expect.poll(() => challengeOverflow(page), { timeout: 10_000 }).toBeLessThanOrEqual(OVERFLOW_TOLERANCE_PX);

            const controls = page.getByTestId('two-factor-page').locator('input, button, a');
            // Enam kotak PIN + tombol Masuk + tombol "Lupa PIN?".
            await expect(controls).toHaveCount(8);

            const controlCount = await controls.count();

            for (let index = 0; index < controlCount; index += 1) {
                const box = await controls.nth(index).boundingBox();

                if (box === null) throw new Error(`Kontrol ke-${index + 1} tidak punya kotak render.`);

                const label = (await controls.nth(index).getAttribute('aria-label')) ?? `kontrol ${index + 1}`;

                expect(box.height, `${label} tinggi minimal 44px pada ${width}px`).toBeGreaterThanOrEqual(
                    MIN_CONTROL_SIZE_PX - 0.5
                );
                expect(box.width, `${label} lebar minimal 44px pada ${width}px`).toBeGreaterThanOrEqual(
                    MIN_CONTROL_SIZE_PX - 0.5
                );
            }
        }
    });

    test('tempel 6 digit mengisi kotak tanpa auto-submit sebelum "Masuk" ditekan', async ({ page }) => {
        await openChallenge(page);

        const submissions: string[] = [];
        page.on('request', (request) => {
            if (request.method() === 'POST' && request.url().endsWith('/auth/two-factor')) {
                submissions.push(request.url());
            }
        });

        const boxes = page.getByTestId('two-factor-pin').getByRole('textbox');

        await boxes.first().click();
        await pasteIntoFirstBox(page, PIN.slice(0, 5));

        for (const [index, digit] of [...PIN.slice(0, 5)].entries()) {
            await expect(boxes.nth(index)).toHaveValue(digit);
        }

        await expect(boxes.nth(5)).toHaveValue('');
        await expect(page.getByTestId('two-factor-submit')).toBeDisabled();

        // Digit terakhir diketik manual: kode lengkap tetap tidak terkirim sendiri.
        await page.keyboard.type(PIN.slice(5));

        for (const [index, digit] of [...PIN].entries()) {
            await expect(boxes.nth(index)).toHaveValue(digit);
        }

        await expect(page.getByTestId('two-factor-submit')).toBeEnabled();
        expect(submissions).toHaveLength(0);
        await expect(page).toHaveURL(/\/auth\/two-factor$/);

        await page.getByTestId('two-factor-submit').click();

        await expect.poll(() => submissions.length, { timeout: 30_000 }).toBe(1);
        await expect(page).not.toHaveURL(/\/auth\/two-factor$/, { timeout: 40_000 });
    });
});
