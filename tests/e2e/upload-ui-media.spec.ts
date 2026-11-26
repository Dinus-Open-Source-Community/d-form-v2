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

/** Avatar profil dirender `UserAvatarFallback` di kepala halaman, bukan di seksi Profil. */
const PROFILE_AVATAR_IMAGE = '[data-testid="profile-header"] span.size-20.rounded-full img';

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

/** Buka profil dan pastikan Vue sudah menempel sebelum menyentuh kontrol. */
async function openProfileForAvatar(page: Page): Promise<void> {
    const response = await page.goto('/profile', { waitUntil: 'domcontentloaded' });

    expect(response?.status() ?? 0).toBe(200);
    await expect(page.getByTestId('profile-header')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

/**
 * Kembalikan avatar admin ke kondisi sebelum test. Test ini dijalankan pada akun
 * admin bersama, jadi gambar awal (bila ada) diunggah ulang dari server, dan
 * avatar yang tidak ada di awal dipastikan terhapus.
 */
async function restoreProfileAvatar(page: Page, originalSrc: string | null): Promise<void> {
    await openProfileForAvatar(page);

    const avatarImage = page.locator(PROFILE_AVATAR_IMAGE);
    const removeButton = page.getByTestId('profile-avatar-remove');
    const saveButton = page.getByTestId('profile-avatar-save');

    if (originalSrc === null) {
        if ((await removeButton.count()) === 0) return;

        await removeButton.click();

        const deleteResponse = page.waitForResponse(
            (response): boolean =>
                response.request().method() === 'DELETE' && new URL(response.url()).pathname === '/profile/avatar'
        );

        await saveButton.click();
        await deleteResponse;
        await expect(avatarImage).toHaveCount(0, { timeout: 20_000 });

        return;
    }

    const currentSrc = await avatarImage.getAttribute('src');

    if (currentSrc === originalSrc) return;

    const absoluteUrl = new URL(originalSrc, page.url()).toString();
    const bytes = await (await page.request.get(absoluteUrl)).body();
    const extension = new URL(absoluteUrl).pathname.split('.').pop() ?? 'png';

    await page.getByTestId('profile-avatar-input').setInputFiles({
        name: `restore-avatar.${extension}`,
        mimeType: extension === 'png' ? 'image/png' : 'image/jpeg',
        buffer: bytes,
    });

    const uploadResponse = page.waitForResponse(
        (response): boolean =>
            response.request().method() === 'POST' && new URL(response.url()).pathname === '/profile/avatar'
    );

    await saveButton.click();
    await uploadResponse;
    await expect(avatarImage).toHaveAttribute('src', /\/storage\//, { timeout: 20_000 });
}

test.describe('kartu unggah media', () => {
    test('avatar profil: unggah, pratinjau dari URL server, lalu hapus', async ({ page }) => {
        test.setTimeout(60_000);

        await openProfileForAvatar(page);

        const avatarImage = page.locator(PROFILE_AVATAR_IMAGE);
        const originalSrc = (await avatarImage.count()) === 0 ? null : await avatarImage.getAttribute('src');
        const avatarUploads: string[] = [];

        page.on('request', (request): void => {
            if (request.method() === 'POST' && new URL(request.url()).pathname === '/profile/avatar') {
                avatarUploads.push(request.url());
            }
        });

        try {
            // Memilih berkas hanya menyiapkan pratinjau: belum ada request unggah.
            await page
                .getByTestId('profile-avatar-input')
                .setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: VALID_PNG_BYTES });

            await expect(page.getByText('Foto baru siap disimpan.')).toBeVisible();
            await expect(page.getByTestId('profile-avatar-save')).toBeVisible();
            expect(avatarUploads, 'memilih berkas tidak boleh mengunggah apa pun').toEqual([]);

            const uploadResponse = page.waitForResponse(
                (response): boolean =>
                    response.request().method() === 'POST' && new URL(response.url()).pathname === '/profile/avatar'
            );

            await page.getByTestId('profile-avatar-save').click();

            // Inertia membalas redirect (2xx/3xx) setelah upload multipart diproses.
            const uploadStatus = (await uploadResponse).status();
            expect(uploadStatus, 'avatar tersimpan').toBeGreaterThanOrEqual(200);
            expect(uploadStatus, 'avatar tersimpan').toBeLessThan(400);

            // Pratinjau memakai URL server dari props yang diperbarui, bukan object URL.
            await expect(avatarImage).toHaveCount(1, { timeout: 20_000 });
            await expect(avatarImage).toHaveAttribute('src', /\/storage\//);

            // Hapus avatar tersimpan, lalu simpan lewat baris tertunda yang sama.
            await page.getByTestId('profile-avatar-remove').click();
            await expect(page.getByText('Foto profil akan dihapus setelah')).toBeVisible();

            const deleteResponse = page.waitForResponse(
                (response): boolean =>
                    response.request().method() === 'DELETE' && new URL(response.url()).pathname === '/profile/avatar'
            );

            await page.getByTestId('profile-avatar-save').click();

            const deleteStatus = (await deleteResponse).status();
            expect(deleteStatus, 'avatar dihapus').toBeGreaterThanOrEqual(200);
            expect(deleteStatus, 'avatar dihapus').toBeLessThan(400);
            await expect(avatarImage).toHaveCount(0, { timeout: 20_000 });
        } finally {
            await restoreProfileAvatar(page, originalSrc);
        }
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
