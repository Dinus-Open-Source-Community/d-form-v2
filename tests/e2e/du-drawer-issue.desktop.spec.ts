import type { Page } from '@playwright/test';
import { runArtisan } from './support/artisan';
import { clearDuCourses, seedDuCourse } from './support/duCourses';
import { readEnvironment } from './support/environment';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';

/** Prefix uid bersama: semua baris cache yang dibuat spec ini dibersihkan lewat satu prefix. */
const SEEDED_UID_PREFIX = 'e2e-du-drawer-';
const AI_COURSE = { uid: `${SEEDED_UID_PREFIX}ai`, title: 'AI Engineer' };
const DEVOPS_COURSE = { uid: `${SEEDED_UID_PREFIX}devops`, title: 'DevOps' };
const FAKE_ISSUANCE = { status: 'issued', code: 'DU-E2E-CODE', message: null };

/** Tunggu Vue benar-benar mount; SSR sudah merender markup sebelum handler aktif. */
async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
}

/**
 * Tandai field `class` sebagai sumber redeem DU dengan opsi beku, lalu isi
 * `du_option_map` kosong agar drawer menampilkan saran dari katalog aktif.
 */
function seedDuClassField(formId: string): void {
    const metadata = {
        type: 'select',
        rules: { required: false, in: ['AI Engineer', 'DevOps'] },
        optionChoices: [
            { type: 'text', label: 'AI Engineer', value: 'AI Engineer' },
            { type: 'text', label: 'DevOps', value: 'DevOps' },
        ],
        du_redeem_enabled: true,
        du_option_map: {},
    };

    runArtisan(`
        \\Illuminate\\Support\\Facades\\DB::table('form_fields')
            ->where('form_id', '${formId}')
            ->where('name', 'class')
            ->update([
                'input_type' => 'selectInput',
                'metadata' => ${JSON.stringify(JSON.stringify(metadata))},
                'updated_at' => now(),
            ]);
    `);
}

test.describe('Drawer jawaban: redeem code DU', () => {
    test('E2E-DU-06-drawer-map-issue', async ({ page }) => {
        const identity = ownerIdentityForTest(test.info());
        const owned = createOwnerFixture(identity, readEnvironment().adminEmail);
        const runTag = Date.now();

        try {
            const [form] = createFormsFixture(owned, {
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E fixture ${identity.ownerKey}`,
                forms: [
                    {
                        title: 'Form redeem DU',
                        purpose: 'registration',
                        fields: [{ label: 'Class', name: 'class', input_type: 'selectInput', required: false }],
                        submissions: 0,
                    },
                ],
                acceptedAnswers: [
                    {
                        formIndex: 0,
                        registrationCode: `E2EDU${runTag}A`,
                        answers: { class: 'AI Engineer' },
                    },
                    {
                        formIndex: 0,
                        registrationCode: `E2EDU${runTag}B`,
                        answers: { class: 'AI Engineer' },
                    },
                ],
            });

            if (form === undefined) throw new Error('e2e:fixture tidak mengembalikan form.');

            seedDuClassField(form.formId);
            seedDuCourse(AI_COURSE.uid, AI_COURSE.title);
            seedDuCourse(DEVOPS_COURSE.uid, DEVOPS_COURSE.title);

            await page.goto(`/admin/events/${owned.eventId}/forms/${form.formId}?tab=jawaban`, {
                waitUntil: 'domcontentloaded',
            });
            await waitForHydration(page);

            const rows = page.locator('table tr[aria-label^="Lihat detail jawaban dari"]');

            await expect(page.getByRole('columnheader', { name: 'Kode DU' })).toBeVisible();
            await expect(rows).toHaveCount(2);
            await rows.first().click();

            const drawer = page.getByRole('dialog').filter({ hasText: 'Redeem code DU' });

            await expect(drawer).toBeVisible();
            await expect(drawer).toContainText('AI Engineer');
            await expect(drawer).toContainText('Mapping berlaku untuk semua jawaban dengan opsi ini.');

            // Saran exact title dipakai admin, bukan diterbitkan otomatis.
            const mappingResponse = page.waitForResponse(
                (response): boolean => response.url().includes('/du-field-map') && response.request().method() === 'PUT'
            );
            // Mapping memicu reload parsial `submissions`; tunggu responsnya agar jawaban
            // kedua membaca payload yang sudah terhubung, bukan mengandalkan timing toast.
            const submissionsReload = page.waitForResponse(
                (response): boolean =>
                    response.request().method() === 'GET' &&
                    (response.request().headers()['x-inertia-partial-data'] ?? '').includes('submissions')
            );

            await drawer.getByRole('button', { name: 'Gunakan' }).click();
            expect((await mappingResponse).status()).toBe(200);
            await submissionsReload;

            await expect(drawer).toContainText('Terhubung:');
            await expect(drawer).toContainText(AI_COURSE.title);

            // Kode berasal dari respons palsu; backend DU tidak dipanggil dari browser.
            await page.route('**/du-issuance', async (route): Promise<void> => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(FAKE_ISSUANCE),
                });
            });

            const issueButton = drawer.getByRole('button', { name: 'Terbitkan kode' });

            await expect(issueButton).toBeEnabled();
            await issueButton.click();

            await expect(drawer).toContainText(FAKE_ISSUANCE.code);
            // Kode terbit adalah sinyalnya; badge "Terbit" tidak lagi ditampilkan.
            await expect(drawer.getByText('Terbit', { exact: true })).toHaveCount(0);

            // Dua aksi pemulihan tersedia untuk kode yang sudah terbit.
            await expect(drawer.getByRole('button', { name: 'Kirim ulang email' })).toBeVisible();

            const reissueRequest = page.waitForRequest(
                (request): boolean => request.url().includes('/du-issuance') && request.method() === 'POST'
            );

            await drawer.getByRole('button', { name: 'Terbitkan ulang' }).click();
            await page.getByRole('alertdialog').getByRole('button', { name: 'Terbitkan ulang' }).click();
            expect((await reissueRequest).postDataJSON()).toMatchObject({ action: 'reissue' });
            await expect(page.getByRole('alertdialog')).toBeHidden();

            // Mapping tersimpan untuk opsi yang sama, jadi jawaban kedua sudah terhubung dan bisa diterbitkan.
            await page.keyboard.press('Escape');
            await expect(drawer).toBeHidden();
            await rows.nth(1).click();

            const secondDrawer = page.getByRole('dialog').filter({ hasText: 'Redeem code DU' });

            await expect(secondDrawer).toContainText('Terhubung:');

            const secondIssueButton = secondDrawer.getByRole('button', { name: 'Terbitkan kode' });

            await expect(secondIssueButton).toBeEnabled();
            await secondIssueButton.click();

            await expect(secondDrawer).toContainText(FAKE_ISSUANCE.code);
        } finally {
            try {
                clearDuCourses(SEEDED_UID_PREFIX);
            } finally {
                cleanupOwnerFixture(identity);
            }
        }
    });
});
