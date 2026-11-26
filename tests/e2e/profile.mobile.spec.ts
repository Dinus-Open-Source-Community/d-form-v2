import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

/**
 * Profil pada layar sempit: tiga seksi tetap bertumpuk dan bisa digulir, tanpa
 * overflow horizontal, tombol simpan selebar seksinya sendiri, dan setiap
 * kontrol interaktif memenuhi target sentuh 44px.
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

const MIN_TOUCH_TARGET_PX = 44;
const OVERFLOW_TOLERANCE_PX = 1;
const LONG_NAME = 'Nama Sangat Panjang Tanpa Spasi '.repeat(3) + 'X'.repeat(120);
const LONG_EMAIL = `${'alamat-email-sangat-panjang'.repeat(4)}@contoh-domain-yang-panjang.example`;

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
    const response = await page.goto('/profile', { waitUntil: 'domcontentloaded' });

    expect(response?.status() ?? 0).toBe(200);
    await expect(page.getByTestId('profile-header')).toBeVisible({ timeout: 20_000 });
    await waitForHydration(page);
}

test.describe('profil mobile', () => {
    test('tiga seksi tampil berurutan dan bisa digulir dalam satu halaman', async ({ page }) => {
        await openProfile(page);

        await expect(page.getByTestId('profile-header')).toBeVisible();

        const scrollTops: number[] = [];

        for (const section of PROFILE_SECTIONS) {
            const locator = page.getByTestId(section.testId);

            await expect(locator, `${section.testId} harus tampil`).toBeVisible();
            await expect(locator.getByRole('heading', { level: 2 })).toHaveText(section.heading);

            // Gulir sampai seksi masuk viewport; tanpa kontrol tab apa pun.
            await locator.scrollIntoViewIfNeeded();

            const visibleInViewport = await locator.evaluate((element: Element): boolean => {
                const rect = element.getBoundingClientRect();
                const viewportHeight = window.innerHeight;

                return rect.top < viewportHeight && rect.bottom > 0;
            });

            expect(visibleInViewport, `${section.testId} harus tercapai dengan menggulir`).toBe(true);

            scrollTops.push(
                await page.evaluate((): number => document.querySelector('[data-testid="dashboard-content"]')?.scrollTop ?? 0)
            );
        }

        // Urutan gulir naik sesuai urutan seksi: identitas -> keamanan -> koneksi.
        expect(scrollTops[0]).toBeLessThanOrEqual(scrollTops[1] ?? Number.NaN);
        expect(scrollTops[1]).toBeLessThan(scrollTops[2] ?? Number.NaN);

        await expect(page.getByRole('tab')).toHaveCount(0);
        await expect(page.getByTestId('profile-save-bar')).toHaveCount(0);
    });

    test('tanpa overflow horizontal meski nama dan email panjang', async ({ page }) => {
        await openProfile(page);

        /*
         * Nama dan email tersimpan tidak diubah (data shared admin). Yang diuji
         * adalah ketahanan layout: teks panjang disuntikkan ke node yang sudah
         * dirender, dan kolom field diisi nilai panjang tanpa disimpan.
         */
        await page.locator('[data-testid="profile-header"] h1').evaluate((element: Element, value: string): void => {
            element.textContent = value;
        }, LONG_NAME);

        await page
            .locator('[data-testid="profile-header"] p')
            .first()
            .evaluate((element: Element, value: string): void => {
                element.textContent = value;
            }, LONG_EMAIL);

        await page.getByTestId('profile-field-email').fill(LONG_EMAIL);

        const overflow = await page.evaluate((): { document: number; scroller: number; page: number } => {
            const scrollingElement = document.scrollingElement;
            const scroller = document.querySelector('[data-testid="dashboard-content"]');
            const profilePage = document.querySelector('[data-testid="profile-page"]');

            return {
                document:
                    scrollingElement === null ? 0 : scrollingElement.scrollWidth - scrollingElement.clientWidth,
                scroller: scroller === null ? 0 : scroller.scrollWidth - scroller.clientWidth,
                page: profilePage === null ? 0 : profilePage.scrollWidth - profilePage.clientWidth,
            };
        });

        expect(overflow.document, `dokumen melebar: ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
        expect(overflow.scroller, `konten melebar: ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
        expect(overflow.page, `halaman profil melebar: ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );

        const viewport = page.viewportSize();
        expect(viewport, 'viewport mobile harus terdefinisi').not.toBeNull();

        const headerRight = await page
            .locator('[data-testid="profile-header"] h1')
            .evaluate((element: Element): number => element.getBoundingClientRect().right);

        expect(headerRight).toBeLessThanOrEqual((viewport?.width ?? 0) + OVERFLOW_TOLERANCE_PX);
    });

    test('tombol simpan tiap seksi selebar seksinya dan berada di dalam seksi', async ({ page }) => {
        await openProfile(page);

        const sections = [
            { save: 'profile-identity-save', section: 'profile-section-identity' },
            { save: 'profile-password-save', section: 'profile-section-security' },
        ];

        for (const entry of sections) {
            const section = page.getByTestId(entry.section);
            const save = page.getByTestId(entry.save);

            await section.scrollIntoViewIfNeeded();
            await expect(save).toBeVisible();

            // Tombol harus keturunan seksi itu sendiri, bukan elemen mengambang.
            await expect(section.locator(`[data-testid="${entry.save}"]`)).toHaveCount(1);

            const metrics = await section.evaluate(
                (element: Element, saveTestId: string): { width: number; contentWidth: number } | null => {
                    const button = element.querySelector(`[data-testid="${saveTestId}"]`);

                    if (!(button instanceof HTMLElement) || !(element instanceof HTMLElement)) return null;

                    const styles = getComputedStyle(element);
                    const buttonRect = button.getBoundingClientRect();
                    const sectionRect = element.getBoundingClientRect();

                    return {
                        width: buttonRect.width,
                        contentWidth:
                            sectionRect.width -
                            Number.parseFloat(styles.paddingLeft) -
                            Number.parseFloat(styles.paddingRight),
                    };
                },
                entry.save
            );

            expect(metrics, `${entry.save} harus terukur`).not.toBeNull();

            if (metrics === null) throw new Error(`${entry.save} tidak terukur.`);

            expect(metrics.width, `${entry.save} harus selebar konten seksinya`).toBeCloseTo(metrics.contentWidth, 0);

            const [buttonBox, sectionBox] = await Promise.all([save.boundingBox(), section.boundingBox()]);

            expect(buttonBox, `${entry.save} harus punya kotak`).not.toBeNull();
            expect(sectionBox, `${entry.section} harus punya kotak`).not.toBeNull();

            if (buttonBox === null || sectionBox === null) throw new Error(`${entry.save} tidak punya kotak.`);

            expect(buttonBox.x).toBeGreaterThanOrEqual(sectionBox.x - OVERFLOW_TOLERANCE_PX);
            expect(buttonBox.x + buttonBox.width).toBeLessThanOrEqual(
                sectionBox.x + sectionBox.width + OVERFLOW_TOLERANCE_PX
            );
            expect(buttonBox.y + buttonBox.height).toBeLessThanOrEqual(
                sectionBox.y + sectionBox.height + OVERFLOW_TOLERANCE_PX
            );
        }

        // Seksi koneksi tidak punya tombol simpan: statusnya berasal dari server.
        const connections = page.getByTestId('profile-section-connections');

        await expect(connections.getByTestId('profile-identity-save')).toHaveCount(0);
        await expect(connections.getByTestId('profile-password-save')).toHaveCount(0);
    });

    test('semua kontrol interaktif setinggi minimal 44px', async ({ page }) => {
        await openProfile(page);

        const controls = page.locator(
            [
                '[data-testid="profile-identity-save"]',
                '[data-testid="profile-password-save"]',
                '[data-testid="profile-field-name"]',
                '[data-testid="profile-field-email"]',
                '[data-testid="profile-field-current-password"]',
                '[data-testid="profile-field-new-password"]',
                '[data-testid="profile-field-confirm-password"]',
                '[data-testid="profile-connection-connect"]',
            ].join(', ')
        );

        const photoAction = page.getByRole('button', { name: 'Ubah foto' });
        // Tiap field kata sandi punya tombol mata sendiri.
        const passwordRevealButtons = page.getByRole('button', {
            name: /^Tampilkan (kata sandi saat ini|kata sandi baru|konfirmasi kata sandi)$/u,
        });

        await expect(photoAction).toBeVisible();
        await expect(passwordRevealButtons).toHaveCount(3);

        const measured: { label: string; height: number }[] = [];

        for (let index = 0; index < (await controls.count()); index += 1) {
            const control = controls.nth(index);

            if (!(await control.isVisible())) continue;

            const box = await control.boundingBox();
            if (box === null) continue;

            measured.push({
                label: (await control.getAttribute('data-testid')) ?? 'kontrol',
                height: Math.round(box.height * 100) / 100,
            });
        }

        const extras = [photoAction, ...(await passwordRevealButtons.all())];

        for (const extra of extras) {
            const box = await extra.boundingBox();
            const label = (await extra.innerText()).trim() || ((await extra.getAttribute('aria-label')) ?? 'tombol');

            expect(box, `${label} harus punya kotak`).not.toBeNull();

            if (box !== null) {
                measured.push({ label, height: Math.round(box.height * 100) / 100 });
            }
        }

        expect(measured.length, 'kontrol interaktif harus terukur').toBeGreaterThan(0);

        const undersized = measured.filter((entry) => entry.height < MIN_TOUCH_TARGET_PX);

        expect(
            undersized,
            `kontrol di bawah ${MIN_TOUCH_TARGET_PX}px (root font 15px membuat min-h-11 = 41.25px): ${JSON.stringify(undersized)}`
        ).toEqual([]);
    });

    test('setiap tombol mata hanya membuka field kata sandinya sendiri', async ({ page }) => {
        await openProfile(page);

        const security = page.getByTestId('profile-section-security');

        await security.scrollIntoViewIfNeeded();

        const passwordFields = [
            { input: 'profile-field-current-password', label: 'kata sandi saat ini' },
            { input: 'profile-field-new-password', label: 'kata sandi baru' },
            { input: 'profile-field-confirm-password', label: 'konfirmasi kata sandi' },
        ] as const;

        for (const field of passwordFields) {
            const input = page.getByTestId(field.input);

            await expect(input).toHaveAttribute('type', 'password');

            const revealButton = page.getByRole('button', { name: `Tampilkan ${field.label}`, exact: true });

            await expect(revealButton).toBeVisible();
            await revealButton.click();

            await expect(input, `${field.label} harus terbuka`).toHaveAttribute('type', 'text');

            // Dua field lain tetap tertutup: satu tombol tidak membuka semuanya.
            for (const other of passwordFields) {
                if (other.input === field.input) continue;

                await expect(page.getByTestId(other.input), `${other.label} tidak boleh ikut terbuka`).toHaveAttribute(
                    'type',
                    'password'
                );
            }

            // Nama aksesibel berganti dan menutup kembali hanya field yang sama.
            const hideButton = page.getByRole('button', { name: `Sembunyikan ${field.label}`, exact: true });

            await expect(hideButton).toHaveAttribute('aria-pressed', 'true');
            await hideButton.click();
            await expect(input, `${field.label} harus tertutup lagi`).toHaveAttribute('type', 'password');
        }
    });
});
