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

const CLIPBOARD_KEY = 'e2e-form-share-copied-text';

/** Event dan form milik admin E2E yang sedang login, supaya editor bisa dibuka. */
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

/** Stub clipboard agar penyalinan bisa diverifikasi tanpa izin browser. */
async function stubClipboard(page: Page): Promise<void> {
    await page.addInitScript((key: string): void => {
        Object.defineProperty(window, 'isSecureContext', { configurable: true, value: true });
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText: async (text: string): Promise<void> => {
                    localStorage.setItem(key, text);
                },
            },
        });
    }, CLIPBOARD_KEY);
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

/** Buka dialog Bagikan pada form yang sudah terbit. */
async function openShareDialog(page: Page, owned: IAdminOwnedForm): Promise<void> {
    await page.goto(`/admin/events/${owned.eventId}/forms/${owned.formId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);

    const publishButton = page.getByRole('button', { name: 'Publish form', exact: true });

    if ((await publishButton.count()) > 0) {
        await publishButton.click();
    }

    await expect(page.getByRole('button', { name: 'Jadikan draft', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Bagikan', exact: true }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
}

test.describe('dialog bagikan formulir: struktur dan hierarki', () => {
    test('judul menjelaskan tujuan, nama form tampil, dan hanya satu aksi primary', async ({ page }) => {
        const owned = createAdminOwnedForm(test.info());

        try {
            await stubClipboard(page);
            await openShareDialog(page, owned);

            const dialog = page.getByRole('dialog');

            await expect(dialog.getByRole('heading', { name: 'Bagikan formulir', exact: true })).toBeVisible();
            await expect(dialog.getByTestId('share-form-title')).not.toBeEmpty();

            // Hierarki: satu aksi utama saja (salin link peserta).
            await expect(dialog.locator('button[data-variant="default"]')).toHaveCount(1);
            await expect(dialog.getByRole('button', { name: 'Salin link peserta', exact: true })).toHaveAttribute(
                'data-variant',
                'default'
            );

            // Semua fungsi terlihat sekaligus, tanpa tab/akordeon.
            await expect(dialog.getByRole('textbox', { name: 'Link peserta', exact: true })).toBeVisible();
            await expect(dialog.getByRole('button', { name: 'Buat barcode', exact: true })).toBeVisible();
            await expect(dialog.getByRole('textbox', { name: 'Alias short link', exact: true })).toBeVisible();
            await expect(dialog.getByRole('button', { name: 'Buat short link', exact: true })).toBeVisible();
            await expect(dialog.getByRole('textbox', { name: 'Link admin', exact: true })).toBeVisible();
            await expect(dialog.getByRole('button', { name: 'Salin link admin', exact: true })).toBeVisible();
            await expect(dialog.getByRole('button', { name: 'Selesai', exact: true })).toBeVisible();
        } finally {
            cleanupOwnerFixture(owned.identity);
        }
    });

    test('konfirmasi salin muncul sebagai toast dan tidak menumpuk', async ({ page }) => {
        const owned = createAdminOwnedForm(test.info());

        try {
            await stubClipboard(page);
            await openShareDialog(page, owned);

            const dialog = page.getByRole('dialog');
            const participantToast = page.locator('[data-sonner-toast]').filter({ hasText: 'Link peserta disalin.' });
            const participantCopy = dialog.getByRole('button', { name: 'Salin link peserta', exact: true });

            await expect(participantCopy).toHaveAttribute('data-copy-feedback', 'idle');
            await participantCopy.click();

            // Ikon berubah menjadi centang, lalu kembali ke ikon salin setelah ~1 detik.
            await expect(participantCopy).toHaveAttribute('data-copy-feedback', 'confirmed');

            const confirmedAt = Date.now();

            await expect(participantCopy).toHaveAttribute('data-copy-feedback', 'idle', { timeout: 5_000 });
            expect(Date.now() - confirmedAt, 'interval konfirmasi minimal 1 detik').toBeGreaterThanOrEqual(800);

            await expect(participantToast).toBeVisible();
            await expect
                .poll(() => page.evaluate((key: string): string | null => localStorage.getItem(key), CLIPBOARD_KEY))
                .toContain(`/joined/${owned.eventId}/forms/${owned.formId}/fill`);

            const adminToast = page.locator('[data-sonner-toast]').filter({ hasText: 'Link admin disalin.' });

            await dialog.getByRole('button', { name: 'Salin link admin', exact: true }).click();

            await expect(adminToast).toBeVisible();
            // Satu id toast dipakai ulang, jadi konfirmasi lama digantikan (tidak menumpuk).
            await expect(participantToast).toHaveCount(0);
            await expect(page.locator('[data-sonner-toast]')).toHaveCount(1);
        } finally {
            cleanupOwnerFixture(owned.identity);
        }
    });

    test('kontrol salin bisa diaktifkan dengan keyboard dan punya focus ring', async ({ page }) => {
        const owned = createAdminOwnedForm(test.info());

        try {
            await stubClipboard(page);
            await openShareDialog(page, owned);

            const dialog = page.getByRole('dialog');
            const copyParticipant = dialog.getByRole('button', { name: 'Salin link peserta', exact: true });

            await copyParticipant.focus();
            await expect(copyParticipant).toBeFocused();

            const focusRing = await copyParticipant.evaluate((element: HTMLElement): string => {
                const style = getComputedStyle(element);

                return `${style.boxShadow} ${style.outlineStyle}`;
            });

            expect(focusRing).not.toBe('none none');

            await page.keyboard.press('Enter');

            await expect(
                page.locator('[data-sonner-toast]').filter({ hasText: 'Link peserta disalin.' })
            ).toBeVisible();
        } finally {
            cleanupOwnerFixture(owned.identity);
        }
    });

    test('dialog fit di 320-1280 px tanpa overflow dan tombol salin memenuhi target sentuh', async ({ page }) => {
        const owned = createAdminOwnedForm(test.info());

        try {
            await stubClipboard(page);
            await openShareDialog(page, owned);

            const dialog = page.getByRole('dialog');

            for (const width of [320, 360, 390, 768, 1280]) {
                await page.setViewportSize({ width, height: 900 });
                await page.waitForTimeout(120);

                const overflow = await page.evaluate((): number => {
                    const root = document.documentElement;

                    return root.scrollWidth - root.clientWidth;
                });

                expect(overflow, `overflow horizontal pada ${width}px`).toBeLessThanOrEqual(1);

                const dialogBox = await dialog.boundingBox();

                if (dialogBox === null) throw new Error('dialog tidak punya bounding box');

                expect(dialogBox.width, `lebar dialog pada ${width}px`).toBeLessThanOrEqual(width);

                if (width === 320) {
                    const copyBox = await dialog
                        .getByRole('button', { name: 'Salin link peserta', exact: true })
                        .boundingBox();

                    if (copyBox === null) throw new Error('tombol salin tidak punya bounding box');

                    expect(copyBox.height, 'target sentuh tinggi').toBeGreaterThanOrEqual(44);
                    expect(copyBox.width, 'target sentuh lebar').toBeGreaterThanOrEqual(44);
                }
            }
        } finally {
            cleanupOwnerFixture(owned.identity);
        }
    });
});
