import { expect, test, type Locator, type Page } from '@playwright/test';
import { runArtisan, runArtisanCommand, runArtisanJson } from './support/artisan';

// OTP registration starts from the guest area; the admin storage state would be redirected away.
test.use({ storageState: { cookies: [], origins: [] } });

const PASSWORD = 'Rahasia123';

// Reserved marker prefix so every row this spec creates can be removed by one predicate.
const MARKER_PREFIX = 'e2e-pii-';

// Only the tail is read so a large laravel.log cannot exhaust the artisan buffer.
const LOG_TAIL_BYTES = 200_000;

type TMailerConfig = { mailer: string; host: string; port: number };

function mailerConfig(): TMailerConfig {
    return runArtisanJson<TMailerConfig>([
        'tinker',
        '--execute',
        "echo json_encode(['mailer' => config('mail.default'), 'host' => (string) config('mail.mailers.smtp.host'), 'port' => (int) config('mail.mailers.smtp.port')]);",
    ]);
}

function markerEmail(kind: string): string {
    return `${MARKER_PREFIX}${kind}-${Date.now()}-${Math.random().toString(16).slice(2, 8)}@example.invalid`;
}

function laravelLogTail(): string {
    return runArtisanJson<string>([
        'tinker',
        '--execute',
        `$path = (string) config('logging.channels.single.path'); echo json_encode($path !== '' && is_file($path) ? substr((string) file_get_contents($path), -${LOG_TAIL_BYTES}) : '');`,
    ]);
}

/** Lines of a log tail that mention the given marker. */
function markerLines(logTail: string, marker: string): string[] {
    return logTail.split('\n').filter((line: string): boolean => line.includes(marker));
}

/** OTP job lines for one pending registration, whether the delivery succeeded or failed. */
function otpJobLinesFor(pendingId: string): string[] {
    return laravelLogTail()
        .split('\n')
        .filter(
            (line: string): boolean =>
                line.includes(pendingId) &&
                (line.includes('[SendRegistrationOtpJob]') || line.includes('Gagal mengirim kode verifikasi OTP registrasi.')),
        );
}

function pendingRegistrationsFor(email: string): number {
    return Number(
        runArtisanCommand([
            'tinker',
            '--execute',
            `echo (string) App\\Models\\PendingRegistration::query()->where('email', '${email}')->count();`,
        ]),
    );
}

/** Id of the pending registration created for this run; used to anchor log assertions. */
function pendingRegistrationIdFor(email: string): string {
    return runArtisanCommand([
        'tinker',
        '--execute',
        `echo (string) App\\Models\\PendingRegistration::query()->where('email', '${email}')->value('id');`,
    ]);
}

function usersFor(email: string): number {
    return Number(
        runArtisanCommand([
            'tinker',
            '--execute',
            `echo (string) App\\Models\\User::query()->where('email', '${email}')->count();`,
        ]),
    );
}

/** Remove every pending registration and user created by this spec. */
function cleanupMarkerRows(): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `App\\Models\\PendingRegistration::query()->where('email', 'like', '${MARKER_PREFIX}%')->delete();` +
            `App\\Models\\User::query()->where('email', 'like', '${MARKER_PREFIX}%')->delete();`,
    ]);
}

/** Bucket rate limiter is per-minute and shared by the register route; several tests need a clean slate. */
function resetThrottleBuckets(): void {
    runArtisan('\\Illuminate\\Support\\Facades\\Cache::flush();');
}

function otpBoxes(page: Page): Locator {
    return page.getByRole('group', { name: /kode verifikasi/i }).getByRole('textbox');
}

/** Read the active registration OTP from the database instead of the mail transport. */
function readActiveOtp(email: string): string {
    return runArtisanCommand(['otp:current', email]);
}

/**
 * Fill the register form and wait until the OTP page is showing.
 * The first click is sometimes swallowed while the dev page is still hydrating, so it is retried.
 */
async function registerUntilOtpPage(page: Page, email: string, password: string = PASSWORD): Promise<void> {
    await page.goto('/auth/register');
    await page.locator('#register-name').fill('E2E PII Redaction');
    await page.locator('#register-email').fill(email);
    await page.locator('#register-password').fill(password);
    await page.locator('#register-password-confirmation').fill(password);

    await expect(page.locator('[data-email-availability="available"]')).toBeVisible();

    const button = page.getByRole('button', { name: /buat akun/i });

    for (let attempt = 0; attempt < 3; attempt += 1) {
        await button.click();

        try {
            await page.waitForURL(/\/auth\/verify-otp/, { timeout: 5_000 });

            return;
        } catch {
            if (/membuat akun/i.test(await button.innerText())) break;
        }
    }

    await expect(page).toHaveURL(/\/auth\/verify-otp/, { timeout: 25_000 });
}

