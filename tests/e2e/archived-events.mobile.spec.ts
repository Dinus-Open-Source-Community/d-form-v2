import { expect, test } from './support/fixtures';
import { openAuthenticatedPath } from './support/eventAdmin';

test.describe('DFORM-ARCHIVED-EVENTS mobile', () => {
    test('sidebar mobile membuka halaman arsip dan daftar merender', async ({ page }): Promise<void> => {
        await openAuthenticatedPath(page, '/dashboard');

        // Di mobile sidebar adalah sheet tertutup: buka lewat trigger topbar dulu.
        await page.getByRole('button', { name: 'Buka sidebar' }).click();

        const navigation = page.locator('[data-slot="sidebar"]');
        const groupButton = navigation.getByRole('button', { name: 'Acara', exact: true });

        await expect(groupButton).toBeVisible();
        await groupButton.click();
        await expect(groupButton).toHaveAttribute('aria-expanded', 'true');

        const archivedLink = navigation.getByRole('link', { name: 'Arsip acara', exact: true });

        await expect(archivedLink).toBeVisible();
        await archivedLink.click();

        await expect(page).toHaveURL(/\/admin\/events\/archived$/);
        // Filter selalu dirender halaman arsip; kehadirannya membuktikan komponen termuat.
        await expect(page.getByLabel('Filter kategori')).toBeVisible();
        await expect(page.getByLabel('Filter sesi')).toBeVisible();

        // Daftar boleh kosong; salah satu dari keadaan kosong atau kartu arsip harus tampil.
        await expect(
            page
                .getByText('Tidak ada acara di arsip', { exact: true })
                .or(page.getByText('Diarsipkan', { exact: true }).first())
        ).toBeVisible();
    });
});
