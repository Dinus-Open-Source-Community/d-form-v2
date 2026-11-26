import type { TestInfo } from '@playwright/test';
import type { IOwnerIdentity } from './support/marker';
import { readEnvironment } from './support/environment';
import { cleanupOwnerFixture, createOwnerFixture, expect, ownerIdentityForTest, test } from './support/fixtures';

interface IAdminOwnedEvent {
    identity: IOwnerIdentity;
    eventId: string;
}

function createAdminOwnedEvent(testInfo: TestInfo): IAdminOwnedEvent {
    const identity = ownerIdentityForTest(testInfo);
    const owner = createOwnerFixture(identity, readEnvironment().adminEmail);

    return { identity, eventId: owner.eventId };
}

test('modal tujuan form tampil sebelum membuat form dan D-Kelas membuka pilihan mode', async ({ page }, testInfo) => {
    test.setTimeout(60_000);

    const owned = createAdminOwnedEvent(testInfo);

    try {
        const response = await page.goto(`/admin/events/${owned.eventId}`, { waitUntil: 'domcontentloaded' });

        expect(response?.status() ?? 0).toBe(200);
        await page.waitForFunction(
            (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
            undefined,
            { timeout: 20_000 }
        );

        await expect(page.getByTestId('add-form-button')).toBeVisible({ timeout: 20_000 });
        await page.getByTestId('add-form-button').click();

        await expect(page.getByRole('dialog')).toBeVisible();
        await expect(page.getByText('Form ini untuk apa?')).toBeVisible();

        await page.getByRole('button', { name: /D-Kelas/ }).click();
        await page.getByRole('button', { name: 'Lanjut' }).click();

        await expect(page.getByText('Bagaimana peserta mendaftar?')).toBeVisible();
        await expect(page.getByRole('button', { name: /^Single/ })).toBeVisible();
        await expect(page.getByRole('button', { name: /^Bundle/ })).toBeVisible();
        await expect(page.getByRole('button', { name: /^Team/ })).toBeVisible();

        await page.getByRole('button', { name: /^Team/ }).click();
        await page.getByRole('button', { name: 'Buat form' }).click();

        await expect(page).toHaveURL(/\/forms\/create/);
        await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
    } finally {
        cleanupOwnerFixture(owned.identity);
    }
});

test('pendaftaran menampilkan langkah mode sebelum membuat form', async ({ page }, testInfo) => {
    test.setTimeout(60_000);

    const owned = createAdminOwnedEvent(testInfo);

    try {
        await page.goto(`/admin/events/${owned.eventId}`, { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(
            (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
            undefined,
            { timeout: 20_000 }
        );

        await page.getByTestId('add-form-button').click();
        await expect(page.getByRole('dialog')).toBeVisible();

        await page.getByRole('button', { name: /Pendaftaran/ }).click();
        await page.getByRole('button', { name: 'Lanjut' }).click();

        await expect(page.getByText('Bagaimana peserta mendaftar?')).toBeVisible();
        await expect(page.getByRole('button', { name: /^Team/ })).toBeVisible();

        await page.getByRole('button', { name: /^Team/ }).click();
        await page.getByRole('button', { name: 'Buat form' }).click();

        await expect(page).toHaveURL(/\/forms\/create/);
    } finally {
        cleanupOwnerFixture(owned.identity);
    }
});
