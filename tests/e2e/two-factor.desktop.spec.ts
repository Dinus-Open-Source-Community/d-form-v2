import type { Browser, BrowserContext, Page } from '@playwright/test';
import { runArtisan, runArtisanCommand } from './support/artisan';
import { loginE2eUser } from './support/authentication';
import { expect, test } from './support/fixtures';
import { createRunId } from './support/marker';
import { parseTinkerJsonRecord } from './support/tinker';

/**
 * PIN faktor kedua: aktivasi dari profil, gerbang setelah login ulang, PIN salah yang
 * tidak mengunci akun, PIN benar yang kembali ke URL semula, dan penonaktifan.
 *
 * Akun dibuat lewat tinker dengan email/ nama khusus run ini. Untuk test yang butuh
 * PIN sudah aktif, status itu disiapkan lewat service (setup, bukan perilaku yang diuji);
 * hanya test aktivasi yang menempuh dialog profil. Semua akun dihapus di `afterAll`.
 */
const runId = process.env.E2E_RUN_ID ?? createRunId();
const PASSWORD = 'Rahasia123';
const PIN = '135790';
const WRONG_PIN = '000001';
const EMAIL_PREFIX = `e2e-pin-${runId.toLowerCase()}-`;
const PASSWORD_FIELD = '#login-password';

test.use({ storageState: { cookies: [], origins: [] } });
test.describe.configure({ timeout: 120_000 });

/** String literal PHP di dalam kode tinker; argumen tidak melewati shell. */
function phpString(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

let userSequence = 0;

/** Buat user `member` terverifikasi emailnya; kembalikan alamat emailnya. */
function createPinUser(label: string): string {
    userSequence += 1;
    const email = `${EMAIL_PREFIX}${label}-${userSequence}@example.test`;
    const code = [
        `$user = \\App\\Models\\User::query()->create(['name' => ${phpString('E2E PIN')}, 'email' => ${phpString(email)}, 'password' => ${phpString(PASSWORD)}, 'email_verified_at' => now()]);`,
        `$user->syncRoles(['member']);`,
    ].join(' ');

    runArtisan(code);

    return email;
}

/** Siapkan PIN aktif lewat service (setup test, bukan alur yang diuji). */
function enablePin(email: string): void {
    const code = [
        `$user = \\App\\Models\\User::query()->where('email', ${phpString(email)})->firstOrFail();`,
        `app(\\App\\Services\\Auth\\TwoFactorPinService::class)->enable($user, ${phpString(PIN)});`,
    ].join(' ');

    runArtisan(code);
}

interface IPinCredentialSnapshot {
    present: boolean;
    pinHash: string | null;
    failedAttempts: number;
    lockedUntil: string | null;
}

/** Baca kredensial PIN tersimpan; dipakai hanya untuk membuktikan hash, bukan PIN terbaca. */
function readPinCredential(email: string): IPinCredentialSnapshot {
    const code = [
        `$user = \\App\\Models\\User::query()->where('email', ${phpString(email)})->first();`,
        `$row = $user === null ? null : \\Illuminate\\Support\\Facades\\DB::table('user_pin_credentials')->where('user_id', $user->id)->first();`,
        `echo json_encode(['present' => $row !== null, 'pin_hash' => $row?->pin_hash, 'failed_attempts' => (int) ($row?->failed_attempts ?? 0), 'locked_until' => $row?->locked_until]);`,
    ].join(' ');
    const record = parseTinkerJsonRecord(runArtisanCommand(['tinker', '--execute', code]), 'kredensial PIN');

    return {
        present: record.present === true,
        pinHash: typeof record.pin_hash === 'string' ? record.pin_hash : null,
        failedAttempts: typeof record.failed_attempts === 'number' ? record.failed_attempts : 0,
        lockedUntil: typeof record.locked_until === 'string' ? record.locked_until : null,
    };
}

/** Login lewat form login seperti pengguna; dipakai setelah PIN aktif. */
async function signInWithPassword(page: Page, email: string): Promise<void> {
    await page.goto('/auth/login');
    await expect(page.locator('#login-email')).toBeVisible({ timeout: 20_000 });
    await page.locator('#login-email').fill(email);
    await page.locator(PASSWORD_FIELD).fill(PASSWORD);

    const loginForm = page.locator('form').filter({ has: page.locator('#login-email') });
    await loginForm.getByRole('button', { name: /masuk/i }).click();
}

/** Keluar lewat topbar lalu buktikan sesi benar-benar berakhir. */
async function signOut(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Keluar' }).click();

    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/auth\/login/, { timeout: 20_000 });
}

