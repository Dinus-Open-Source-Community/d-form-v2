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

/** PNG 4x4 hasil GD: lolos deteksi tipe server dan bisa didekode `createImageBitmap`. */
const VALID_PNG_BYTES: Buffer = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPkqjjBAANMDEgANwcAQLoBUlac4vkAAAAASUVORK5CYII=',
    'base64'
);

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

async function openBuilder(page: Page, form: IAdminOwnedForm): Promise<void> {
    const response = await page.goto(`/admin/events/${form.eventId}/forms/${form.formId}`, {
        waitUntil: 'domcontentloaded',
    });

    expect(response?.status() ?? 0).toBe(200);
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

test.describe('header form builder', () => {
    test('kartu header berlabel dengan counter dan tanpa tanda pisah panjang', async ({ page }, testInfo) => {
        const form = createAdminOwnedForm(testInfo);

        try {
            await openBuilder(page, form);

            const header = page.getByTestId('builder-header-settings');
            await expect(header).toBeVisible();

            await expect(header.getByLabel(/Judul form/)).toBeVisible();
            await expect(header.getByLabel(/Deskripsi singkat/)).toBeVisible();
            await expect(page.getByTestId('form-title-counter')).toHaveText(/^\d+\/200$/);
            await expect(page.getByTestId('form-description-counter')).toHaveText(/^\d+\/500$/);

            const hint = await page.getByTestId('builder-banner-hint').textContent();
            expect(hint ?? '').toContain('Rasio 3:1');
            expect(hint ?? '').toContain('5,0 MB');
            expect(hint ?? '').not.toContain('\u2014');
            expect(await page.getByText('banner-form', { exact: true }).count()).toBe(0);
        } finally {
            cleanupOwnerFixture(form.identity);
        }
    });

    test('counter judul mengikuti isi dan berhenti di batas 200 karakter', async ({ page }, testInfo) => {
        const form = createAdminOwnedForm(testInfo);

        try {
            await openBuilder(page, form);

            await page.locator('#f-title').fill('x'.repeat(250));

            expect((await page.locator('#f-title').inputValue()).length).toBe(200);
            await expect(page.getByTestId('form-title-counter')).toHaveText('200/200');
        } finally {
            cleanupOwnerFixture(form.identity);
        }
    });

    test('banner hanya punya satu tombol hapus setelah unggah', async ({ page }, testInfo) => {
        test.setTimeout(60_000);

        const form = createAdminOwnedForm(testInfo);

        try {
            await openBuilder(page, form);

            const uploadResponse = page.waitForResponse(
                (response): boolean =>
                    response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/images')
            );

            await page
                .locator('input[type=file][accept="image/png,image/jpeg,image/gif"]')
                .first()
                .setInputFiles({ name: 'banner.png', mimeType: 'image/png', buffer: VALID_PNG_BYTES });

            expect((await uploadResponse).status(), 'banner tersimpan').toBe(200);

            await expect(page.getByTestId('builder-banner-preview')).toBeVisible({ timeout: 20_000 });
            await expect(page.locator('button[aria-label="Hapus banner"]')).toHaveCount(1);
        } finally {
            cleanupOwnerFixture(form.identity);
        }
    });
});
