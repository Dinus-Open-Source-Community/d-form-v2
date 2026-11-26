import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { readEnvironment } from './support/environment';
import { resolveStatePath } from './support/authGuard';
import {
    archiveEvent,
    claimEventForAdmin,
    openAuthenticatedPath,
    readEventState,
    signInAsNonSuperAdmin,
} from './support/eventAdmin';

const ACTIVE_PATH = '/admin/events';
const ARCHIVED_PATH = '/admin/events/archived';
const EVENT_MENU_LABEL = 'Menu acara';
const ARCHIVED_BADGE_LABEL = 'Diarsipkan';

/** Judul event fixture mengikuti `defaultFixtureRequest` di `support/fixtures.ts`. */
function fixtureTitle(ownerKey: string): string {
    return `E2E fixture ${ownerKey}`;
}

function activeSearchPath(title: string): string {
    return `${ACTIVE_PATH}?search=${encodeURIComponent(title)}`;
}

function archivedSearchPath(title: string): string {
    return `${ARCHIVED_PATH}?search=${encodeURIComponent(title)}`;
}

function sidebar(page: Page): Locator {
    return page.locator('[data-slot="sidebar"]');
}

/**
 * Kartu event yang memuat judul tertentu. Root kartu adalah div terdalam yang
 * memuat judul sekaligus tombol menu aksi, sehingga tidak bergantung pada class CSS.
 */
function eventCardFor(page: Page, title: string): Locator {
    return page
        .locator('div')
        .filter({ has: page.getByRole('heading', { name: title, exact: true }) })
        .filter({ has: page.getByRole('button', { name: EVENT_MENU_LABEL, exact: true }) })
        .last();
}

test.describe('DFORM-ARCHIVED-EVENTS navigasi, daftar, restore, hapus permanen', () => {
    // Satu form + satu field + satu submission memberi anak nyata untuk purge.
    test.use({ fixtureFormCount: 1, fixtureFieldCount: 1, fixtureSubmissionCount: 1 });

    test('grup sidebar Acara membuka daftar aktif lalu arsip', async ({ page }): Promise<void> => {
        await openAuthenticatedPath(page, '/dashboard');

        const navigation = sidebar(page);
        const groupButton = navigation.getByRole('button', { name: 'Acara', exact: true });

        await expect(groupButton).toHaveAttribute('aria-expanded', 'false');
        await groupButton.click();
        await expect(groupButton).toHaveAttribute('aria-expanded', 'true');

        const activeLink = navigation.getByRole('link', { name: 'Semua acara', exact: true });
        const archivedLink = navigation.getByRole('link', { name: 'Arsip acara', exact: true });

        await expect(activeLink).toBeVisible();
        await expect(archivedLink).toBeVisible();

        await activeLink.click();
        await expect(page).toHaveURL(/\/admin\/events$/);
        await expect(groupButton).toHaveAttribute('aria-expanded', 'true');

        await archivedLink.click();
        await expect(page).toHaveURL(/\/admin\/events\/archived$/);
    });

    test('acara terarsip hanya tampil di halaman arsip', async ({ page, fixtureEvent }): Promise<void> => {
        const title = fixtureTitle(fixtureEvent.ownerKey);

        claimEventForAdmin(fixtureEvent.eventId);
        await openAuthenticatedPath(page, `${ACTIVE_PATH}/${fixtureEvent.eventId}`);
        await archiveEvent(page.context(), fixtureEvent.eventId);
        expect(readEventState(fixtureEvent.eventId).trashed).toBe(true);

        await openAuthenticatedPath(page, archivedSearchPath(title));
        const card = eventCardFor(page, title);

        await expect(card.getByRole('heading', { name: title, exact: true })).toBeVisible();
        await expect(card.getByText(ARCHIVED_BADGE_LABEL, { exact: true })).toBeVisible();
        // Satu baris hasil pencarian = satu halaman: pagination harus disembunyikan.
        await expect(page.getByRole('navigation', { name: 'pagination' })).toHaveCount(0);

        await openAuthenticatedPath(page, activeSearchPath(title));
        await expect(page.getByRole('heading', { name: title, exact: true })).toHaveCount(0);
        await expect(page.getByText('Tidak ada acara', { exact: true })).toBeVisible();
    });

    test('pulihkan mengembalikan acara ke daftar aktif dan keluar dari arsip', async ({
        page,
        fixtureEvent,
    }): Promise<void> => {
        test.setTimeout(60_000);
        const title = fixtureTitle(fixtureEvent.ownerKey);

        claimEventForAdmin(fixtureEvent.eventId);
        await openAuthenticatedPath(page, `${ACTIVE_PATH}/${fixtureEvent.eventId}`);
        await archiveEvent(page.context(), fixtureEvent.eventId);

        await openAuthenticatedPath(page, archivedSearchPath(title));
        const card = eventCardFor(page, title);

        await card.getByRole('button', { name: EVENT_MENU_LABEL, exact: true }).click();
        await card.getByRole('button', { name: 'Pulihkan', exact: true }).click();

        const dialog = page.getByRole('alertdialog');

        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Pulihkan', exact: true }).click();

        // `return_to=archived` harus membawa halaman kembali ke arsip, bukan ke daftar aktif.
        await expect(page).toHaveURL(/\/admin\/events\/archived$/);
        await expect(page.getByRole('heading', { name: title, exact: true })).toHaveCount(0);
        expect(readEventState(fixtureEvent.eventId).trashed).toBe(false);

        await openAuthenticatedPath(page, activeSearchPath(title));
        await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    });

    test('hapus permanen baru aktif setelah judul diketik dan menghapus baris DB', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }): Promise<void> => {
        test.setTimeout(60_000);
        const title = fixtureTitle(fixtureEvent.ownerKey);

        claimEventForAdmin(fixtureEvent.eventId);
        await openAuthenticatedPath(page, `${ACTIVE_PATH}/${fixtureEvent.eventId}`);
        await archiveEvent(page.context(), fixtureEvent.eventId);
        // `formsTotal` memakai `withTrashed()`: ini hanya membuktikan form fixture ada,
        // bukan bahwa arsip meng-cascade-nya (cascade diuji di spec tersendiri).
        expect(fixtureForm.forms).toHaveLength(1);
        expect(readEventState(fixtureEvent.eventId).formsTotal).toBe(1);

        await openAuthenticatedPath(page, archivedSearchPath(title));
        const card = eventCardFor(page, title);

        await card.getByRole('button', { name: EVENT_MENU_LABEL, exact: true }).click();
        await card.getByRole('button', { name: 'Hapus permanen', exact: true }).click();

        const dialog = page.getByRole('alertdialog');
        const confirm = dialog.getByRole('button', { name: 'Hapus permanen', exact: true });
        const titleInput = dialog.getByLabel('Ketik judul acara untuk konfirmasi.');

        await expect(dialog).toBeVisible();
        await expect(confirm).toBeDisabled();

        // Judul sebagian (walau hampir sama) tidak boleh membuka gate konfirmasi.
        await titleInput.fill(title.slice(0, Math.max(1, title.length - 1)));
        await expect(confirm).toBeDisabled();

        await titleInput.fill(title);
        await expect(confirm).toBeEnabled();
        await confirm.click();

        await expect(page).toHaveURL(new RegExp(`${ARCHIVED_PATH}$`));
        await expect(page.getByRole('heading', { name: title, exact: true })).toHaveCount(0);

        const state = readEventState(fixtureEvent.eventId);
        expect(state.eventExists).toBe(false);
        expect(state.formsTotal).toBe(0);
    });
});

