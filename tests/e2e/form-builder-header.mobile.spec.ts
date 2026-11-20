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

async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => {
            const container = document.querySelector('#app');

            return container !== null && Reflect.has(container, '__vue_app__');
        },
        undefined,
        { timeout: 20_000 }
    );
}

/** Event/form milik admin E2E: fixture bawaan membuat event milik user marker sehingga admin kena 403. */
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

test('kartu header muat di layar mobile tanpa overflow horizontal', async ({ page }, testInfo) => {
    const form = createAdminOwnedForm(testInfo);

    try {
        const response = await page.goto(`/admin/events/${form.eventId}/forms/${form.formId}`, {
            waitUntil: 'domcontentloaded',
        });

        expect(response?.status() ?? 0).toBe(200);
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
        await waitForHydration(page);

        const overflow = await page.evaluate((): number => document.documentElement.scrollWidth - window.innerWidth);

        expect(overflow).toBeLessThanOrEqual(1);
        await expect(page.getByTestId('builder-header-settings')).toBeVisible();
        await expect(page.getByTestId('builder-banner-hint')).toBeVisible();
        await expect(page.getByTestId('form-builder-canvas')).toBeVisible();
    } finally {
        cleanupOwnerFixture(form.identity);
    }
});
