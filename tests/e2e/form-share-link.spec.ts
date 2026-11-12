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

/** Event and form owned by the authenticated E2E admin for editor authorization. */
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

async function assertNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate((): boolean => {
        return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });

    expect(overflow, 'form-share editor and dialog should fit the viewport width').toBe(false);
}

async function ensurePublished(page: Page): Promise<void> {
    const publishButton = page.getByRole('button', { name: 'Publish form', exact: true });

    if ((await publishButton.count()) > 0) {
        await publishButton.click();
    }

    await expect(page.getByRole('button', { name: 'Jadikan draft', exact: true })).toBeVisible();
}

test.describe('tautan berbagi form', () => {
    test('Bagikan supports links, aliases, barcode, draft protection, keyboard and mobile fit', async ({ page }) => {
        const owned = createAdminOwnedForm(test.info());

        try {
            const consoleErrors: string[] = [];
            page.on('console', (message): void => {
                if (message.type() === 'error') consoleErrors.push(message.text());
            });
            page.on('pageerror', (error): void => {
                consoleErrors.push(error.message);
            });
            page.on('requestfailed', (request): void => {
                const failure = request.failure();
                const failureText = failure?.errorText ?? 'Unknown request failure';

                consoleErrors.push(`Request failed: ${request.url()} (${failureText})`);
            });
            await page.addInitScript((): void => {
                Object.defineProperty(window, 'isSecureContext', {
                    configurable: true,
                    value: true,
                });
                Object.defineProperty(navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (text: string): Promise<void> => {
                            localStorage.setItem('e2e-form-share-copied-text', text);
                        },
                    },
                });
            });
            // Isolate optional external analytics so DNS availability cannot affect app behavior.
            await page.route('https://seeyu.doscom.org/script.js', async (route): Promise<void> => {
                await route.fulfill({ status: 200, contentType: 'application/javascript', body: '' });
            });
            await page.goto(`/admin/events/${owned.eventId}/forms/${owned.formId}`, {
                waitUntil: 'domcontentloaded',
            });
            await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
            await waitForHydration(page);
            await ensurePublished(page);

            const shareButton = page.getByRole('button', { name: 'Bagikan', exact: true });
            await expect(shareButton).toHaveCount(1);
            await assertNoHorizontalOverflow(page);

            await shareButton.focus();
            await page.keyboard.press('Enter');

            const dialog = page.getByRole('dialog');
            await expect(dialog).toBeVisible();
            await expect
                .poll(() =>
                    dialog.evaluate((element: HTMLElement): boolean => element.contains(document.activeElement))
                )
                .toBe(true);
            await assertNoHorizontalOverflow(page);
            await page.keyboard.press('Escape');
            await expect(dialog).toHaveCount(0);
            await expect(shareButton).toBeFocused();

            await shareButton.click();
            await expect(dialog).toBeVisible();

            const origin = new URL(page.url()).origin;
            const expectedParticipantUrl = `${origin}/joined/${owned.eventId}/forms/${owned.formId}/fill`;
            const expectedAdminUrl = `${origin}/admin/events/${owned.eventId}/forms/${owned.formId}?tab=editor`;

            await dialog.getByRole('button', { name: 'Salin link peserta', exact: true }).click();
            await expect
                .poll(() => page.evaluate(() => localStorage.getItem('e2e-form-share-copied-text')))
                .toBe(expectedParticipantUrl);

            await dialog.getByRole('button', { name: 'Salin link admin', exact: true }).click();
            await expect
                .poll(() => page.evaluate(() => localStorage.getItem('e2e-form-share-copied-text')))
                .toBe(expectedAdminUrl);

            const firstAlias = `e2e-${owned.formId.slice(0, 8)}`;
            const secondAlias = `edit-${owned.formId.slice(0, 8)}`;
            const aliasInput = dialog.getByRole('textbox', { name: 'Alias short link' });
            await aliasInput.fill(firstAlias);
            await dialog.getByRole('button', { name: 'Buat short link', exact: true }).click();

            const shortLink = dialog.getByRole('textbox', { name: 'Short link', exact: true });
            await expect(shortLink).toHaveValue(`${origin}/s/${firstAlias}`);
            const oldAliasResponse = await page.request.get(`${origin}/s/${firstAlias}`, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect(oldAliasResponse.status()).toBe(302);
            const aliasRedirect = oldAliasResponse.headers()['location'];
            if (aliasRedirect === undefined) throw new Error('Alias aktif tidak mengembalikan tujuan redirect.');
            expect(new URL(aliasRedirect, origin).toString()).toBe(expectedParticipantUrl);

            await aliasInput.fill(secondAlias);
            await dialog.getByRole('button', { name: 'Simpan alias', exact: true }).click();
            await expect(shortLink).toHaveValue(`${origin}/s/${secondAlias}`);

            const releasedAliasResponse = await page.request.get(`${origin}/s/${firstAlias}`, {
                maxRedirects: 0,
                failOnStatusCode: false,
            });
            expect(releasedAliasResponse.status()).toBe(404);

            await dialog.getByRole('button', { name: 'Salin short link', exact: true }).click();
            await expect
                .poll(() => page.evaluate(() => localStorage.getItem('e2e-form-share-copied-text')))
                .toBe(`${origin}/s/${secondAlias}`);

            await dialog.getByRole('button', { name: 'Generate barcode', exact: true }).click();
            const barcode = dialog.locator('img[alt^="Barcode untuk "]');
            await expect(barcode).toBeVisible();
            await expect
                .poll(() => barcode.evaluate((image: HTMLImageElement): number => image.naturalWidth))
                .toBeGreaterThan(0);

            const downloadPromise = page.waitForEvent('download');
            await dialog.getByRole('link', { name: 'Unduh barcode PNG', exact: true }).click();
            const download = await downloadPromise;
            expect(download.suggestedFilename()).toBe('formulir-barcode.png');

            await page.keyboard.press('Escape');
            await expect(dialog).toHaveCount(0);

            const unsavedTitle = `E2E share state ${owned.formId.slice(0, 8)}`;
            await page.locator('#f-title').fill(unsavedTitle);
            const formSettingsPath = `/admin/events/${owned.eventId}/forms/${owned.formId}`;
            const draftAutosaveResponse = page.waitForResponse((response): boolean => {
                const request = response.request();

                return (
                    request.method() === 'PUT' &&
                    new URL(request.url()).pathname === formSettingsPath &&
                    (request.postData() ?? '').includes('"autosave":true')
                );
            });
            await page.getByRole('button', { name: 'Jadikan draft', exact: true }).click();
            await expect(page.getByRole('button', { name: 'Publish form', exact: true })).toBeVisible();
            await expect(page.locator('#f-title')).toHaveValue(unsavedTitle);
            expect((await draftAutosaveResponse).ok()).toBe(true);
            await expect(page.getByTestId('autosave-status')).toContainText('Tersimpan');

            await shareButton.click();
            await expect(dialog).toBeVisible();
            await expect(
                dialog.getByText('Terbitkan formulir terlebih dahulu untuk membagikan link peserta.')
            ).toBeVisible();
            await expect(dialog.getByRole('button', { name: 'Salin link peserta', exact: true })).toBeDisabled();
            await expect(dialog.getByRole('button', { name: 'Generate barcode', exact: true })).toBeDisabled();
            await expect(dialog.getByRole('textbox', { name: 'Alias short link' })).toBeDisabled();
            await expect(dialog.getByRole('button', { name: 'Simpan alias', exact: true })).toBeDisabled();
            await expect(dialog.getByRole('button', { name: 'Salin short link', exact: true })).toBeDisabled();
            await expect(dialog.getByRole('button', { name: 'Salin link admin', exact: true })).toBeEnabled();
            await expect(dialog.getByRole('heading', { name: 'Barcode peserta', exact: true })).toHaveCount(0);
            await expect(dialog.getByRole('link', { name: 'Unduh barcode PNG', exact: true })).toHaveCount(0);
            await assertNoHorizontalOverflow(page);

            await page.reload({ waitUntil: 'domcontentloaded' });
            await waitForHydration(page);
            await expect(page.locator('#f-title')).toHaveValue(unsavedTitle);

            expect(consoleErrors, 'page should not emit browser console errors').toEqual([]);
        } finally {
            cleanupOwnerFixture(owned.identity);
        }
    });
});
