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

/** Avatar root memakai kelas ukuran dari `UserAvatarFallback` di halaman profil. */
const PROFILE_AVATAR_IMAGE = 'span.size-20.rounded-full.border img';

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

test.describe('kartu unggah media', () => {
    test('avatar profil: unggah, pratinjau dari URL server, lalu hapus', async ({ page }) => {
        test.setTimeout(60_000);

        const response = await page.goto('/profile', { waitUntil: 'domcontentloaded' });
        expect(response?.status() ?? 0).toBe(200);
        await expect(page.getByRole('heading', { name: 'Foto Profil' })).toBeVisible({ timeout: 20_000 });
        await waitForHydration(page);

        const avatarImage = page.locator(PROFILE_AVATAR_IMAGE);

        // Kartu unggah: memilih berkas belum mengirim request (unggahan ikut Simpan Perubahan).
        await page
            .locator('input[type=file][accept="image/jpeg,image/png,image/gif,image/webp"]')
            .setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: VALID_PNG_BYTES });

        await expect(page.getByText('Foto baru sudah dipilih.')).toBeVisible();

        const uploadResponse = page.waitForResponse(
            (response): boolean =>
                response.request().method() === 'POST' && new URL(response.url()).pathname === '/profile/avatar'
        );

        await page.getByRole('button', { name: 'Simpan Perubahan' }).click();

        // Inertia membalas redirect (2xx/3xx) setelah upload multipart diproses.
        const uploadStatus = (await uploadResponse).status();
        expect(uploadStatus, 'avatar tersimpan').toBeGreaterThanOrEqual(200);
        expect(uploadStatus, 'avatar tersimpan').toBeLessThan(400);

        // Pratinjau memakai URL server dari props yang diperbarui, bukan object URL.
        await expect(avatarImage).toHaveCount(1, { timeout: 20_000 });
        await expect(avatarImage).toHaveAttribute('src', /\/storage\//);

        // Hapus avatar tersimpan, lalu simpan.
        await page.getByRole('button', { name: 'Hapus', exact: true }).click();
        await expect(page.getByText('Foto profil akan dihapus setelah')).toBeVisible();

        const deleteResponse = page.waitForResponse(
            (response): boolean =>
                response.request().method() === 'DELETE' && new URL(response.url()).pathname === '/profile/avatar'
        );

        await page.getByRole('button', { name: 'Simpan Perubahan' }).click();

        const deleteStatus = (await deleteResponse).status();
        expect(deleteStatus, 'avatar dihapus').toBeGreaterThanOrEqual(200);
        expect(deleteStatus, 'avatar dihapus').toBeLessThan(400);
        await expect(avatarImage).toHaveCount(0, { timeout: 20_000 });
    });

    test('banner builder: unggah, gambar dari URL server, lalu hapus', async ({ page }, testInfo) => {
        test.setTimeout(60_000);

        const form = createAdminOwnedForm(testInfo);

        try {
            const response = await page.goto(`/admin/events/${form.eventId}/forms/${form.formId}`, {
                waitUntil: 'domcontentloaded',
            });
            expect(response?.status() ?? 0).toBe(200);
            await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
            await waitForHydration(page);

            const bannerImage = page.getByAltText('Pratinjau banner form');
            await expect(bannerImage).toHaveCount(0);

            const uploadResponse = page.waitForResponse(
                (response): boolean =>
                    response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/images')
            );

            await page
                .locator('input[type=file][accept="image/png,image/jpeg,image/gif"]')
                .first()
                .setInputFiles({ name: 'banner.png', mimeType: 'image/png', buffer: VALID_PNG_BYTES });

            expect((await uploadResponse).status(), 'banner tersimpan').toBe(200);

            // Hasil unggah tampil dari URL server pada pratinjau 3:1, bukan object URL.
            await expect(bannerImage).toHaveCount(1, { timeout: 20_000 });
            await expect(bannerImage).toHaveAttribute('src', /\/storage\//);

            await page.locator('button[aria-label="Hapus banner"]').first().click();

            await expect(bannerImage).toHaveCount(0);
            await expect(page.getByRole('button', { name: 'Tambahkan banner form' })).toBeVisible();
        } finally {
            cleanupOwnerFixture(form.identity);
        }
    });
});
