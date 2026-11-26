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

test('undo dan redo mengembalikan perubahan pada halaman builder', async ({ page }, testInfo) => {
    test.setTimeout(60_000);

    const form = createAdminOwnedForm(testInfo);

    try {
        await openBuilder(page, form.eventId, form.formId);

        const undo = page.getByRole('button', { name: 'Undo perubahan' });
        const redo = page.getByRole('button', { name: 'Redo perubahan' });
        const title = page.locator('#f-title');

        await expect(undo).toBeDisabled();
        await expect(redo).toBeDisabled();

        const original = await title.inputValue();

        await title.fill('Judul Sebelum Undo');
        await page.waitForTimeout(1_000);

        await expect(undo).toBeEnabled();
        await undo.click();
        await expect(title).toHaveValue(original);

        await expect(redo).toBeEnabled();
        await redo.click();
        await expect(title).toHaveValue('Judul Sebelum Undo');
    } finally {
        cleanupOwnerFixture(form.identity);
    }
});
