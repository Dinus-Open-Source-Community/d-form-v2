import type { Locator, Page } from '@playwright/test';
import { clearDuCourses, seedDuCourse } from './support/duCourses';
import { readEnvironment } from './support/environment';
import { cleanupOwnerFixture, createOwnerFixture, expect, ownerIdentityForTest, test } from './support/fixtures';
import { closeFieldDrawer, fieldCardTriggers, openFieldDrawer } from './support/builderFieldCard';

/** Prefix uid bersama: semua baris cache yang dibuat spec ini dibersihkan lewat satu prefix. */
const SEEDED_UID_PREFIX = 'e2e-du-';
const GOLANG_COURSE = { uid: 'e2e-du-golang', title: 'Golang E2E' };
const SQL_COURSE = { uid: 'e2e-du-sql', title: 'SQL E2E' };
/** Kelas yang hanya ada di respons sync palsu, tidak pernah masuk cache server. */
const SYNCED_COURSE = { uid: 'e2e-du-synced', title: 'Kelas Sync E2E' };
/**
 * Balasan sync palsu: membuktikan UI memakai respons endpoint, bukan cache.
 * Golang ikut disertakan agar pilihan yang tersimpan tetap bertahan setelah sync.
 */
const SYNC_RESPONSE_BODY = {
    courses: [GOLANG_COURSE, SYNCED_COURSE],
    synced_at: '2026-11-24T00:00:00Z',
};
const PUBLISHED_FORM_PATTERN = /^\/admin\/events\/[0-9a-f-]+\/forms\/[0-9a-f-]{36}$/;
const UUID_PATTERN = /^[0-9a-f-]{36}$/;

/** Tunggu Vue benar-benar mount; SSR sudah merender markup sebelum handler aktif. */
async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
}

/**
 * Pilih tanggal tutup di bulan berikutnya: form harus masih terbuka saat halaman isi dibuka.
 * Popover bisa perlu dibuka ulang, jadi percobaan diulang sekali.
 */
async function pickFutureClosedAt(page: Page): Promise<void> {
    for (let attempt = 0; attempt < 2; attempt += 1) {
        await page.locator('#create-closed-date').click();
        await page.locator('[data-slot="calendar-next-button"]').click();

        const cell = page.locator('[data-reka-calendar-cell-trigger]:not([disabled])').nth(15);

        await expect(cell).toBeVisible();
        await cell.click();

        const text = (await page.locator('#create-closed-date').innerText()).trim();

        if (text !== '' && !/pilih tanggal/i.test(text)) return;
    }

    throw new Error('tanggal tutup tidak terpasang setelah dua percobaan');
}

/** Tombol simpan yang benar-benar terlihat di header tab (desktop). */
function saveControls(page: Page): Locator {
    return page.getByRole('button', { name: /^(Simpan|Save Form)$/ }).filter({ visible: true });
}

/**
 * Pilih tepat satu kelas lewat modal picker. Draft dikosongkan dulu bila tombolnya aktif,
 * lalu hasil filter diisi judul kelas dan kartu yang cocok diklik.
 */
async function chooseOnlyCourse(page: Page, course: { uid: string; title: string }): Promise<void> {
    const picker = page.getByRole('dialog').filter({ hasText: 'Pilih kelas DU' });

    await expect(picker).toBeVisible();

    const clear = picker.getByRole('button', { name: 'Kosongkan' });

    if ((await clear.count()) > 0 && (await clear.isEnabled())) await clear.click();

    await picker.getByLabel('Cari kelas DU').fill(course.title);

    const card = picker.getByRole('button', { name: new RegExp(course.title) });

    await card.click();
    await expect(card).toHaveAttribute('aria-pressed', 'true');
    await picker.getByRole('button', { name: 'Simpan' }).click();
    await expect(picker).toBeHidden();
}

