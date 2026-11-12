import { expect, test } from '@playwright/test';
import {
    expectJoinedAnswerFiles,
    expectJoinedBanner,
    expectJoinedImageFallback,
    expectJoinedImageToolbarLayout,
    expectJoinedNoHorizontalOverflow,
    readJoinedRegistrationData,
} from './support/joinedRegistration';

const REGISTRATION_PATH = '/joined/ep04-lane-a-adoption-fixture/registration';

test('joined registration stays compact on desktop and preserves answer file actions', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(REGISTRATION_PATH);
    await expect(page).toHaveURL(new RegExp(`${REGISTRATION_PATH}$`));

    const data = await readJoinedRegistrationData(page);
    expect(data).toBeTruthy();
    expect(data.event).toBeTruthy();
    expect(data.registration).toBeTruthy();

    await expectJoinedBanner(page);
    await expectJoinedAnswerFiles(page);
    await expectJoinedImageToolbarLayout(page);
    await expectJoinedNoHorizontalOverflow(page);
    await expect(page.getByText('Dikirim', { exact: false }).first()).toBeVisible();
    await expect(page.getByText('Tanggal', { exact: true })).toBeVisible();
    await expect(page.getByText('Lokasi', { exact: true })).toBeVisible();

    const answerGrid = page.locator('main .grid.grid-cols-1');
    const gridColumns = await answerGrid.evaluate((element) => getComputedStyle(element).gridTemplateColumns);
    expect(gridColumns.trim().split(/\s+/)).toHaveLength(2);

    await page.screenshot({ path: testInfo.outputPath('joined-registration-desktop.png'), fullPage: true });

    await expectJoinedImageFallback(page);
    await expectJoinedNoHorizontalOverflow(page);

    await page.unroute('**/form-answers/**/files/**');
    await page.reload();
    await page.getByRole('button', { name: 'Buka gambar layar penuh' }).first().click();
    const lightbox = page.getByRole('dialog', { name: /Pratinjau gambar/ });
    await expect(lightbox).toBeVisible();
    await expect(lightbox.getByRole('button', { name: 'Tutup pratinjau' })).toBeVisible();
    await expect
        .poll(() =>
            lightbox.locator('img').evaluate((image) => (image instanceof HTMLImageElement ? image.naturalWidth : 0))
        )
        .toBeGreaterThan(0);
    await page.screenshot({ path: testInfo.outputPath('joined-registration-image-preview.png') });
    await lightbox.getByRole('button', { name: 'Tutup pratinjau' }).click();
    await expect(lightbox).toHaveCount(0);
});
