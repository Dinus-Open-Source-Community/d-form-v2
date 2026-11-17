import { expect, test } from './support/fixtures';

const RECENT_EVENT_ROW = '[data-testid="recent-event-row"]';
const RECENT_EVENT_QUOTA = '[data-testid="recent-event-quota"]';
const RECENT_EVENT_MANAGE = '[data-testid="recent-event-action-manage"]';
const MIN_TOUCH_TARGET = 44;

test.describe('dashboard recent events (mobile)', () => {
    test('keeps rows inside the viewport with touch-sized actions and no title/quota overlap', async ({ page }) => {
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        const rows = page.locator(RECENT_EVENT_ROW);
        await expect(rows.first()).toBeVisible();

        const rowCount = await rows.count();

        for (let rowIndex = 0; rowIndex < rowCount; rowIndex += 1) {
            const row = rows.nth(rowIndex);
            const manageAction = row.locator(RECENT_EVENT_MANAGE);

            await expect(manageAction).toBeVisible();

            const box = await manageAction.boundingBox();
            expect(box).not.toBeNull();

            if (box === null) {
                throw new Error('Row manage action has no measurable bounding box.');
            }

            expect(box.height).toBeGreaterThanOrEqual(MIN_TOUCH_TARGET);
            expect(box.width).toBeGreaterThanOrEqual(MIN_TOUCH_TARGET);

            const overlapsQuota = await row.evaluate((element: Element, quotaSelector: string): boolean => {
                const title = element.querySelector('h3');
                const quota = element.querySelector(quotaSelector);

                if (!(title instanceof HTMLElement) || !(quota instanceof HTMLElement)) {
                    throw new Error('Row is missing its h3 title or quota block.');
                }

                return title.getBoundingClientRect().right > quota.getBoundingClientRect().left + 1;
            }, RECENT_EVENT_QUOTA);

            expect(overlapsQuota).toBe(false);
        }

        const hasHorizontalOverflow = await page.evaluate((): boolean => {
            const root = document.documentElement;

            return root.scrollWidth > root.clientWidth + 1;
        });
        expect(hasHorizontalOverflow).toBe(false);
    });
});