test.describe('D-Kelas: create, sync kelas, publish, isi form', () => {
    test('E2E-DU-01-create-sync-fill', async ({ page }) => {
        const identity = ownerIdentityForTest(test.info());
        const owned = createOwnerFixture(identity, readEnvironment().adminEmail);

        try {
            seedDuCourse(GOLANG_COURSE.uid, GOLANG_COURSE.title);
            seedDuCourse(SQL_COURSE.uid, SQL_COURSE.title);

            await page.goto(`/admin/events/${owned.eventId}/forms/create?purpose=d_kelas&mode=single`, {
                waitUntil: 'domcontentloaded',
            });
            await waitForHydration(page);

            // Field sistem D-Kelas muncul di kanvas tanpa aksi user.
            await expect(page.getByRole('button', { name: 'Ubah field Kelas' })).toBeVisible();

            await page.getByRole('tab', { name: /pengaturan form/i }).click();
            await expect(page.locator('#create-purpose')).toContainText('D-Kelas');
            await page.getByRole('tab', { name: 'Editor' }).click();

            // Kanvas hanya punya satu field: sistem Kelas.
            await expect(fieldCardTriggers(page)).toHaveCount(1);

            const inspector = await openFieldDrawer(page, 0);
            await expect(inspector).toContainText(GOLANG_COURSE.title);
            await expect(inspector).toContainText(SQL_COURSE.title);

            await page.route('**/admin/du/courses/sync', async (route): Promise<void> => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(SYNC_RESPONSE_BODY),
                });
            });

            // Sync kini berada di dalam dialog pemilih kelas.
            await inspector.getByRole('button', { name: 'Tambah kelas' }).click();
            const picker = page.getByRole('dialog').filter({ hasText: 'Pilih kelas DU' });
            await expect(picker.getByRole('button', { name: 'Sync kelas' })).toBeVisible();
            await picker.getByRole('button', { name: 'Sync kelas' }).click();
            await expect(picker.getByText(SYNCED_COURSE.title, { exact: true })).toBeVisible();

            // Pilih subset kelas lewat modal: pilihan eksplisit disimpan di metadata field.
            await chooseOnlyCourse(page, GOLANG_COURSE);
            await expect(inspector).toContainText(GOLANG_COURSE.title);
            await expect(inspector).not.toContainText(SQL_COURSE.title);

            // Hapus dari kartu terpilih: metadata menjadi array kosong.
            await inspector.getByRole('button', { name: `Hapus kelas ${GOLANG_COURSE.title}` }).click();
            await expect(inspector).toContainText('Belum ada kelas dipilih');
            await expect(inspector).not.toContainText(GOLANG_COURSE.title);

            // Pilih ulang sebelum publish.
            await inspector.getByRole('button', { name: 'Tambah kelas' }).click();
            await chooseOnlyCourse(page, GOLANG_COURSE);
            await expect(inspector).toContainText(GOLANG_COURSE.title);

            // Escape menutup modal dan mengembalikan fokus ke pemicunya.
            await inspector.getByRole('button', { name: 'Tambah kelas' }).click();
            await expect(page.getByRole('dialog').filter({ hasText: 'Pilih kelas DU' })).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(page.getByRole('dialog').filter({ hasText: 'Pilih kelas DU' })).toBeHidden();
            await expect(inspector.getByRole('button', { name: 'Tambah kelas' })).toBeFocused();

            // Sync setelah memilih tidak boleh menghapus pilihan.
            await inspector.getByRole('button', { name: 'Tambah kelas' }).click();
            const syncPicker = page.getByRole('dialog').filter({ hasText: 'Pilih kelas DU' });
            await syncPicker.getByRole('button', { name: 'Sync kelas' }).click();
            await expect(syncPicker.getByText(SYNCED_COURSE.title, { exact: true })).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(syncPicker).toBeHidden();
            await expect(inspector).toContainText(GOLANG_COURSE.title);
            await expect(inspector).not.toContainText(SYNCED_COURSE.title);

            await closeFieldDrawer(page);
            await expect(page.getByRole('dialog')).toHaveCount(0);

            await page.locator('#f-title').fill(`D-Kelas e2e ${Date.now()}`);
            await page.locator('#f-description').fill('Deskripsi e2e D-Kelas');

            await page.getByRole('tab', { name: /pengaturan form/i }).click();
            await pickFutureClosedAt(page);

            const publicOption = page.getByRole('button', { name: 'Public', exact: true });
            if ((await publicOption.getAttribute('aria-pressed')) !== 'true') await publicOption.click();

            await Promise.all([
                page.waitForURL((url: URL): boolean => PUBLISHED_FORM_PATTERN.test(url.pathname), { timeout: 30_000 }),
                saveControls(page).first().click(),
            ]);

            const segments = new URL(page.url()).pathname.split('/');
            const formId = segments[segments.length - 1] ?? '';

            expect(new URL(page.url()).pathname).not.toContain('/forms/create');
            expect(formId).toMatch(UUID_PATTERN);

            await page.goto(`/joined/${owned.eventId}/forms/${formId}/fill`, {
                waitUntil: 'domcontentloaded',
            });
            await waitForHydration(page);

            // Field sistem satu-satunya di form ini adalah kartu pilihan Kelas.
            const classGroup = page.getByRole('radiogroup', { name: /^Kelas/ });

            await expect(classGroup).toBeVisible();
            await expect(classGroup.getByText(GOLANG_COURSE.title, { exact: true })).toBeVisible();
            // Kelas yang tidak dipilih dan opsi builder hasil sync tidak boleh tampil di halaman isi.
            await expect(classGroup.getByText(SQL_COURSE.title, { exact: true })).toHaveCount(0);
            await expect(classGroup.getByText(SYNCED_COURSE.title, { exact: true })).toHaveCount(0);

            await classGroup.getByText(GOLANG_COURSE.title, { exact: true }).click();
            await expect(
                page.locator(`input[type="radio"][value="${GOLANG_COURSE.title}"]`)
            ).toBeChecked();
        } finally {
            try {
                clearDuCourses(SEEDED_UID_PREFIX);
            } finally {
                cleanupOwnerFixture(identity);
            }
        }
    });
});
