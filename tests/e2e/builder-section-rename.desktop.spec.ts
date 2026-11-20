import type { Page, TestInfo } from '@playwright/test';
import { readEnvironment } from './support/environment';
import type { IOwnerIdentity } from './support/marker';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    defaultFixtureRequest,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';

interface IAdminOwnedForm {
    identity: IOwnerIdentity;
    eventId: string;
    formId: string;
}

/** Fixture bawaan milik user marker; admin kena 403, jadi form dibuat atas nama admin. */
function createAdminOwnedForm(testInfo: TestInfo): IAdminOwnedForm {
    const identity = ownerIdentityForTest(testInfo);
    const ownerEmail = readEnvironment().adminEmail;
    const owner = createOwnerFixture(identity, ownerEmail);
    const forms = createFormsFixture(owner, {
        ...defaultFixtureRequest(identity, 1, 1, 0),
        ownerEmail,
    });
    const primary = forms[0];

    if (primary === undefined) {
        cleanupOwnerFixture(identity);
        throw new Error('e2e:fixture returned no form.');
    }

    return { identity, eventId: owner.eventId, formId: primary.formId };
}

async function openBuilder(page: Page, eventId: string, formId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/${formId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
}

test('nama bagian bisa diubah lalu bagian dihapus lewat konfirmasi', async ({ page }, testInfo) => {
    test.setTimeout(60_000);

    const form = createAdminOwnedForm(testInfo);

    try {
        await openBuilder(page, form.eventId, form.formId);

        await page.getByRole('button', { name: /tambah bagian setelah ini/i }).click();

        const titleInput = page.locator('input[aria-label="Nama bagian"]');
        await expect(titleInput).toHaveValue('Bagian 2');
        await titleInput.fill('Berkas Pendukung');
        await expect(page.getByTestId('autosave-status')).toContainText('Tersimpan', { timeout: 20_000 });

        await page.getByRole('button', { name: 'Hapus bagian ini' }).click();
        await page.getByTestId('confirm-remove-section').click();

        await expect(page.getByRole('button', { name: 'Hapus bagian ini' })).toHaveCount(0);
        await expect(page.locator('input[aria-label="Nama bagian"]')).not.toHaveValue('Berkas Pendukung');
    } finally {
        cleanupOwnerFixture(form.identity);
    }
});