/** Buka halaman profil dan tunggu konten ter-hydrate. */
async function openProfile(page: Page): Promise<void> {
    await page.goto('/profile');
    await expect(page.getByTestId('profile-page')).toBeVisible({ timeout: 20_000 });
}

/** Isi satu grup kotak PIN lalu pastikan setiap digit masuk ke kotak yang benar. */
async function fillPinGroup(page: Page, testId: string, pin: string): Promise<void> {
    const boxes = page.getByTestId(testId).getByRole('textbox');

    await expect(boxes).toHaveCount(6);
    await boxes.first().click();
    await page.keyboard.type(pin);

    for (const [index, digit] of [...pin].entries()) {
        await expect(boxes.nth(index)).toHaveValue(digit);
    }
}

/** Isi PIN pada halaman tantangan lalu tekan "Masuk". */
async function submitChallengePin(page: Page, pin: string): Promise<void> {
    await fillPinGroup(page, 'two-factor-pin', pin);
    await page.getByTestId('two-factor-submit').click();
}

/** Login lewat form lalu pastikan halaman tantangan benar-benar tampil sebelum diisi. */
async function reachChallenge(page: Page, email: string): Promise<void> {
    await signInWithPassword(page, email);
    await expect(page).toHaveURL(/\/auth\/two-factor/, { timeout: 30_000 });
    await expect(page.getByTestId('two-factor-page')).toBeVisible({ timeout: 30_000 });
}

/** Context baru dengan sesi user (login API) untuk test yang belum butuh gerbang PIN. */
async function openAsUser(browser: Browser, email: string): Promise<{ context: BrowserContext; page: Page }> {
    const storageState = await loginE2eUser(email, PASSWORD);
    const context = await browser.newContext({ storageState, viewport: { width: 1440, height: 900 } });

    return { context, page: await context.newPage() };
}

test.afterAll(() => {
    runArtisan(
        `\\App\\Models\\User::withTrashed()->where('email', 'like', ${phpString(`${EMAIL_PREFIX}%`)})->forceDelete();`
    );
});

