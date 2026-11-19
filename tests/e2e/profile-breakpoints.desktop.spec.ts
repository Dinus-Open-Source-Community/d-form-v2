import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

/**
 * Halaman profil harus utuh pada lebar 360 sampai 1920px, kolom konten tidak
 * ikut melebar di layar besar, dan skala teks 200% (didekati lewat root
 * font-size 32px pada lebar 640) tidak memotong input maupun tombol.
 */

const BREAKPOINTS = [360, 390, 414, 640, 768, 1024, 1280, 1440, 1920] as const;
const OVERFLOW_TOLERANCE_PX = 1;
const WIDE_VIEWPORT_PX = 1920;
const CONTENT_COLUMN_MAX_PX = 800;
const ZOOM_APPROXIMATION_VIEWPORT_PX = 640;
const ZOOM_ROOT_FONT_SIZE = '32px';

interface IOverflowReport {
    viewportWidth: number;
    documentOverflow: number;
    scrollerOverflow: number;
    profileOverflow: number;
    offenders: string[];
}

async function measureOverflow(page: Page): Promise<IOverflowReport> {
    return page.evaluate((): IOverflowReport => {
        const scrollingElement = document.scrollingElement;
        const scroller = document.querySelector('[data-testid="dashboard-content"]');
        const profilePage = document.querySelector('[data-testid="profile-page"]');
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

            // 1px adalah toleransi subpiksel yang sama dengan assertion di luar evaluate.
            if (delta > 1) offenders.push({ delta, label: describe(element) });
        }

        offenders.sort((left, right) => right.delta - left.delta);

        return {
            viewportWidth,
            documentOverflow:
                scrollingElement === null ? 0 : scrollingElement.scrollWidth - scrollingElement.clientWidth,
            scrollerOverflow: scroller === null ? 0 : scroller.scrollWidth - scroller.clientWidth,
            profileOverflow: profilePage === null ? 0 : profilePage.scrollWidth - profilePage.clientWidth,
            offenders: offenders
                .slice(0, 5)
                .map((offender) => `${offender.label} (+${offender.delta}px)`),
        };
    });
}

async function openProfileAt(page: Page, width: number): Promise<void> {
    await page.setViewportSize({ width, height: 900 });

    const response = await page.goto('/profile', { waitUntil: 'domcontentloaded' });

    expect(response?.status() ?? 0).toBe(200);
    await expect(page.getByTestId('profile-section-connections')).toBeVisible({ timeout: 20_000 });
}

test.describe('profil (breakpoint)', () => {
    test('tidak ada overflow horizontal pada setiap breakpoint target', async ({ page }) => {
        test.setTimeout(120_000);

        for (const width of BREAKPOINTS) {
            await openProfileAt(page, width);

            const overflow = await measureOverflow(page);
            const details = `${width}px: ${JSON.stringify(overflow)}`;

            expect(overflow.documentOverflow, `overflow dokumen pada ${details}`).toBeLessThanOrEqual(
                OVERFLOW_TOLERANCE_PX
            );
            expect(overflow.scrollerOverflow, `overflow konten pada ${details}`).toBeLessThanOrEqual(
                OVERFLOW_TOLERANCE_PX
            );
            expect(overflow.profileOverflow, `overflow halaman profil pada ${details}`).toBeLessThanOrEqual(
                OVERFLOW_TOLERANCE_PX
            );
            expect(overflow.offenders, `elemen melebar pada ${details}`).toEqual([]);
        }
    });

    test('kolom konten tetap terbatas dan tidak melebar bersama viewport', async ({ page }) => {
        const measureColumn = (): Promise<{ pageWidth: number; columnWidth: number; viewportWidth: number }> =>
            page.evaluate(
                (): { pageWidth: number; columnWidth: number; viewportWidth: number } => {
                    const profilePage = document.querySelector('[data-testid="profile-page"]');
                    const column = document.querySelector('[data-testid="profile-page"] > div');

                    return {
                        pageWidth: profilePage === null ? -1 : profilePage.getBoundingClientRect().width,
                        columnWidth: column === null ? -1 : column.getBoundingClientRect().width,
                        viewportWidth: window.innerWidth,
                    };
                }
            );

        await openProfileAt(page, 1440);
        const narrow = await measureColumn();

        await openProfileAt(page, WIDE_VIEWPORT_PX);
        const wide = await measureColumn();

        expect(wide.viewportWidth).toBe(WIDE_VIEWPORT_PX);
        expect(wide.columnWidth, 'kolom konten profil harus terukur').toBeGreaterThan(0);
        expect(
            wide.columnWidth,
            `kolom konten ${wide.columnWidth}px melebihi batas ${CONTENT_COLUMN_MAX_PX}px`
        ).toBeLessThanOrEqual(CONTENT_COLUMN_MAX_PX);

        // Bukti tidak melebar: lebar kolom identik pada 1440 dan 1920.
        expect(wide.columnWidth, 'kolom konten tidak boleh ikut melebar di 1920').toBeCloseTo(
            narrow.columnWidth,
            0
        );
        expect(wide.pageWidth, 'kontainer halaman tidak boleh ikut melebar di 1920').toBeCloseTo(
            narrow.pageWidth,
            0
        );
    });

    test('skala teks 200% pada lebar 640 tidak memotong input dan tombol', async ({ page }) => {
        await openProfileAt(page, ZOOM_APPROXIMATION_VIEWPORT_PX);

        await page.addStyleTag({ content: `html{font-size:${ZOOM_ROOT_FONT_SIZE}}` });

        await expect
            .poll(
                async (): Promise<string> =>
                    page.evaluate((): string => getComputedStyle(document.documentElement).fontSize),
                { message: 'root font-size 32px harus diterapkan' }
            )
            .toBe(ZOOM_ROOT_FONT_SIZE);

        const overflow = await measureOverflow(page);
        const details = JSON.stringify(overflow);

        expect(overflow.documentOverflow, `overflow dokumen pada skala 200%: ${details}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
        expect(overflow.scrollerOverflow, `overflow konten pada skala 200%: ${details}`).toBeLessThanOrEqual(
            OVERFLOW_TOLERANCE_PX
        );
        expect(overflow.offenders, `elemen melebar pada skala 200%: ${details}`).toEqual([]);

        const clipped = await page.evaluate((): string[] => {
            const root = document.querySelector('[data-testid="profile-page"]');

            if (root === null) return ['profile-page tidak ditemukan'];

            const result: string[] = [];

            for (const control of Array.from(root.querySelectorAll('input, button'))) {
                const rect = control.getBoundingClientRect();

                // Kontrol tersembunyi (input berkas `sr-only`) tidak dirender untuk mata.
                if (rect.width < 2 || rect.height < 2) continue;

                if (control.scrollWidth > control.clientWidth + 1 || control.scrollHeight > control.clientHeight + 1) {
                    const testId = control.getAttribute('data-testid') ?? control.tagName.toLowerCase();
                    const text = (control.textContent ?? '').trim().slice(0, 30);

                    result.push(`${testId}${text === '' ? '' : ` "${text}"`} terpotong`);
                }
            }

            return result;
        });

        expect(clipped, `kontrol terpotong pada skala 200%: ${JSON.stringify(clipped)}`).toEqual([]);
    });
});