test.describe('AUDIT-P2-006 PII and raw exception redaction', () => {
    test.describe.configure({ timeout: 90_000 });

    test.beforeEach(() => {
        resetThrottleBuckets();
    });

    test.afterAll(() => {
        cleanupMarkerRows();
    });

    test('happy path: OTP registration delivers a code and keeps the address out of job logs', async ({ page }) => {
        const config = mailerConfig();

        test.skip(config.mailer !== 'log', `Requires MAIL_MAILER=log (current: "${config.mailer}").`);

        const email = markerEmail('happy');

        await registerUntilOtpPage(page, email);

        await expect(page.getByTestId('otp-email')).toHaveText(email);
        await expect(otpBoxes(page)).toHaveCount(6);

        const pendingId = pendingRegistrationIdFor(email);
        expect(pendingId, 'the registration attempt must create a pending row for the run anchor').not.toBe('');

        const code = readActiveOtp(email);
        expect(code).toMatch(/^\d{6}$/);

        await otpBoxes(page).first().click();
        await page.keyboard.type(code);

        await expect(page).toHaveURL(/\/dashboard/, { timeout: 30_000 });
        expect(usersFor(email)).toBe(1);

        let jobLines: string[] = [];
        await expect
            .poll(
                () => {
                    jobLines = otpJobLinesFor(pendingId);

                    return jobLines.length;
                },
                { timeout: 60_000, intervals: [1_000] },
            )
            .toBeGreaterThan(0);

        const leakingLines = jobLines.filter((line: string): boolean => line.includes(email));

        expect(leakingLines, 'OTP job log lines must not contain the raw recipient address').toHaveLength(0);
    });

    test('failure path: a failed OTP send logs the exception class and correlation id but no raw message', async ({
        page,
    }) => {
        const config = mailerConfig();
        const failFastTransport = config.mailer === 'smtp' && config.host === '127.0.0.1' && config.port === 1;

        test.skip(
            !failFastTransport,
            `Requires a fail-fast SMTP transport (mailer="${config.mailer}", host="${config.host}", port=${config.port}).`,
        );

        const email = markerEmail('fail');

        await registerUntilOtpPage(page, email);

        const pendingId = pendingRegistrationIdFor(email);
        expect(pendingId, 'the registration attempt must create a pending row for the run anchor').not.toBe('');

        const failureMarker = 'Gagal mengirim kode verifikasi OTP registrasi.';
        let failureLines: string[] = [];

        await expect
            .poll(
                () => {
                    failureLines = markerLines(laravelLogTail(), failureMarker).filter((line: string): boolean =>
                        line.includes(pendingId),
                    );

                    return failureLines.length;
                },
                { timeout: 120_000, intervals: [2_000] },
            )
            .toBeGreaterThan(0);

        const latestFailure = failureLines.at(-1) ?? '';

        expect(latestFailure).toContain('exception_class');
        expect(latestFailure).toContain('job_id');
        expect(latestFailure).toContain('attempt');
        expect(latestFailure, 'the failure log must not contain the raw recipient address').not.toContain(email);
        expect(latestFailure, 'the diagnostic message stays available for operators').toContain('"error"');

        // The registration itself still exists, so the applicant can retry; no account is created yet.
        expect(pendingRegistrationsFor(email)).toBe(1);
        expect(usersFor(email)).toBe(0);
    });

    test('anomaly: weak password, mismatched confirmation, and a taken email never create a registration', async ({
        page,
    }) => {
        const weakEmail = markerEmail('weak');
        const mismatchEmail = markerEmail('mismatch');

        // Client guard: a password shorter than eight characters never reaches the server.
        await page.goto('/auth/register');
        await page.locator('#register-name').fill('E2E PII Redaction');
        await page.locator('#register-email').fill(weakEmail);
        await page.locator('#register-password').fill('short');
        await page.locator('#register-password-confirmation').fill('short');
        await expect(page.locator('[data-email-availability="available"]')).toBeVisible();
        await page.getByRole('button', { name: /buat akun/i }).click();
        await page.waitForTimeout(1_000);

        await expect(page).toHaveURL(/\/auth\/register/);
        expect(pendingRegistrationsFor(weakEmail)).toBe(0);
        expect(usersFor(weakEmail)).toBe(0);

        // Client guard: a mismatched confirmation never reaches the server.
        await page.locator('#register-email').fill(mismatchEmail);
        await page.locator('#register-password').fill(PASSWORD);
        await page.locator('#register-password-confirmation').fill(`${PASSWORD}x`);
        await expect(page.locator('[data-email-availability="available"]')).toBeVisible();
        await page.getByRole('button', { name: /buat akun/i }).click();
        await page.waitForTimeout(1_000);

        await expect(page).toHaveURL(/\/auth\/register/);
        expect(pendingRegistrationsFor(mismatchEmail)).toBe(0);
        expect(usersFor(mismatchEmail)).toBe(0);

        // Server guard: the availability endpoint reports an existing account, so nothing is created.
        await page.locator('#register-email').fill('stress-user-1@stress.test');
        await expect(page.locator('[data-email-availability="taken"]')).toBeVisible();
        expect(pendingRegistrationsFor('stress-user-1@stress.test')).toBe(0);

        // Server guard: a wrong OTP keeps the registration pending and shows the error state.
        const wrongCodeEmail = markerEmail('wrong');

        await registerUntilOtpPage(page, wrongCodeEmail);

        const activeCode = readActiveOtp(wrongCodeEmail);
        const wrongCode = (activeCode.startsWith('9') ? '1' : '9') + activeCode.slice(1);

        await otpBoxes(page).first().click();
        await page.keyboard.type(wrongCode);

        await expect(page.getByRole('alert')).toContainText(/kode verifikasi salah/i);
        await expect(page).toHaveURL(/\/auth\/verify-otp/);
        expect(pendingRegistrationsFor(wrongCodeEmail)).toBe(1);
        expect(usersFor(wrongCodeEmail)).toBe(0);

        const wrongPendingId = pendingRegistrationIdFor(wrongCodeEmail);
        expect(wrongPendingId, 'the wrong-code attempt must create a pending row for the run anchor').not.toBe('');

        let otpJobLines: string[] = [];

        await expect
            .poll(
                () => {
                    otpJobLines = otpJobLinesFor(wrongPendingId);

                    return otpJobLines.length;
                },
                { timeout: 60_000, intervals: [1_000] },
            )
            .toBeGreaterThan(0);

        const leakingLines = otpJobLines.filter((line: string): boolean => line.includes(wrongCodeEmail));

        expect(leakingLines, 'rejected attempts must not print the raw address in job logs').toHaveLength(0);
    });
});
