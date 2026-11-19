import type { Browser, BrowserContext, Locator, Page } from '@playwright/test';
import { runArtisan, runArtisanCommand } from './support/artisan';
import { loginE2eUser } from './support/authentication';
import { expect, test } from './support/fixtures';
import { createRunId } from './support/marker';
import { parseTinkerJsonRecord } from './support/tinker';

/**
 * Verifikasi email non-blocking: banner di shell dashboard, halaman `/email/verify`,
 * cooldown kirim ulang, kode dari command lokal, dan penolakan kode salah.
 *
 * Setiap test memakai akun sendiri karena service OTP membatasi 3 kirim + cooldown
 * 60 detik per user; akun dipisah supaya test tidak saling memakan kuota. Akun dibuat
 * lewat tinker dan dihapus di `afterAll` (cascade membersihkan baris tantangan).
 */
const runId = process.env.E2E_RUN_ID ?? createRunId();
const PASSWORD = 'Rahasia123';
const EMAIL_PREFIX = `e2e-verify-${runId.toLowerCase()}-`;

test.use({ storageState: { cookies: [], origins: [] } });
test.describe.configure({ timeout: 90_000 });

/** String literal PHP di dalam kode tinker; argumen tidak melewati shell. */
function phpString(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

let userSequence = 0;

/** Buat user `member` yang emailnya belum diverifikasi; kembalikan alamat emailnya. */
function createUnverifiedUser(label: string): string {
    userSequence += 1;
    const email = `${EMAIL_PREFIX}${label}-${userSequence}@example.test`;
    const code = [
        `$user = \\App\\Models\\User::query()->create(['name' => ${phpString('E2E Verify')}, 'email' => ${phpString(email)}, 'password' => ${phpString(PASSWORD)}, 'email_verified_at' => null]);`,
        `$user->syncRoles(['member']);`,
    ].join(' ');

    runArtisan(code);

    return email;
}

/**
 * Login API untuk user ini lalu buka browser context berisi sesi tersebut.
 * Konteks ditutup pemanggil agar tidak ada sesi yang tertinggal antar test.
 */
async function openAsUser(browser: Browser, email: string): Promise<{ context: BrowserContext; page: Page }> {
    const storageState = await loginE2eUser(email, PASSWORD);
    const context = await browser.newContext({ storageState, viewport: { width: 1440, height: 900 } });

    return { context, page: await context.newPage() };
}

/** Enam kotak kode halaman verifikasi. */
function verificationBoxes(page: Page): Locator {
    return page.getByTestId('verify-email-code').getByRole('textbox');
}

/** Buka halaman verifikasi dan pastikan halaman sudah ter-hydrate. */
async function openVerificationPage(page: Page): Promise<void> {
    await page.goto('/email/verify');
    await expect(page.getByTestId('verify-email-page')).toBeVisible({ timeout: 20_000 });
}

/** Kirim kode pertama lalu tunggu props baru memasang enam kotak kode. */
async function sendFirstCode(page: Page): Promise<void> {
    await page.getByTestId('verify-email-resend').click();
    await expect(verificationBoxes(page)).toHaveCount(6, { timeout: 30_000 });
}

/** Isi kode lewat keyboard: kotak pertama lalu auto-advance seperti pemakaian nyata. */
async function typeCode(page: Page, code: string): Promise<void> {
    await verificationBoxes(page).first().click();
    await page.keyboard.type(code);
}

/** Kode aktif dari command lokal; string kosong selama job pengirim belum jalan. */
function tryReadActiveCode(email: string): string {
    try {
        return runArtisanCommand(['email:verification-code', email]).trim();
    } catch {
        return '';
    }
}

/** Tunggu job antrean menulis kode ke cache lalu kembalikan kodenya. */
async function waitForActiveCode(email: string): Promise<string> {
    let latest = '';

    await expect
        .poll(
            () => {
                latest = tryReadActiveCode(email);

                return /^\d{6}$/.test(latest);
            },
            { timeout: 30_000, intervals: [500, 1_000, 2_000] }
        )
        .toBe(true);

    return latest;
}

/** Kode salah yang dijamin berbeda dari kode asli: digit pertama diganti. */
function toWrongCode(code: string): string {
    return (code.startsWith('9') ? '1' : '9') + code.slice(1);
}

interface IEmailVerificationRowState {
    verifiedAt: string | null;
    challengeRows: number;
}

/** Baca `email_verified_at` dan sisa baris tantangan milik user lewat tinker. */
function readVerificationState(email: string): IEmailVerificationRowState {
    const code = [
        `$user = \\App\\Models\\User::query()->where('email', ${phpString(email)})->first();`,
        `echo json_encode(['found' => $user !== null, 'verified_at' => $user?->email_verified_at?->toIso8601String(), 'challenge_rows' => $user === null ? -1 : \\App\\Models\\EmailVerificationCode::query()->where('user_id', $user->id)->count()]);`,
    ].join(' ');
    const record = parseTinkerJsonRecord(runArtisanCommand(['tinker', '--execute', code]), 'status verifikasi email');
    const verifiedAt = record.verified_at;
    const challengeRows = record.challenge_rows;

    if (record.found !== true) {
        throw new Error(`tinker tidak menemukan user ${email}.`);
    }

    if (verifiedAt !== null && typeof verifiedAt !== 'string') {
        throw new Error('tinker: field "verified_at" harus string atau null.');
    }

    if (typeof challengeRows !== 'number') {
        throw new Error('tinker: field "challenge_rows" harus angka.');
    }

    return { verifiedAt, challengeRows };
}

test.afterAll(() => {
    runArtisan(
        `\\App\\Models\\User::withTrashed()->where('email', 'like', ${phpString(`${EMAIL_PREFIX}%`)})->forceDelete();`
    );
});

test.describe('verifikasi email akun', () => {
    test('banner tampil di portal peserta dan profil, "Ingatkan nanti" hanya berlaku untuk sesi itu', async ({
        browser,
    }) => {
        const email = createUnverifiedUser('banner');
        const otherEmail = createUnverifiedUser('banner-lain');
        const current = await openAsUser(browser, email);
        const other = await openAsUser(browser, otherEmail);

        try {
            const banner = current.page.getByTestId('email-verification-banner');

            await current.page.goto('/joined');
            await expect(banner).toBeVisible({ timeout: 20_000 });

            await current.page.goto('/profile');
            await expect(current.page.getByTestId('profile-page')).toBeVisible({ timeout: 20_000 });
            await expect(banner).toBeVisible();

            await current.page.getByTestId('email-verification-dismiss').click();
            await expect(banner).toHaveCount(0);

            await current.page.reload();
            await expect(current.page.getByTestId('profile-page')).toBeVisible({ timeout: 20_000 });
            await expect(banner).toHaveCount(0);

            // Akun lain pada sesi lain tetap melihat banner: penyingkiran bukan sifat akun.
            await other.page.goto('/joined');
            await expect(other.page.getByTestId('email-verification-banner')).toBeVisible({ timeout: 20_000 });
        } finally {
            await current.context.close();
            await other.context.close();
        }
    });

    test('tombol banner membuka halaman verifikasi dengan email akun', async ({ browser }) => {
        const email = createUnverifiedUser('cta');
        const { context, page } = await openAsUser(browser, email);

        try {
            await page.goto('/joined');
            await page.getByTestId('email-verification-cta').click();

            await expect(page).toHaveURL(/\/email\/verify$/, { timeout: 20_000 });
            await expect(page.getByTestId('verify-email-page')).toBeVisible();
            await expect(page.getByTestId('verify-email-email')).toHaveText(email);
        } finally {
            await context.close();
        }
    });

    test('kirim kode memasang cooldown dan tombol kirim ulang tidak mengirim dua kali', async ({ browser }) => {
        const email = createUnverifiedUser('cooldown');
        const { context, page } = await openAsUser(browser, email);
        const sendRequests: string[] = [];

        page.on('request', (request) => {
            if (request.method() === 'POST' && request.url().endsWith('/email/verify/send')) {
                sendRequests.push(request.url());
            }
        });

        try {
            await openVerificationPage(page);

            const sendButton = page.getByTestId('verify-email-resend');
            await expect(sendButton).toBeEnabled();
            await expect(sendButton).toContainText(/kirim kode verifikasi/i);

            await sendButton.click();
            await expect(verificationBoxes(page)).toHaveCount(6, { timeout: 30_000 });

            // Setelah kirim: tombol berubah menjadi "Kirim ulang" dan terkunci cooldown.
            const resendButton = page.getByTestId('verify-email-resend');
            await expect(resendButton).toContainText(/kirim ulang/i);
            await expect(resendButton).toBeDisabled();

            // Copy produk menaruh jam di dalam tanda kurung: "Kirim ulang (00:52)".
            const resendLine = page
                .getByTestId('verify-email-page')
                .locator('p')
                .filter({ has: page.getByTestId('verify-email-resend') });
            await expect(resendLine).toContainText(/kirim ulang\s+\(\d{2}:\d{2}\)/i);

            const countdown = resendLine.locator('span');
            const before = (await countdown.innerText()).trim();
            expect(before).toMatch(/^\(\d{2}:\d{2}\)$/);

            // Jam benar-benar berjalan, bukan angka statis.
            await expect.poll(async () => (await countdown.innerText()).trim(), { timeout: 5_000 }).not.toBe(before);

            expect(sendRequests).toHaveLength(1);

            // Klik programatik pada tombol nonaktif tetap ditahan guard cooldown.
            await page.evaluate(() => {
                document
                    .querySelector('[data-testid="verify-email-resend"]')
                    ?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            });

            await expect(resendLine).toContainText(/kirim ulang\s+\(\d{2}:\d{2}\)/i);
            expect(sendRequests).toHaveLength(1);
        } finally {
            await context.close();
        }
    });

    test('kode aktif dari command lokal memverifikasi email dan menghapus banner', async ({ browser }) => {
        const email = createUnverifiedUser('sukses');
        const { context, page } = await openAsUser(browser, email);

        try {
            await openVerificationPage(page);
            await sendFirstCode(page);

            const code = await waitForActiveCode(email);
            await typeCode(page, code);

            await expect(page).toHaveURL(/\/joined/, { timeout: 40_000 });
            await expect(page.getByTestId('email-verification-banner')).toHaveCount(0);

            const state = readVerificationState(email);
            expect(state.verifiedAt).not.toBeNull();
            expect(state.challengeRows).toBe(0);
        } finally {
            await context.close();
        }
    });

    test('kode salah menampilkan alert, mengosongkan kotak, dan akun tetap belum terverifikasi', async ({
        browser,
    }) => {
        const email = createUnverifiedUser('salah');
        const { context, page } = await openAsUser(browser, email);

        try {
            await openVerificationPage(page);
            await sendFirstCode(page);

            const code = await waitForActiveCode(email);
            await typeCode(page, toWrongCode(code));

            await expect(page.getByRole('alert')).toContainText(/kode verifikasi salah/i, { timeout: 20_000 });

            const boxes = verificationBoxes(page);
            for (let index = 0; index < 6; index += 1) {
                await expect(boxes.nth(index)).toHaveValue('');
            }

            await expect(page).toHaveURL(/\/email\/verify/);
            expect(readVerificationState(email).verifiedAt).toBeNull();
        } finally {
            await context.close();
        }
    });
});
