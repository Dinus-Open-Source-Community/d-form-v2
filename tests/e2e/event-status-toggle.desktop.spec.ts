import { expect, test, cleanupOwnerFixture, ownerIdentityForTest, runFixtureCommand } from './support/fixtures';
import { readEnvironment } from './support/environment';

/**
 * Aksi publish/draft dari kartu Manage event di halaman detail acara.
 *
 * Fixture membuat event published atas nama admin E2E (bukan super-admin), supaya
 * `EventPolicy::update` mengizinkan perubahan status. Satu alur membuktikan kedua
 * arah: published → draft → published, termasuk konfirmasi dan persistensi setelah reload.
 */
test.describe('status acara dari kartu Manage event', () => {
    test('published → draft → published tanpa meninggalkan halaman', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(testInfo);
        const title = `E2E status toggle ${identity.ownerKey}`;

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title,
                forms: [{ title: 'Form status', fields: [], submissions: 0 }],
                ownerEmail: readEnvironment().adminEmail,
            });

            await page.goto(`/admin/events/${fixture.eventId}`, { waitUntil: 'domcontentloaded' });

            const manageCard = page
                .locator('[data-slot="card"]')
                .filter({ has: page.getByRole('heading', { name: 'Manage event' }) });
            await expect(manageCard).toBeVisible({ timeout: 20_000 });

            // Lifecycle sudah menyatu: archive dan status ada di kartu yang sama.
            await expect(manageCard.getByRole('button', { name: 'Archive event' })).toBeVisible();
            await expect(manageCard.getByRole('button', { name: 'Move to draft' })).toBeVisible();

            await manageCard.getByRole('button', { name: 'Move to draft' }).click();
            const draftDialog = page.getByRole('alertdialog');
            await expect(draftDialog.getByText('Move this event to draft?')).toBeVisible();
            await draftDialog.getByRole('button', { name: 'Move to draft' }).click();

            await expect(manageCard.getByRole('button', { name: 'Publish event' })).toBeVisible({ timeout: 20_000 });

            // Persistensi: reload tidak mengembalikan status published.
            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(
                page
                    .locator('[data-slot="card"]')
                    .filter({ has: page.getByRole('heading', { name: 'Manage event' }) })
                    .getByRole('button', { name: 'Publish event' })
            ).toBeVisible({ timeout: 20_000 });

            const refreshedCard = page
                .locator('[data-slot="card"]')
                .filter({ has: page.getByRole('heading', { name: 'Manage event' }) });
            await refreshedCard.getByRole('button', { name: 'Publish event' }).click();
            const publishDialog = page.getByRole('alertdialog');
            await expect(publishDialog.getByText('Publish this event?')).toBeVisible();
            await publishDialog.getByRole('button', { name: 'Publish event' }).click();

            await expect(refreshedCard.getByRole('button', { name: 'Move to draft' })).toBeVisible({ timeout: 20_000 });
            await expect(page.locator('body')).not.toContainText('Internal Server Error');
        } finally {
            cleanupOwnerFixture(identity);
        }
    });
});
