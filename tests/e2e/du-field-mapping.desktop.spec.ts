import type { Locator, Page } from '@playwright/test';
import { runArtisan } from './support/artisan';
import { closeFieldDrawer, openFieldDrawer } from './support/builderFieldCard';
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
const SEEDED_UID_PREFIX = 'e2e-du-mapping-';
const AI_COURSE = { uid: `${SEEDED_UID_PREFIX}ai`, title: 'AI Engineer' };
const DEVOPS_COURSE = { uid: `${SEEDED_UID_PREFIX}devops`, title: 'DevOps' };

/** Tunggu Vue benar-benar mount; SSR sudah merender markup sebelum handler aktif. */
async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
}

/**
 * Field `class` kustom dengan opsi beku tetapi belum memakai redeem DU.
 * Admin yang menyalakan toggle dan memetakan opsi lewat drawer editor.
 * Tanggal tutup diisi agar autosave/save form lolos validasi header.
 */
function seedUnflaggedClassField(formId: string): void {
    const metadata = {
        rules: { required: false, in: ['AI Engineer', 'DevOps'] },
        optionChoices: [
            { type: 'text', label: 'AI Engineer', value: 'AI Engineer' },
            { type: 'text', label: 'DevOps', value: 'DevOps' },
        ],
    };

    runArtisan(`
        \\Illuminate\\Support\\Facades\\DB::table('forms')
            ->where('id', '${formId}')
            ->update(['closed_at' => now()->addDays(30), 'updated_at' => now()]);

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

/** Tombol simpan yang benar-benar terlihat di header tab (desktop). */
function saveControls(page: Page): Locator {
    return page.getByRole('button', { name: /^(Simpan|Save All|Save Form)$/ }).filter({ visible: true });
}

test.describe('Editor field: redeem code DU pada form registrasi', () => {
    test('E2E-DU-07-field-toggle-map-persist', async ({ page }) => {
        const identity = ownerIdentityForTest(test.info());
        const owned = createOwnerFixture(identity, readEnvironment().adminEmail);

        try {
            const [form] = createFormsFixture(owned, {
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E fixture ${identity.ownerKey}`,
                forms: [
                    {
                        title: 'Form mapping DU',
                        purpose: 'registration',
                        fields: [{ label: 'Class', name: 'class', input_type: 'selectInput', required: false }],
                        submissions: 0,
                    },
                ],
            });

            if (form === undefined) throw new Error('e2e:fixture tidak mengembalikan form.');

            seedUnflaggedClassField(form.formId);
            seedDuCourse(AI_COURSE.uid, AI_COURSE.title);
            seedDuCourse(DEVOPS_COURSE.uid, DEVOPS_COURSE.title);

            await page.goto(`/admin/events/${owned.eventId}/forms/${form.formId}`, {
                waitUntil: 'domcontentloaded',
            });
            await waitForHydration(page);

            const inspector = await openFieldDrawer(page, 0);
            const toggle = inspector.getByLabel('Redeem code DU');

            await expect(toggle).not.toBeChecked();
            // Input switch sengaja `sr-only`; klik labelnya seperti user sungguhan.
            await inspector.getByText('Redeem code DU', { exact: true }).click();
            await expect(toggle).toBeChecked();

            // Opsi dibekukan dan kartu mapping muncul setelah flag aktif.
            await expect(inspector).toContainText('Opsi terkunci selama redeem code DU aktif.');
            await expect(inspector).toContainText('Hubungkan opsi ke DU');

            const aiRow = inspector
                .locator('[data-testid="du-option-row"]')
                .filter({ hasText: 'AI Engineer' })
                .first();
            const devopsRow = inspector
                .locator('[data-testid="du-option-row"]')
                .filter({ hasText: 'DevOps' })
                .first();

            await expect(aiRow).toContainText('Belum terhubung');
            await aiRow.getByRole('button', { name: 'Gunakan' }).click();
            await expect(aiRow).toContainText('Terhubung');
            await expect(aiRow).toContainText(AI_COURSE.title);

            // Opsi kedua lewat dialog pencarian, bukan dropdown inline.
            await expect(devopsRow).toContainText('Belum terhubung');
            await devopsRow.getByRole('button', { name: 'Pilih course DU untuk DevOps' }).click();

            const picker = page.getByRole('dialog', { name: 'Pilih course DU' });
            await expect(picker).toBeVisible();
            await picker.getByLabel('Cari course DU').fill('DevOps');
            await picker.getByRole('button', { name: 'DevOps', exact: true }).click();
            await expect(picker).toHaveCount(0);

            await expect(devopsRow).toContainText('Terhubung');
            await expect(devopsRow).toContainText(DEVOPS_COURSE.title);

            await closeFieldDrawer(page);

            // Simpan manual supaya reload berikutnya membaca state yang sudah pasti tersimpan.
            const updateUrl = `/admin/events/${owned.eventId}/forms/${form.formId}`;
            const saved = page.waitForResponse(
                (response): boolean =>
                    new URL(response.url()).pathname === updateUrl &&
                    ['PUT', 'POST'].includes(response.request().method()) &&
                    response.status() < 400
            );

            await saveControls(page).first().click();
            expect((await saved).status()).toBeLessThan(400);

            await page.reload({ waitUntil: 'domcontentloaded' });
            await waitForHydration(page);

            const reloadedInspector = await openFieldDrawer(page, 0);
            const reloadedToggle = reloadedInspector.getByLabel('Redeem code DU');

            await expect(reloadedToggle).toBeChecked();
            await expect(reloadedInspector).toContainText('Hubungkan opsi ke DU');

            const reloadedAiRow = reloadedInspector
                .locator('[data-testid="du-option-row"]')
                .filter({ hasText: 'AI Engineer' })
                .first();
            const reloadedDevopsRow = reloadedInspector
                .locator('[data-testid="du-option-row"]')
                .filter({ hasText: 'DevOps' })
                .first();

            await expect(reloadedAiRow).toContainText('Terhubung');
            await expect(reloadedAiRow).toContainText(AI_COURSE.title);
            await expect(reloadedDevopsRow).toContainText('Terhubung');
            await expect(reloadedDevopsRow).toContainText(DEVOPS_COURSE.title);
        } finally {
            try {
                clearDuCourses(SEEDED_UID_PREFIX);
            } finally {
                cleanupOwnerFixture(identity);
            }
        }
    });
});
