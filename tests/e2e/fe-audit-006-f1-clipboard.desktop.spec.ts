import { expect, test, type Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';

test.use({ storageState: { cookies: [], origins: [] } });

const PNG_1X1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAAE0lEQVR4nGP8z4APMOGVZRip0gBBLAETee26JgAAAABJRU5ErkJggg==',
    'base64'
);
const PDF_STUB = Buffer.from('%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n');

function divisions(): string[] {
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        "echo json_encode(App\\Models\\Recruitment\\RecruitmentDivision::query()->where('is_active', true)->orderBy('name')->take(2)->pluck('name')->all());",
    ]);
    const parsed: unknown = JSON.parse(output);
    if (!Array.isArray(parsed) || !parsed.every((item): item is string => typeof item === 'string')) {
        throw new Error('Daftar divisi aktif tidak valid.');
    }
    return parsed;
}

async function selectOption(page: Page, id: string, label: string): Promise<void> {
    await page.locator(`#${id}`).click();
    await page
        .locator('[data-state="open"] [role="listbox"]')
        .getByRole('option', { name: label, exact: true })
        .click();
}

async function fillApply(page: Page, nim: string, email: string): Promise<void> {
    const [primary, secondary] = divisions();
    if (primary === undefined || secondary === undefined) throw new Error('Diperlukan dua divisi aktif.');
    await page.locator('#full_name').fill('FE-AUDIT-006-F1 Clipboard');
    await page.locator('#nim').fill(nim);
    await selectOption(page, 'semester', '1');
    await page.locator('#phone').fill('081234567890');
    await page.locator('#personal_email').fill(email);
    await page.locator('#student_email').fill(`student.${email}`);
    await page.locator('#instagram_username').fill(`e2e_${nim.toLowerCase()}`);
    await page.getByRole('button', { name: 'Lanjut' }).click();
    await selectOption(page, 'primary_division_id', primary);
    await selectOption(page, 'secondary_division_id', secondary);
    await page.getByRole('button', { name: 'Lanjut' }).click();
    await page
        .locator('#oprec-field-card-cv input[type=file]')
        .setInputFiles({ name: 'cv.pdf', mimeType: 'application/pdf', buffer: PDF_STUB });
    await page
        .locator('#oprec-field-card-instagram_follow_proof input[type=file]')
        .setInputFiles({ name: 'follow.png', mimeType: 'image/png', buffer: PNG_1X1 });
    await page.locator('#twibbon_url').fill(`https://instagram.com/p/${nim}`);
    await page.getByRole('button', { name: /Kirim Pendaftaran/i }).click();
}

function cleanupApplication(nim: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$apps=App\\Models\\Recruitment\\RecruitmentApplication::query()->where('nim','${nim}')->get();` +
            `foreach($apps as $app){` +
            `App\\Models\\EmailLog::query()->where('recruitment_application_id',$app->id)->delete();` +
            `App\\Models\\Recruitment\\RecruitmentActivityLog::query()->where('recruitment_application_id',$app->id)->delete();` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('recruitment/'.$app->recruitment_period_id.'/'.$app->id);` +
            `$app->delete();}`,
    ]);
}

test.describe('FE-AUDIT-006-F1 clipboard fallback', () => {
    test('FE_AUDIT_006_F1 rejected clipboard shows manual registration copy without success', async ({ page }) => {
        const nim = `E2EF1${Date.now()}`;
        await page.addInitScript((): void => {
            Object.defineProperty(navigator, 'clipboard', {
                configurable: true,
                value: { writeText: async (): Promise<never> => Promise.reject(new Error('clipboard rejected')) },
            });
        });
        try {
            await page.goto('/recruitment');
            await fillApply(page, nim, `e2e.${nim}@example.test`);
            await expect(page).toHaveURL(/\/recruitment\/success/);
            const registrationNumber = await page.locator('p.font-mono').textContent();
            if (registrationNumber === null || registrationNumber === '') {
                throw new Error('Nomor pendaftaran tidak tampil.');
            }
            await page.getByRole('button', { name: 'Salin nomor' }).click();
            await expect(page.getByRole('alert')).toContainText(registrationNumber);
            await expect(page.locator('[data-sonner-toast]').filter({ hasText: 'berhasil disalin' })).toHaveCount(0);
        } finally {
            cleanupApplication(nim);
        }
    });
});
