import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

/**
 * Direktori pengguna pada viewport mobile: kartu menggantikan tabel, semua target
 * sentuh minimal 44px, dan panel create memakai lebar penuh dengan Escape.
 */
const ACTION_TEST_IDS = ['user-row-edit', 'user-row-detail', 'user-row-delete'] as const;
const MIN_TOUCH_TARGET = 44;
const VIEWPORT_TOLERANCE_PX = 1;

/** Selisih `scrollWidth - clientWidth` sebuah elemen; `-1` bila elemen tidak ada. */
async function overflowPixels(page: Page, selector: string): Promise<number> {
    return page.evaluate((target: string): number => {
        const element = target === 'document' ? document.scrollingElement : document.querySelector(target);

        if (element === null) return -1;

        return element.scrollWidth - element.clientWidth;
    }, selector);
}

function visibleCards(page: Page): Locator {
    return page.locator('article[data-testid="user-row"]:visible');
}

test.describe('direktori pengguna (mobile)', () => {
    test('kartu menggantikan tabel tanpa overflow horizontal', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

        await expect(visibleCards(page).first()).toBeVisible();
        await expect(page.locator('[data-testid="user-directory"] table')).toBeHidden();

        expect(await overflowPixels(page, 'document')).toBeLessThanOrEqual(VIEWPORT_TOLERANCE_PX);
        expect(await overflowPixels(page, '[data-testid="dashboard-content"]')).toBeLessThanOrEqual(
            VIEWPORT_TOLERANCE_PX
        );
    });

    test('aksi baris dan chip role memenuhi target sentuh 44px', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await expect(visibleCards(page).first()).toBeVisible();

        for (const testId of ACTION_TEST_IDS) {
            const actions = page.locator(`[data-testid="${testId}"]:visible`);
            const actionCount = await actions.count();

            expect(actionCount).toBeGreaterThan(0);

            for (let index = 0; index < actionCount; index += 1) {
                const box = await actions.nth(index).boundingBox();

                expect(box, `${testId} #${index} tidak punya bounding box`).not.toBeNull();

                if (box === null) continue;

                expect(box.height, `${testId} #${index}`).toBeGreaterThanOrEqual(MIN_TOUCH_TARGET);
            }
        }

        const chips = page.locator('[data-testid="user-role-filter"]');
        const chipCount = await chips.count();
        expect(chipCount).toBeGreaterThan(1);

        // Chip berada di kontainer yang boleh di-scroll horizontal pada layar sempit.
        const containerScrolls = await chips.first().evaluate((element: Element): boolean => {
            const container = element.parentElement;

            if (!(container instanceof HTMLElement)) return false;

            const overflowX = getComputedStyle(container).overflowX;

            return overflowX === 'auto' || overflowX === 'scroll';
        });
        expect(containerScrolls).toBe(true);

        for (let index = 0; index < chipCount; index += 1) {
            const chip = chips.nth(index);

            await chip.scrollIntoViewIfNeeded();
            await expect(chip).toBeVisible();

            const box = await chip.boundingBox();
            expect(box, `chip #${index} tidak punya bounding box`).not.toBeNull();

            if (box === null) continue;

            expect(box.height, `chip #${index}`).toBeGreaterThanOrEqual(MIN_TOUCH_TARGET);
        }
    });

    test('panel create selebar viewport dan Escape mengembalikan fokus', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

        const trigger = page.locator('[data-testid="user-create-trigger"]');
        await trigger.click();

        const panel = page.locator('[data-testid="user-panel"]');
        await expect(panel).toBeVisible();
        await expect(page.locator('[data-testid="user-panel-title"]')).toHaveText('Buat akun baru');

        const viewport = page.viewportSize();
        const panelBox = await panel.boundingBox();

        expect(viewport).not.toBeNull();
        expect(panelBox).not.toBeNull();

        if (viewport === null || panelBox === null) {
            throw new Error('Panel atau viewport tidak punya ukuran terukur.');
        }

        expect(panelBox.width).toBeGreaterThanOrEqual(viewport.width - 2);

        await page.keyboard.press('Escape');

        await expect(panel).toBeHidden();
        await expect.poll((): boolean => new URL(page.url()).searchParams.has('create')).toBe(false);
        await expect(trigger).toBeFocused();
    });
});
