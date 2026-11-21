import { readEnvironment } from './support/environment';
import { cleanupOwnerFixture, createFormsFixture, createOwnerFixture, expect, ownerIdentityForTest, test } from './support/fixtures';

test('D-Kelas schedule supports add, persistence, duplicate rejection, and removal', async ({ page }, testInfo) => {
    test.setTimeout(60_000);
    const identity = ownerIdentityForTest(testInfo);
    const owner = createOwnerFixture(identity, readEnvironment().adminEmail);
    const [form] = createFormsFixture(owner, {
        marker: identity.marker,
        slug: identity.slug,
        title: 'D-Kelas schedule fixture',
        forms: [{ title: 'D-Kelas form', fields: [], submissions: 0, purpose: 'd_kelas' }],
    });

    if (form === undefined) {
        throw new Error('e2e:fixture returned no form.');
    }

    const date = '2099-12-31';
    const settingsUrl = `/admin/events/${owner.eventId}/forms/${form.formId}?tab=settings`;

    try {
        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'));
        await expect(page.getByRole('heading', { name: 'Jadwal kelas' })).toBeVisible();
        const dateInput = page.getByTestId('session-date-input');
        await expect(dateInput).toBeVisible();
        await expect(page.getByText('Belum ada jadwal.')).toBeVisible();

        await dateInput.fill(date);
        const addResponsePromise = page.waitForResponse(
            (response) => response.request().method() === 'POST' && response.url().includes('/sessions')
        );
        await page.getByTestId('session-add-button').click();
        const addResponse = await addResponsePromise;
        expect(addResponse.status()).toBeLessThan(400);
        await expect(page.getByText(/31 Desember 2099|2099-12-31/)).toBeVisible();
        await expect(page.locator('[data-testid="session-row"]').filter({ hasText: date })).toHaveCount(1);

        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await expect(page.getByTestId('session-row').filter({ hasText: date })).toBeVisible();

        await dateInput.fill(date);
        await page.getByTestId('session-add-button').click();
        await expect(page.getByText('Tanggal ini sudah ada di jadwal.')).toBeVisible();

        const removeResponsePromise = page.waitForResponse(
            (response) => response.request().method() === 'DELETE' && response.url().includes('/sessions/')
        );
        await page.getByTestId('session-remove-button').click();
        const removeResponse = await removeResponsePromise;
        expect(removeResponse.status()).toBeLessThan(400);
        await expect(page.getByText('Belum ada jadwal.')).toBeVisible();
        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('Belum ada jadwal.')).toBeVisible();
        await expect(page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).resolves.toBe(true);
    } finally {
        cleanupOwnerFixture(identity);
    }
});
