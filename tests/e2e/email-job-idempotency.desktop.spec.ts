import { expect, test, type Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';

// Public apply flow: no admin session and no seeded admin state.
test.use({ storageState: { cookies: [], origins: [] } });

const MAILER = runArtisanCommand(['tinker', '--execute', "echo config('mail.default');"]).trim();

const PNG_1X1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGP8z4APMOGVZRip0gBBLAETee26JgAAAABJRU5ErkJggg==',
    'base64'
);

const PDF_STUB = Buffer.from('%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n');

type TEmailLogRow = { notification_type: string; status: string; delivery_key: string | null };

function divisions(): string[] {
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        "echo json_encode(App\\Models\\Recruitment\\RecruitmentDivision::query()->where('is_active', true)->orderBy('name')->take(2)->pluck('name')->all());",
    ]);

    return JSON.parse(output) as string[];
}

function applicationIdForNim(nim: string): string | null {
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        `echo (string) (App\\Models\\Recruitment\\RecruitmentApplication::query()->where('nim', '${nim}')->value('id') ?? '');`,
    ]).trim();

    return output === '' ? null : output;
}

function emailLogsForNim(nim: string): TEmailLogRow[] {
    const applicationId = applicationIdForNim(nim);

    if (applicationId === null) {
        return [];
    }

    const output = runArtisanCommand([
        'tinker',
        '--execute',
        `echo json_encode(App\\Models\\EmailLog::query()->where('recruitment_application_id', '${applicationId}')->get(['notification_type', 'status', 'delivery_key'])->toArray());`,
    ]);

    return JSON.parse(output) as TEmailLogRow[];
}

/** Remove the applications created by this spec and their delivery artefacts. */
function cleanupApplications(nims: string[]): void {
    if (nims.length === 0) {
        return;
    }

    const list = nims.map((nim) => `'${nim}'`).join(', ');

    runArtisanCommand([
        'tinker',
        '--execute',
        `$apps = App\\Models\\Recruitment\\RecruitmentApplication::query()->whereIn('nim', [${list}])->get();` +
            `foreach ($apps as $app) {` +
            `App\\Models\\EmailLog::query()->where('recruitment_application_id', $app->id)->delete();` +
            `App\\Models\\Recruitment\\RecruitmentActivityLog::query()->where('recruitment_application_id', $app->id)->delete();` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('recruitment/' . $app->recruitment_period_id . '/' . $app->id);` +
            `$app->delete();` +
            `}`,
    ]);
}

async function selectOption(page: Page, controlId: string, optionName: string): Promise<void> {
    const trigger = page.locator(`#${controlId}`);

    await trigger.click();

    // Hanya popover ber-state open yang dirender aktif; popover lama bisa masih
    // ada selama animasi close dan punya label opsi yang sama. Reka melepas
    // node content saat animasi, jadi dispatch event langsung ke option.
    await page
        .locator('[data-state="open"] [role="listbox"]')
        .getByRole('option', { name: optionName, exact: true })
        .dispatchEvent('click');
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
}

async function fillIdentityStep(page: Page, values: { nim: string; email: string }): Promise<void> {
    await page.locator('#full_name').fill('E2E Idempotency');
    await page.locator('#nim').fill(values.nim);
    await selectOption(page, 'semester', '1');
    await page.locator('#phone').fill('081234567890');
    await page.locator('#personal_email').fill(values.email);
    await page.locator('#student_email').fill(`student.${values.email}`);
    await page.locator('#instagram_username').fill(`e2e_${values.nim.toLowerCase()}`);
}

function nextButton(page: Page) {
    return page.locator('button:visible').filter({ hasText: 'Lanjut' });
}

async function uploadRequiredFiles(page: Page): Promise<void> {
    await page.locator('#oprec-field-card-cv input[type=file]').setInputFiles({
        name: 'cv.pdf',
        mimeType: 'application/pdf',
        buffer: PDF_STUB,
    });
    await page.locator('#oprec-field-card-instagram_follow_proof input[type=file]').setInputFiles({
        name: 'follow.png',
        mimeType: 'image/png',
        buffer: PNG_1X1,
    });
}

test.describe('AUDIT-P1-007 email delivery idempotency', () => {
    test.beforeAll(() => {
        if (MAILER !== 'log') {
            throw new Error(`E2E requires MAIL_MAILER=log to avoid real delivery; current mailer is "${MAILER}".`);
        }
    });

    test('public apply creates exactly one confirmation log', async ({ page }) => {
        const suffix = Date.now();
        const nim = `E2E${suffix}`;
        const email = `e2e.${suffix}@example.test`;
        const [primary, secondary] = divisions();

        expect(primary, 'at least two active divisions are required').toBeTruthy();
        expect(secondary, 'at least two active divisions are required').toBeTruthy();

        try {
            await page.goto('/recruitment');
            await fillIdentityStep(page, { nim, email });
            await nextButton(page).click();

            await selectOption(page, 'primary_division_id', primary);
            await selectOption(page, 'secondary_division_id', secondary);
            await nextButton(page).click();

            await uploadRequiredFiles(page);
            await page.locator('#twibbon_url').fill(`https://instagram.com/p/e2e-${suffix}`);

            await page.getByRole('button', { name: /Kirim Pendaftaran/i }).click();

            await expect(page).toHaveURL(/\/recruitment\/success/, { timeout: 20_000 });
            await expect(page.getByText('Pendaftaran berhasil')).toBeVisible();

            const submittedLogs = (): TEmailLogRow[] =>
                emailLogsForNim(nim).filter((log) => log.notification_type === 'recruitment_application_submitted');

            await expect
                .poll(() => submittedLogs().filter((log) => log.status === 'sent').length, {
                    timeout: 45_000,
                    intervals: [1_000],
                })
                .toBe(1);

            const logs = submittedLogs();

            expect(logs).toHaveLength(1);
            expect(logs[0]?.status).toBe('sent');
            expect(logs[0]?.delivery_key).toBeTruthy();
        } finally {
            cleanupApplications([nim]);
        }
    });

    test('anomaly: empty step is blocked client-side and duplicate division/invalid twibbon are rejected server-side', async ({
        page,
    }) => {
        const suffix = Date.now();
        const nim = `E2EBAD${suffix}`;
        const email = `e2e.bad.${suffix}@example.test`;
        const [primary] = divisions();

        expect(primary, 'at least one active division is required').toBeTruthy();

        try {
            await page.goto('/recruitment');

            // Client-side: required fields on step 1 must block navigation.
            await nextButton(page).click();
            await expect(page.getByText(/wajib diisi/i).first()).toBeVisible();
            await expect(page).toHaveURL(/\/recruitment(\?|$)/);

            await fillIdentityStep(page, { nim, email });
            await nextButton(page).click();

            // Server-side: the optional secondary division must differ from the primary one.
            await selectOption(page, 'primary_division_id', primary);
            await selectOption(page, 'secondary_division_id', primary);
            await nextButton(page).click();

            await uploadRequiredFiles(page);
            await page.locator('#twibbon_url').fill('javascript:alert(1)');

            await page.getByRole('button', { name: /Kirim Pendaftaran/i }).click();

            await expect(
                page.locator('#app').getByText(/Divisi cadangan tidak boleh sama dengan divisi utama/i)
            ).toBeVisible({ timeout: 20_000 });
            await expect(page).not.toHaveURL(/\/recruitment\/success/);
            expect(applicationIdForNim(nim)).toBeNull();
        } finally {
            cleanupApplications([nim]);
        }
    });
});