test.describe('PIN faktor kedua', () => {
    test('aktivasi dari profil menyimpan hash dan menyalakan sakelar', async ({ browser }) => {
        const email = createPinUser('aktifkan');
        const { context, page } = await openAsUser(browser, email);

        try {
            await openProfile(page);

            const toggle = page.getByTestId('profile-pin-toggle');
            await expect(toggle).toHaveAttribute('aria-checked', 'false');

            await toggle.click();

            const dialog = page.getByRole('alertdialog');
            await expect(dialog).toBeVisible();
            await expect(dialog.getByRole('heading', { name: 'Aktifkan PIN' })).toBeVisible();

            await page.getByTestId('profile-pin-password').fill(PASSWORD);
            await fillPinGroup(page, 'profile-pin-input', PIN);
            await fillPinGroup(page, 'profile-pin-confirm', PIN);

            const submit = page.getByTestId('profile-pin-submit');
            await expect(submit).toBeEnabled();
            await submit.click();

            await expect(dialog).toBeHidden({ timeout: 20_000 });
            await expect(toggle).toHaveAttribute('aria-checked', 'true');

            const credential = readPinCredential(email);
            expect(credential.present).toBe(true);
            expect(credential.pinHash).not.toBeNull();
            expect(credential.pinHash).not.toBe(PIN);
            expect(credential.pinHash ?? '').toMatch(/^\$2[aby]\$/);
        } finally {
            await context.close();
        }
    });

    test('login ulang menahan di halaman tantangan dan dashboard tidak bisa dibuka', async ({ browser }) => {
        const email = createPinUser('gerbang');
        enablePin(email);

        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await context.newPage();

        try {
            await reachChallenge(page, email);

            await expect(page.getByTestId('two-factor-pin').getByRole('textbox')).toHaveCount(6);

            // Sebelum PIN dimasukkan, route ber-auth tetap dialihkan kembali ke tantangan.
            await page.goto('/dashboard');
            await expect(page).toHaveURL(/\/auth\/two-factor/);
            await expect(page.getByTestId('two-factor-page')).toBeVisible();
        } finally {
            await context.close();
        }
    });

    test('PIN salah dua kali menampilkan sisa percobaan tanpa mengunci akun, lalu PIN benar lanjut', async ({
        browser,
    }) => {
        const email = createPinUser('salah');
        enablePin(email);

        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await context.newPage();

        try {
            await reachChallenge(page, email);

            // URL yang diminta sebelum gerbang harus tetap dipakai setelah PIN benar.
            await page.goto('/profile');
            await expect(page).toHaveURL(/\/auth\/two-factor/);

            await submitChallengePin(page, WRONG_PIN);
            await expect(page.getByRole('alert')).toContainText(/PIN yang Anda masukkan salah/i, {
                timeout: 20_000,
            });
            await expect(page.getByText(/Sisa percobaan: 4/)).toBeVisible();
            await expect(page.getByTestId('two-factor-lock')).toHaveCount(0);
            await expect(page.getByTestId('two-factor-pin').getByRole('textbox').first()).toHaveValue('');

            await submitChallengePin(page, WRONG_PIN);
            await expect(page.getByRole('alert')).toContainText(/PIN yang Anda masukkan salah/i, {
                timeout: 20_000,
            });
            await expect(page.getByText(/Sisa percobaan: 3/)).toBeVisible();
            await expect(page.getByTestId('two-factor-lock')).toHaveCount(0);
            // PIN ditolak mengosongkan kotak, jadi tombol kirim memang nonaktif...
            await expect(page.getByTestId('two-factor-submit')).toBeDisabled();
            // ...tetapi akun tidak terkunci: keenam kotak masih bisa diisi.
            await expect(page.getByTestId('two-factor-pin').getByRole('textbox').first()).toBeEnabled();

            const afterFailures = readPinCredential(email);
            expect(afterFailures.failedAttempts).toBe(2);
            expect(afterFailures.lockedUntil).toBeNull();

            await submitChallengePin(page, PIN);

            // `url.intended` milik permintaan `/profile` dipakai, bukan selalu dashboard.
            await expect(page).toHaveURL(/\/profile$/, { timeout: 40_000 });
            await expect(page.getByTestId('profile-page')).toBeVisible({ timeout: 20_000 });

            await page.goto('/dashboard');
            await expect(page).toHaveURL(/\/dashboard/);
            await expect(page.getByTestId('email-verification-banner')).toHaveCount(0);

            const afterSuccess = readPinCredential(email);
            expect(afterSuccess.failedAttempts).toBe(0);
            expect(afterSuccess.lockedUntil).toBeNull();
        } finally {
            await context.close();
        }
    });

    test('nonaktifkan PIN menghapus kredensial dan login berikutnya tidak meminta PIN', async ({ browser }) => {
        const email = createPinUser('nonaktifkan');
        enablePin(email);

        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await context.newPage();

        try {
            await reachChallenge(page, email);
            await submitChallengePin(page, PIN);
            await expect(page).toHaveURL(/\/dashboard/, { timeout: 40_000 });

            await openProfile(page);

            const toggle = page.getByTestId('profile-pin-toggle');
            await expect(toggle).toHaveAttribute('aria-checked', 'true');

            await toggle.click();

            const dialog = page.getByRole('alertdialog');
            await expect(dialog).toBeVisible();
            await expect(dialog.getByRole('heading', { name: 'Nonaktifkan PIN' })).toBeVisible();

            await page.getByTestId('profile-pin-password').fill(PASSWORD);
            await fillPinGroup(page, 'profile-pin-current', PIN);
            await page.getByTestId('profile-pin-submit').click();

            await expect(dialog).toBeHidden({ timeout: 20_000 });
            await expect(toggle).toHaveAttribute('aria-checked', 'false');
            expect(readPinCredential(email).present).toBe(false);

            await signOut(page);
            await signInWithPassword(page, email);

            await expect(page).toHaveURL(/\/dashboard/, { timeout: 30_000 });
            await expect(page.getByTestId('two-factor-page')).toHaveCount(0);
        } finally {
            await context.close();
        }
    });
});
