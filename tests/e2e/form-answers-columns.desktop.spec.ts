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

/** Form milik admin dengan banyak field agar batas kolom tabel ikut teruji. */
function createAdminOwnedForm(testInfo: TestInfo, fieldCount: number): IAdminOwnedForm {
    const identity = ownerIdentityForTest(testInfo);
    const ownerEmail = readEnvironment().adminEmail;
    const owner = createOwnerFixture(identity, ownerEmail);
    const forms = createFormsFixture(owner, {
        ...defaultFixtureRequest(identity, 1, fieldCount, 1),
        ownerEmail,
    });
    const primary = forms[0];

    if (primary === undefined) {
        cleanupOwnerFixture(identity);
        throw new Error('e2e:fixture returned no form.');
    }

    return { identity, eventId: owner.eventId, formId: primary.formId };
}

async function openAnswersTab(page: Page, form: IAdminOwnedForm): Promise<void> {
    await page.goto(`/admin/events/${form.eventId}/forms/${form.formId}?tab=jawaban`, {
        waitUntil: 'domcontentloaded',
    });
    await expect(page.getByRole('table').first()).toBeVisible({ timeout: 20_000 });
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
}

test('tabel jawaban hanya menampilkan enam kolom field walau form punya delapan', async ({ page }, testInfo) => {
    test.setTimeout(60_000);

    const form = createAdminOwnedForm(testInfo, 8);

    try {
        await openAnswersTab(page, form);

        const table = page.locator('table').first();

        // Enam kolom inti (pilih, No., Pengirim, Status review, Dikirim, Aksi) + enam kolom field.
        await expect(table.locator('thead th')).toHaveCount(12);
        await expect(page.getByRole('button', { name: 'Atur kolom tabel jawaban' })).toBeVisible();
    } finally {
        cleanupOwnerFixture(form.identity);
    }
});
