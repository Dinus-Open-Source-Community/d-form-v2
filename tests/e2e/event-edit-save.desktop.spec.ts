import { expect, test, cleanupOwnerFixture, ownerIdentityForTest, runFixtureCommand } from './support/fixtures';
import { runArtisanCommand, runArtisanJson } from './support/artisan';
import { readEnvironment } from './support/environment';

/**
 * Edit event tidak lagi punya aksi "Simpan & terbitkan"; status publikasi hanya
 * berubah lewat aksi di halaman detail. Create tetap punya tombol "Terbitkan".
 */

function readEventStatus(eventId: string): string {
    const payload = runArtisanJson<{ status: string }>([
        'tinker',
        '--execute',
        `echo json_encode(['status'=>\\App\\Models\\Event::find('${eventId}')->status->value]);`,
    ]);

    return payload.status;
}

test.describe('edit event tanpa aksi terbitkan', () => {
    test('hanya Simpan perubahan dan status published tidak berubah', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(testInfo);
        const title = `E2E edit status ${identity.ownerKey}`;

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title,
                forms: [{ title: 'Form edit', fields: [], submissions: 0 }],
                ownerEmail: readEnvironment().adminEmail,
            });

            expect(readEventStatus(fixture.eventId)).toBe('published');

            // Fixture memakai jam `now()` untuk registration_end dan tanggal tengah malam
            // untuk end_date, sehingga edit pertama akan gagal aturan
            // "registration_end <= end_date". Rapikan rentang agar test menguji
            // penyimpanan edit, bukan validasi tanggal fixture.
            runArtisanCommand([
                'tinker',
                '--execute',
                `\\App\\Models\\Event::whereKey('${fixture.eventId}')->update([` +
                    `'registration_end' => now()->addDay()->endOfDay(), ` +
                    `'end_date' => now()->addDays(2)->toDateString()]);`,
            ]);

            await page.goto(`/admin/events/${fixture.eventId}/edit`, { waitUntil: 'domcontentloaded' });

            await expect(page.getByRole('button', { name: 'Simpan & terbitkan' })).toHaveCount(0);
            const saveButton = page.getByRole('button', { name: 'Simpan perubahan' });
            await expect(saveButton).toBeVisible({ timeout: 20_000 });

            const updatedTitle = `${title} diperbarui`;
            await page.locator('#title').fill(updatedTitle);
            await saveButton.click();

            await page.waitForURL(`**/admin/events/${fixture.eventId}`, { timeout: 30_000 });
            await expect(page.getByRole('heading', { name: updatedTitle, level: 1 })).toBeVisible({ timeout: 20_000 });
            await expect(page.getByText('Perubahan disimpan.')).toBeVisible({ timeout: 20_000 });

            expect(readEventStatus(fixture.eventId)).toBe('published');
        } finally {
            cleanupOwnerFixture(identity);
        }
    });
});