test.describe('DFORM-ARCHIVED-EVENTS scoping daftar arsip per pemilik', () => {
    // Project desktop memakai storageState super-admin; spec ini butuh sesi admin biasa.
    test.use({ storageState: { cookies: [], origins: [] } });

    // Tanpa kredensial non-admin, login memakai string kosong dan hasilnya tidak bermakna.
    test.beforeAll((): void => {
        const environment = readEnvironment();

        if (environment.nonAdminEmail === '' || environment.nonAdminPassword === '') {
            throw new Error(
                'E2E_NON_ADMIN_EMAIL and E2E_NON_ADMIN_PASSWORD must both be set to the seeded (role `admin`) credentials for this spec.'
            );
        }
    });

    test('admin non-pemilik tidak melihat acara terarsip milik user lain', async ({
        page,
        browser,
        fixtureEvent,
    }): Promise<void> => {
        test.setTimeout(90_000);
        const title = fixtureTitle(fixtureEvent.ownerKey);

        claimEventForAdmin(fixtureEvent.eventId);

        // Kontrol positif: pemilik/super-admin melihat baris yang sama di arsip.
        const adminContext = await browser.newContext({
            baseURL: readEnvironment().baseUrl,
            storageState: resolveStatePath(),
        });

        try {
            await archiveEvent(adminContext, fixtureEvent.eventId);

            const adminPage = await adminContext.newPage();

            await openAuthenticatedPath(adminPage, archivedSearchPath(title));
            await expect(adminPage.getByRole('heading', { name: title, exact: true })).toBeVisible();
        } finally {
            await adminContext.close();
        }

        expect(readEventState(fixtureEvent.eventId).trashed).toBe(true);

        await signInAsNonSuperAdmin(page);
        await page.goto(archivedSearchPath(title), { waitUntil: 'domcontentloaded' });

        await expect(page.getByRole('heading', { name: title, exact: true })).toHaveCount(0);
        await expect(page.getByText('Tidak ada acara di arsip', { exact: true })).toBeVisible();
    });
});
