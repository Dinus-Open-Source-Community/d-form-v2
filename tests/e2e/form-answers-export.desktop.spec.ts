import { readFileSync } from 'node:fs';
import { expect, test, cleanupOwnerFixture, ownerIdentityForTest, runFixtureCommand } from './support/fixtures';
import { readEnvironment } from './support/environment';

/**
 * Tab Jawaban punya satu aksi Export dengan dropdown:
 * CSV per field dan ZIP semua jawaban + file per pengirim.
 */
test.describe('export jawaban form', () => {
    test('dropdown menawarkan CSV dan ZIP dan keduanya terunduh', async ({ page }, testInfo) => {
        test.setTimeout(180_000);

        const identity = ownerIdentityForTest(testInfo);

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E export answers ${identity.ownerKey}`,
                forms: [
                    {
                        title: 'Form export answers',
                        fields: [{ label: 'Pertanyaan', name: 'field_1', input_type: 'input', required: true }],
                        submissions: 1,
                    },
                ],
                ownerEmail: readEnvironment().adminEmail,
            });

            const form = fixture.forms[0];
            if (form === undefined) {
                throw new Error('Fixture tidak mengembalikan form export.');
            }

            await page.goto(`/admin/events/${fixture.eventId}/forms/${form.formId}?tab=jawaban`, {
                waitUntil: 'domcontentloaded',
            });

            // Tiga aksi form tidak tampil di tab Jawaban.
            await expect(page.getByRole('button', { name: 'Jadikan draft' })).toHaveCount(0);
            await expect(page.getByRole('button', { name: 'Pratinjau formulir' })).toHaveCount(0);
            await expect(page.getByRole('button', { name: 'Save All' })).toHaveCount(0);

            const exportButton = page.getByRole('button', { name: 'Export jawaban' });
            await expect(exportButton).toBeVisible({ timeout: 20_000 });
            await exportButton.click();

            const zipItem = page.getByRole('menuitem', { name: 'Download dengan file (ZIP)' });
            const csvItem = page.getByRole('menuitem', { name: 'Download biasa (CSV)' });
            await expect(zipItem).toBeVisible();
            await expect(csvItem).toBeVisible();

            await expect(zipItem).toHaveAttribute(
                'href',
                new RegExp(`/admin/events/${fixture.eventId}/forms/${form.formId}/answers\\.zip$`)
            );
            await expect(csvItem).toHaveAttribute(
                'href',
                new RegExp(`/admin/events/${fixture.eventId}/forms/${form.formId}/answers\\.csv$`)
            );

            const csvDownloadPromise = page.waitForEvent('download', { timeout: 30_000 });
            await csvItem.click();
            const csvDownload = await csvDownloadPromise;
            expect(csvDownload.suggestedFilename()).toMatch(/^jawaban-.+\.csv$/);

            const csvPath = await csvDownload.path();
            if (csvPath === null) {
                throw new Error('CSV tidak memiliki path unduhan.');
            }
            const csv = readFileSync(csvPath, 'utf8');
            expect(csv).toContain('submission_id');
            expect(csv).toContain('jawaban e2e');

            await exportButton.click();
            const zipDownloadPromise = page.waitForEvent('download', { timeout: 30_000 });
            await page.getByRole('menuitem', { name: 'Download dengan file (ZIP)' }).click();
            const zipDownload = await zipDownloadPromise;
            expect(zipDownload.suggestedFilename()).toMatch(/^jawaban-.+\.zip$/);
        } finally {
            cleanupOwnerFixture(identity);
        }
    });

    test('tab editor tetap menampilkan tiga aksi form', async ({ page }, testInfo) => {
        const identity = ownerIdentityForTest(testInfo);

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E export editor ${identity.ownerKey}`,
                forms: [{ title: 'Form editor', fields: [], submissions: 0 }],
                ownerEmail: readEnvironment().adminEmail,
            });
            const form = fixture.forms[0];
            if (form === undefined) {
                throw new Error('Fixture tidak mengembalikan form editor.');
            }

            await page.goto(`/admin/events/${fixture.eventId}/forms/${form.formId}?tab=editor`, {
                waitUntil: 'domcontentloaded',
            });

            await expect(page.getByRole('button', { name: 'Jadikan draft' })).toBeVisible({ timeout: 20_000 });
            await expect(page.getByRole('button', { name: 'Pratinjau formulir' })).toBeVisible();
            await expect(page.getByRole('button', { name: 'Save All' })).toBeVisible();
            await expect(page.getByRole('button', { name: 'Export jawaban' })).toHaveCount(0);
        } finally {
            cleanupOwnerFixture(identity);
        }
    });
});
