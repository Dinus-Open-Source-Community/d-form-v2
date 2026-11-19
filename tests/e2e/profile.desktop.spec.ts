import type { Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';
import { readEnvironment } from './support/environment';
import { createRunId } from './support/marker';
import { expect, test } from './support/fixtures';

/**
 * Halaman profil tanpa tab: kepala identitas + tiga seksi bertumpuk dalam satu
 * gulir (Profil, Keamanan, Koneksi akun). Spec ini menjaga kontrak itu tetap
 * utuh, termasuk deep link `#koneksi`, simpan per seksi, Enter-to-submit, status
 * "Belum disimpan", dan lokasi error kata sandi.
 *
 * Anggaran mutasi: route `PATCH /profile` dan `PUT /profile/password` memakai
 * `throttle:10,1` yang dihitung per IP untuk semua route ber-middleware sama.
 * Karena itu satu run hanya mengirim DUA request yang mengubah data: satu PATCH
 * (ganti nama, dipulihkan lewat tinker) dan satu PUT (percobaan kata sandi saat
 * ini yang salah). Menambah simpan identitas kedua akan menghabiskan kuota dan
 * membuat run berikutnya gagal 429.
 */

interface IProfileSectionContract {
    anchor: string;
    heading: string;
    testId: string;
}

const PROFILE_SECTIONS: readonly IProfileSectionContract[] = [
    { testId: 'profile-section-identity', anchor: 'profil', heading: 'Profil' },
    { testId: 'profile-section-security', anchor: 'keamanan', heading: 'Keamanan' },
    { testId: 'profile-section-connections', anchor: 'koneksi', heading: 'Koneksi akun' },
];

const PROFILE_PATH = '/profile';
const OVERFLOW_TOLERANCE_PX = 1;
const runId = process.env.E2E_RUN_ID ?? createRunId();

interface IOverflowReport {
    documentOverflow: number;
    scrollerOverflow: number;
    offenders: string[];
}

/** Tunggu sampai Vue menempel ke root sehingga `v-model` sudah aktif. */
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

async function openProfile(page: Page): Promise<void> {
    const response = await page.goto(PROFILE_PATH, { waitUntil: 'domcontentloaded' });

    expect(response?.status() ?? 0).toBe(200);
    await expect(page.getByTestId('profile-header')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

/**
 * Ukur overflow horizontal pada scroller dokumen dan kontainer dashboard.
 * `dashboard-content` adalah scroller sebenarnya (shell `h-svh`), jadi keduanya
 * diperiksa, plus daftar elemen terlebar untuk laporan kegagalan.
 */
async function measureOverflow(page: Page): Promise<IOverflowReport> {
    return page.evaluate((): IOverflowReport => {
        const scrollingElement = document.scrollingElement;
        const scroller = document.querySelector('[data-testid="dashboard-content"]');
        const viewportWidth = document.documentElement.clientWidth;

        const describe = (element: Element): string => {
            const testId = element.getAttribute('data-testid');
            const id = element.id === '' ? '' : `#${element.id}`;
            const text = (element.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 40);
            const prefix = testId === null ? element.tagName.toLowerCase() : `[data-testid="${testId}"]`;

            return `${prefix}${id}${text === '' ? '' : ` "${text}"`}`;
        };

        const offenders: { delta: number; label: string }[] = [];

        for (const element of Array.from(document.querySelectorAll('body *'))) {
            const rect = element.getBoundingClientRect();

            if (rect.width < 2 || rect.height < 2) continue;

            const delta = Math.round(rect.right - viewportWidth);

            if (delta > 1) offenders.push({ delta, label: describe(element) });
        }

        offenders.sort((left, right) => right.delta - left.delta);

        return {
            documentOverflow:
                scrollingElement === null ? 0 : scrollingElement.scrollWidth - scrollingElement.clientWidth,
            scrollerOverflow: scroller === null ? 0 : scroller.scrollWidth - scroller.clientWidth,
            offenders: offenders
                .slice(0, 5)
                .map((offender) => `${offender.label} (+${offender.delta}px)`),
        };
    });
}

/**
 * Isi nama lalu kirim satu-satunya `PATCH /profile` lewat Enter di field.
 * Status response dikembalikan supaya kegagalan (mis. 429) terlihat apa adanya.
 */
async function submitIdentityNameWithEnter(page: Page, name: string): Promise<number> {
    const nameField = page.getByTestId('profile-field-name');

    await nameField.fill(name);
    await expect(page.getByTestId('profile-identity-dirty')).toBeVisible();
    await expect(page.getByTestId('profile-identity-save')).toBeEnabled();

    const patchResponse = page.waitForResponse(
        (response): boolean =>
            response.request().method() === 'PATCH' && new URL(response.url()).pathname === PROFILE_PATH
    );

    await nameField.press('Enter');

    const status = (await patchResponse).status();

    return status;
}

/**
 * Kembalikan nama akun admin lewat database, bukan PATCH kedua yang akan
 * memakan kuota throttle. Reload sesudahnya membuktikan server menyajikan nama asli.
 */
async function restoreIdentityNameInDatabase(page: Page, originalName: string): Promise<void> {
    const emailLiteral = JSON.stringify(readEnvironment().adminEmail);
    const nameLiteral = JSON.stringify(originalName);

    runArtisanCommand([
        'tinker',
        '--execute',
        `App\\Models\\User::query()->where('email', ${emailLiteral})->update(['name' => ${nameLiteral}]);`,
    ]);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByTestId('profile-header')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
    await expect(page.getByTestId('profile-field-name')).toHaveValue(originalName);
    await expect(page.locator('[data-testid="profile-header"] h1')).toHaveText(originalName);
}

/**
 * Hash kata sandi admin untuk membandingkan sebelum dan sesudah percobaan gagal.
 * Nilainya tidak pernah dicetak: yang dibuktikan hanya "tidak berubah".
 */
function readAdminPasswordHash(): string {
    const emailLiteral = JSON.stringify(readEnvironment().adminEmail);

    return runArtisanCommand([
        'tinker',
        '--execute',
        `echo App\\Models\\User::query()->where('email', ${emailLiteral})->value('password');`,
    ]);
}

test.describe('profil desktop: seksi bertumpuk', () => {
    test('kepala dan tiga seksi tampil bersamaan berurutan tanpa interaksi', async ({ page }) => {
        await openProfile(page);

        // Tidak ada kontrol navigasi tersisa dari desain tab.
        await expect(page.getByRole('tab')).toHaveCount(0);
        await expect(page.getByTestId('profile-section-nav')).toHaveCount(0);
        await expect(page.getByTestId('profile-save-bar')).toHaveCount(0);
        await expect(page.getByTestId('profile-panel-identity')).toHaveCount(0);

        const header = page.getByTestId('profile-header');
        await expect(header).toBeVisible();

        const positions: number[] = [];

        for (const section of PROFILE_SECTIONS) {
            const locator = page.getByTestId(section.testId);

            await expect(locator, `${section.testId} harus tampil tanpa interaksi`).toBeVisible();
            await expect(locator.getByRole('heading', { level: 2 })).toHaveText(section.heading);
            await expect(page.locator(`#${section.anchor}`)).toHaveCount(1);

            const box = await locator.boundingBox();
            expect(box, `${section.testId} harus punya kotak terukur`).not.toBeNull();
            positions.push(box?.y ?? Number.NaN);
        }

        const headerBox = await header.boundingBox();
        expect(headerBox, 'kepala profil harus terukur').not.toBeNull();
        expect(headerBox?.y ?? Number.NaN).toBeLessThan(positions[0] ?? Number.NaN);

        // Urutan visual dan urutan DOM sama: identitas -> keamanan -> koneksi.
        expect(positions[0]).toBeLessThan(positions[1] ?? Number.NaN);
        expect(positions[1]).toBeLessThan(positions[2] ?? Number.NaN);

        const domOrder = await page.evaluate((): string[] =>
            Array.from(document.querySelectorAll('[data-testid^="profile-section-"]')).map(
                (element): string => element.getAttribute('data-testid') ?? ''
            )
        );

        expect(domOrder).toEqual(PROFILE_SECTIONS.map((section) => section.testId));

        // Setiap seksi memakai jangkar URL-nya sendiri sebagai id elemen.
        for (const section of PROFILE_SECTIONS) {
            const anchorTestId = await page.evaluate(
                (anchor: string): string | null =>
                    document.getElementById(anchor)?.getAttribute('data-testid') ?? null,
                section.anchor
            );

            expect(anchorTestId, `#${section.anchor} harus menunjuk ${section.testId}`).toBe(section.testId);
        }

        const overflow = await measureOverflow(page);

        expect(overflow.documentOverflow, `overflow dokumen: ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
        expect(overflow.scrollerOverflow, `overflow konten: ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
    });

    test('deep link /profile#koneksi mendarat di seksi koneksi', async ({ page }) => {
        const response = await page.goto(`${PROFILE_PATH}#koneksi`, { waitUntil: 'domcontentloaded' });

        expect(response?.status() ?? 0).toBe(200);

        const connections = page.getByTestId('profile-section-connections');
        await expect(connections).toBeVisible({ timeout: 20_000 });

        // URL tetap membawa hash dan hash itu menunjuk elemen seksi koneksi.
        expect(new URL(page.url()).hash).toBe('#koneksi');

        const targetTestId = await page.evaluate((): string | null => {
            // `:target` browser dibekukan saat navigasi, sementara seksi ini baru
            // dirender setelah Vue mount; resolusi id dari hash adalah bukti yang setara.
            const anchor = window.location.hash.replace('#', '');
            const target = anchor === '' ? null : document.getElementById(anchor);

            return target === null ? null : target.getAttribute('data-testid');
        });

        expect(targetTestId).toBe('profile-section-connections');

        // Judul seksi harus benar-benar masuk viewport setelah lompatan hash.
        await expect
            .poll(
                async (): Promise<boolean> => {
                    const box = await connections.getByRole('heading', { level: 2 }).boundingBox();
                    const viewport = page.viewportSize();

                    if (box === null || viewport === null) return false;

                    return box.y >= 0 && box.y + box.height <= viewport.height;
                },
                { message: 'judul seksi koneksi harus berada di dalam viewport setelah deep link' }
            )
            .toBe(true);

        const overflow = await measureOverflow(page);

        expect(overflow.documentOverflow, `overflow dokumen: ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
        expect(overflow.scrollerOverflow, `overflow konten: ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
    });

    test('simpan profil lewat Enter: tersimpan, bertahan setelah reload, lalu nama asli dipulihkan', async ({
        page,
    }) => {
        await openProfile(page);

        const nameField = page.getByTestId('profile-field-name');
        const saveButton = page.getByTestId('profile-identity-save');
        const dirtyMarker = page.getByTestId('profile-identity-dirty');
        const originalName = await nameField.inputValue();
        const updatedName = `${originalName} ${runId}`;

        expect(originalName, 'nama admin harus terbaca').not.toBe('');

        try {
            // Enter di field nama adalah submit form seksi identitas: satu request saja.
            const patchStatus = await submitIdentityNameWithEnter(page, updatedName);

            expect(patchStatus, 'Enter harus memicu PATCH /profile yang diterima').toBeLessThan(400);

            await expect(
                page.locator('[data-sonner-toast]').filter({ hasText: 'Profil berhasil diperbarui.' })
            ).toBeVisible();
            await expect(page.locator('[data-testid="profile-header"] h1')).toHaveText(updatedName);
            await expect(dirtyMarker).toHaveCount(0);
            await expect(saveButton).toBeDisabled();

            // Persistensi: nilai datang dari server setelah reload penuh.
            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.getByTestId('profile-header')).toBeVisible({ timeout: 20_000 });
            await waitForHydration(page);

            await expect(page.getByTestId('profile-field-name')).toHaveValue(updatedName);
            await expect(page.locator('[data-testid="profile-header"] h1')).toHaveText(updatedName);
        } finally {
            await restoreIdentityNameInDatabase(page, originalName);
        }
    });

    test('status "Belum disimpan" muncul saat kotor dan hilang saat nilai dikembalikan', async ({ page }) => {
        await openProfile(page);

        const nameField = page.getByTestId('profile-field-name');
        const saveButton = page.getByTestId('profile-identity-save');
        const dirtyMarker = page.getByTestId('profile-identity-dirty');
        const originalName = await nameField.inputValue();

        // Kondisi awal bersih: penanda tidak ada dan tombol simpan nonaktif.
        await expect(dirtyMarker).toHaveCount(0);
        await expect(saveButton).toBeDisabled();

        await nameField.fill(`${originalName} dirty ${runId}`);

        await expect(dirtyMarker).toBeVisible();
        await expect(dirtyMarker).toHaveText('Belum disimpan');
        await expect(saveButton).toBeEnabled();

        // Mengembalikan nilai ke versi tersimpan menghapus status kotor tanpa request.
        await nameField.fill(originalName);
        await expect(dirtyMarker).toHaveCount(0);
        await expect(saveButton).toBeDisabled();
    });

    test('error kata sandi tampil di dalam seksi Keamanan tanpa memicu simpan profil', async ({ page }) => {
        await openProfile(page);

        const security = page.getByTestId('profile-section-security');
        const currentPassword = page.getByTestId('profile-field-current-password');
        const newPassword = page.getByTestId('profile-field-new-password');
        const confirmPassword = page.getByTestId('profile-field-confirm-password');
        const passwordSave = page.getByTestId('profile-password-save');

        await security.scrollIntoViewIfNeeded();
        await expect(currentPassword).toBeVisible();

        const identityPatches: string[] = [];
        page.on('request', (request): void => {
            if (request.method() === 'PATCH' && new URL(request.url()).pathname === PROFILE_PATH) {
                identityPatches.push(request.url());
            }
        });

        // Kata sandi saat ini sengaja salah: akun admin bersama tidak boleh berubah.
        await currentPassword.fill('KataSandiSalah!2026');
        await newPassword.fill('KataSandiBaru!2026');
        await confirmPassword.fill('KataSandiBaru!2026');

        await expect(passwordSave).toBeEnabled();

        const passwordHashBefore = readAdminPasswordHash();

        expect(passwordHashBefore, 'admin harus punya kata sandi lokal').not.toBe('');

        const passwordResponse = page.waitForResponse(
            (response): boolean =>
                response.request().method() === 'PUT' && new URL(response.url()).pathname === '/profile/password'
        );

        await passwordSave.click();

        const rejectedStatus = (await passwordResponse).status();

        /*
         * Penolakan validasi dikirim sebagai redirect balik (302/303) sama seperti
         * sukses, jadi status saja bukan bukti. Bukti penolakan ada pada error
         * inline, tidak adanya PATCH /profile, dan hash kata sandi yang tidak berubah.
         */
        expect([302, 303, 422], `status penolakan kata sandi tidak terduga: ${rejectedStatus}`).toContain(rejectedStatus);

        // Error dirender di dalam seksi Keamanan, bukan di dekat tombol simpan profil.
        const inlineError = security.locator('[role="alert"]').filter({ hasText: /\S/u });

        await expect(inlineError).toBeVisible();
        await expect(inlineError).toContainText(/salah|incorrect/i);
        await expect(currentPassword).toHaveAttribute('aria-invalid', 'true');
        await expect(currentPassword).toHaveAttribute('aria-describedby', 'current-password-error');

        const describedByTargets = await page.evaluate((): string[] => {
            const field = document.querySelector('[data-testid="profile-field-current-password"]');
            if (!(field instanceof HTMLElement)) return [];

            const describedBy = field.getAttribute('aria-describedby') ?? '';

            return describedBy
                .split(/\s+/u)
                .filter((id) => id !== '')
                .map((id): string => document.getElementById(id)?.textContent?.trim() ?? '');
        });

        expect(describedByTargets.join(' ')).toMatch(/salah|incorrect/i);

        // Tidak ada PATCH /profile dan tidak ada perubahan identitas tertunda.
        expect(identityPatches, 'simpan kata sandi tidak boleh memicu PATCH /profile').toEqual([]);
        await expect(page.getByTestId('profile-identity-dirty')).toHaveCount(0);

        // Kata sandi admin bersama tidak berubah dan tidak ada toast sukses.
        expect(readAdminPasswordHash(), 'kata sandi admin tidak boleh berubah').toBe(passwordHashBefore);
        await expect(
            page.locator('[data-sonner-toast]').filter({ hasText: 'Kata sandi berhasil diperbarui.' })
        ).toHaveCount(0);
    });

    test('kata sandi baru: indikator kekuatan, baris kecocokan, dan submit tertahan sebelum request', async ({
        page,
    }) => {
        await openProfile(page);

        const security = page.getByTestId('profile-section-security');
        const newPassword = page.getByTestId('profile-field-new-password');
        const confirmPassword = page.getByTestId('profile-field-confirm-password');
        const passwordSave = page.getByTestId('profile-password-save');
        const strengthMeter = security.getByRole('progressbar', { name: 'Kekuatan kata sandi' });

        await security.scrollIntoViewIfNeeded();

        // Indikator selalu tampil: empat segmen, nilai awal nol, hint aturan minimum.
        await expect(strengthMeter).toBeVisible();
        await expect(strengthMeter.locator('span')).toHaveCount(4);
        await expect(strengthMeter).toHaveAttribute('aria-valuemin', '0');
        await expect(strengthMeter).toHaveAttribute('aria-valuemax', '4');
        await expect(strengthMeter).toHaveAttribute('aria-valuenow', '0');
        await expect(security.getByText('Minimal 8 karakter dengan huruf besar dan angka.')).toBeVisible();

        // Tujuh karakter: dua kriteria terpenuhi, segmen naik tetapi minimum belum.
        await newPassword.fill('Rahasi1');
        await expect(strengthMeter).toHaveAttribute('aria-valuenow', '3');

        // Baris kecocokan hanya muncul setelah konfirmasi diisi.
        await expect(security.getByText('Kata sandi belum cocok')).toHaveCount(0);
        await confirmPassword.fill('Rahasi2');
        await expect(security.getByText('Kata sandi belum cocok')).toBeVisible();
        await confirmPassword.fill('Rahasi1');
        await expect(security.getByText('Kata sandi cocok')).toBeVisible();

        const passwordRequests: string[] = [];
        page.on('request', (request): void => {
            if (request.method() === 'PUT' && new URL(request.url()).pathname === '/profile/password') {
                passwordRequests.push(request.url());
            }
        });

        // Di bawah 8 karakter: ditahan di klien dengan pesan yang bisa dibaca.
        await expect(passwordSave).toBeEnabled();
        await passwordSave.click();
        await expect(
            page.locator('[data-sonner-toast]').filter({ hasText: 'Kata sandi minimal 8 karakter.' })
        ).toBeVisible();
        expect(passwordRequests, 'kata sandi di bawah 8 karakter tidak boleh dikirim').toEqual([]);

        // Panjang cukup tetapi konfirmasi berbeda: juga ditahan sebelum request.
        await newPassword.fill('KataSandiBaru!2026');
        await expect(strengthMeter).toHaveAttribute('aria-valuenow', '4');
        await confirmPassword.fill('KataSandiLain!2026');
        await expect(security.getByText('Kata sandi belum cocok')).toBeVisible();

        await passwordSave.click();
        await expect(
            page.locator('[data-sonner-toast]').filter({ hasText: 'Konfirmasi kata sandi tidak cocok.' })
        ).toBeVisible();
        expect(passwordRequests, 'konfirmasi yang berbeda tidak boleh dikirim').toEqual([]);

        // Tidak ada jalur yang berakhir dengan kata sandi admin berubah.
        await expect(
            page.locator('[data-sonner-toast]').filter({ hasText: 'Kata sandi berhasil diperbarui.' })
        ).toHaveCount(0);
    });
});
