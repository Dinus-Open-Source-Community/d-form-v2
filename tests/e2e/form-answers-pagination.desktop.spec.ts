import { readFileSync } from 'node:fs';
import { expect, test, cleanupOwnerFixture, ownerIdentityForTest, runFixtureCommand } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import { readEnvironment } from './support/environment';

/**
 * Pagination tab Jawaban hanya membatasi tampilan/load baris. Select-all dan
 * export tetap memakai seluruh jawaban (bukan satu halaman).
 */

function seedGuestAnswers(formId: string, total: number): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$formId='${formId}'; $now=now(); $rows=[]; ` +
            `for($i=1;$i<=${total};$i++){ $n=str_pad($i,2,'0',STR_PAD_LEFT); ` +
            `$rows[]=['id'=>(string) \\Illuminate\\Support\\Str::orderedUuid(),'form_id'=>$formId,` +
            `'user_id'=>null,'invited_email'=>"page{$n}@example.test",` +
            `'answers'=>json_encode(['field_1'=>"jawaban {$n}"], JSON_THROW_ON_ERROR),` +
            `'is_active'=>1,'created_at'=>$now->copy()->subMinutes(${total}-$i),'updated_at'=>$now]; } ` +
            `\\Illuminate\\Support\\Facades\\DB::table('form_answers')->insert($rows);`,
    ]);
}

test.describe('pagination jawaban', () => {
    test('pindah halaman, select all lintas halaman, dan export tetap semua data', async ({ page }, testInfo) => {
        test.setTimeout(180_000);

        const identity = ownerIdentityForTest(testInfo);

        try {
            const fixture = runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: `E2E pagination answers ${identity.ownerKey}`,
                forms: [
                    {
                        title: 'Form pagination answers',
                        fields: [{ label: 'Pertanyaan', name: 'field_1', input_type: 'input', required: true }],
                        submissions: 0,
                    },
                ],
                ownerEmail: readEnvironment().adminEmail,
            });

            const form = fixture.forms[0];
            if (form === undefined) {
                throw new Error('Fixture tidak mengembalikan form pagination.');
            }

            seedGuestAnswers(form.formId, 25);

            await page.goto(`/admin/events/${fixture.eventId}/forms/${form.formId}?tab=jawaban&per_page=10`, {
                waitUntil: 'domcontentloaded',
            });

            await expect(page.getByRole('navigation', { name: 'pagination' })).toBeVisible({ timeout: 20_000 });
            await expect(page.getByText('1–10 dari 25')).toBeVisible();

            const answersCard = page
                .locator('.app-surface')
                .filter({ has: page.getByRole('heading', { name: 'Daftar jawaban' }) });
            const rows = answersCard.locator('tbody tr');
            await expect(rows).toHaveCount(10);
            await expect(rows.first().locator('td').nth(1)).toHaveText('1');
            await expect(rows.last().locator('td').nth(1)).toHaveText('10');

            // Select all mengikuti seluruh ID eligible, bukan hanya 10 baris halaman ini.
            await page.getByRole('checkbox', { name: 'Pilih semua jawaban' }).click();
            await expect(page.getByText('25 dipilih').first()).toBeVisible();

            // Pindah halaman: nomor baris lanjut 11 dan semua baris halaman 2 sudah terpilih.
            await page.getByRole('button', { name: 'Halaman 2' }).click();
            await expect(page).toHaveURL(/page=2/);
            await expect(page.getByText('11–20 dari 25')).toBeVisible();
            await expect(rows).toHaveCount(10);
            await expect(rows.first().locator('td').nth(1)).toHaveText('11');
            await expect(page.getByText('25 dipilih').first()).toBeVisible();

            const pageTwoBoxes = page.getByRole('checkbox', { name: /Pilih jawaban nomor/ });
            await expect(pageTwoBoxes).toHaveCount(10);
            for (let index = 0; index < 10; index++) {
                await expect(pageTwoBoxes.nth(index)).toHaveAttribute('aria-checked', 'true');
            }

            // Export dari halaman 2 tetap berisi seluruh 25 jawaban.
            await page.getByRole('button', { name: 'Export jawaban' }).click();
            const csvDownloadPromise = page.waitForEvent('download', { timeout: 30_000 });
            await page.getByRole('menuitem', { name: 'Download biasa (CSV)' }).click();
            const download = await csvDownloadPromise;
            const csvPath = await download.path();
            if (csvPath === null) {
                throw new Error('CSV tidak memiliki path unduhan.');
            }

            const csv = readFileSync(csvPath, 'utf8');
            const dataRows = csv
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter((line) => line !== '');
            expect(dataRows).toHaveLength(26); // header + 25 jawaban, bukan 10 baris halaman.
        } finally {
            cleanupOwnerFixture(identity);
        }
    });
});
