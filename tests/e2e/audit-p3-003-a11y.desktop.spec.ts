import { expect, test } from './support/fixtures';

test.describe('AUDIT_P3_003 accessible control names', () => {
    test('recruitment file remove control is keyboard operable', async ({ page }) => {
        await page.goto('/recruitment?step=3');
        await page.getByRole('radio', { name: 'file', exact: true }).check();
        const portfolioUpload = page.locator('#oprec-field-card-portfolio_file input[type=file]');
        await portfolioUpload.setInputFiles({
            name: 'portfolio.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4\n%%EOF\n'),
        });
        const removeButton = page.getByRole('button', { name: 'Hapus portfolio.pdf' });
        await expect(removeButton).toBeVisible();
        await removeButton.focus();
        await expect(removeButton).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('button', { name: 'Hapus portfolio.pdf' })).toHaveCount(0);
    });

    test('keyboard can reach the builder component search', async ({ page, fixtureEvent }) => {
        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/create`, { waitUntil: 'domcontentloaded' });

        const searchInput = page.getByRole('textbox', { name: 'Cari komponen' });
        await expect(searchInput).toBeVisible();
        await searchInput.focus();
        await expect(searchInput).toBeFocused();
        await page.keyboard.press('Enter');
    });

    test('activity log filters have names and are keyboard operable', async ({ page }) => {
        await page.goto('/admin/recruitment/activity-logs', { waitUntil: 'domcontentloaded' });

        const periodFilter = page.getByRole('combobox', { name: 'Filter periode' });
        const actionFilter = page.getByRole('searchbox', { name: 'Filter aksi' });
        await expect(periodFilter).toBeVisible();
        await expect(actionFilter).toBeVisible();
        await periodFilter.focus();
        await expect(periodFilter).toBeFocused();
        await page.keyboard.press('Enter');
        await actionFilter.focus();
        await expect(actionFilter).toBeFocused();
        await page.keyboard.press('Enter');
    });
});
