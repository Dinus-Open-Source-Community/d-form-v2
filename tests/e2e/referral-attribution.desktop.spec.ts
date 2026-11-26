import { expect, test } from './support/fixtures';
import { claimEventForAdmin } from './support/eventAdmin';

/**
 * E2E referral attribution (desktop): aktifkan referral, buat satu kode manual,
 * lalu pastikan kolom Referral tampil di tab Jawaban tanpa kontrol pengurutan.
 *
 * Batasan yang disengaja: redemption oleh peserta (submit form memakai kode referral)
 * TIDAK diuji di sini. Spec ini hanya memakai submission fixture tanpa referral untuk
 * memverifikasi daftar dan urutan; redemption sudah dibuktikan feature test backend.
 */

const REFERRAL_PREFIX = 'UDIN';
const REFERRAL_BODY = 'AB12';
const REFERRAL_CODE = `${REFERRAL_PREFIX}-${REFERRAL_BODY}`;
const ATTRIBUTION_NAME = 'Pemberi kode E2E Udin';

test.describe('referral attribution', () => {
    test.use({ fixtureFormCount: 1, fixtureFieldCount: 1, fixtureSubmissionCount: 2 });

    test('membuat kode manual lalu mengurutkan jawaban per kode referral', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }): Promise<void> => {
        claimEventForAdmin(fixtureEvent.eventId);

        const formPath = `/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}`;

        await page.goto(`${formPath}?tab=settings`, { waitUntil: 'domcontentloaded' });

        const referralSwitch = page.locator('#referral-enabled');
        await expect(referralSwitch).toBeVisible();
        await referralSwitch.click();

        const addCodeButton = page.getByRole('button', { name: 'Tambah kode' });
        await expect(addCodeButton).toBeVisible();

        await addCodeButton.click();

        const codeDialog = page.getByRole('dialog');
        await expect(codeDialog).toBeVisible();
        await expect(codeDialog.getByRole('heading', { name: 'Kode referral baru' })).toBeVisible();
        await expect(codeDialog.getByRole('heading', { name: 'Pemberi kode' })).toBeVisible();
        await expect(codeDialog.getByRole('heading', { name: 'Format kode' })).toBeVisible();
        await expect(codeDialog.getByRole('heading', { name: 'Batas dan status' })).toBeVisible();
        await expect(codeDialog.getByRole('heading', { name: 'Periode berlaku' })).toBeVisible();
        await expect(codeDialog.getByRole('button', { name: 'Simpan' })).toBeVisible();
        await codeDialog.locator('#referral-attribution-name').fill(ATTRIBUTION_NAME);
        await codeDialog.locator('#referral-prefix').fill(REFERRAL_PREFIX);
        await codeDialog.getByRole('button', { name: 'Manual' }).click();
        await codeDialog.locator('#referral-body').fill(REFERRAL_BODY);
        // Input type=number meng-cast nilai ke number; dulu membuat submit melempar error.
        await codeDialog.locator('#referral-usage-limit').fill('12');
        await codeDialog.getByRole('button', { name: 'Simpan' }).click();

        await expect(page.getByText(REFERRAL_CODE, { exact: true })).toBeVisible();

        await page.getByRole('tab', { name: /Jawaban/ }).click();

        const referralHeader = page.getByRole('columnheader', { name: 'Referral' });
        await expect(referralHeader).toBeVisible();
        await expect(referralHeader).not.toHaveAttribute('aria-sort');
        await expect(page.getByRole('button', { name: /Urutkan Referral/ })).toHaveCount(0);
        await expect(page.getByText('Total 2 jawaban masuk.')).toBeVisible();
        await expect(page.getByRole('row', { name: /Lihat detail jawaban/ })).toHaveCount(2);

        // Chip filter referral hidup berdampingan dengan kolomnya.
        await expect(page.getByRole('group', { name: 'Filter referral' })).toBeVisible();
        await page.getByRole('button', { name: 'Tanpa kode' }).click();
        await expect(page).toHaveURL(/referral=without/);
        await expect(page.getByRole('row', { name: /Lihat detail jawaban/ })).toHaveCount(2);

        await page.getByRole('tab', { name: /Pengaturan/ }).click();
        const codeRow = page.locator('li').filter({ hasText: REFERRAL_CODE });
        await codeRow.getByRole('button', { name: 'Hapus' }).click();

        const deleteDialog = page.getByRole('alertdialog');
        await expect(deleteDialog).toBeVisible();
        await expect(deleteDialog).toContainText('Hapus kode referral?');
        await expect(deleteDialog).toContainText(REFERRAL_CODE);
        await deleteDialog.getByRole('button', { name: 'Batal' }).click();
        await expect(page.getByText(REFERRAL_CODE, { exact: true })).toBeVisible();
    });
});
