import { expect, test } from './support/fixtures';

const RECENT_EVENT_ROW = '[data-testid="recent-event-row"]';
const RECENT_EVENT_QUOTA = '[data-testid="recent-event-quota"]';
const RECENT_EVENT_MANAGE = '[data-testid="recent-event-action-manage"]';
const RECENT_EVENT_REGISTRANTS = '[data-testid="recent-event-action-registrants"]';
const ACTION_SELECTOR = `${RECENT_EVENT_MANAGE}, ${RECENT_EVENT_REGISTRANTS}`;
const MIN_TOUCH_TARGET = 44;

test.describe('dashboard recent events (mobile)', () => {
    test('keeps rows inside the viewport with touch-sized actions and no title/quota overlap', async ({ page }) => {
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        const rows = page.locator(RECENT_EVENT_ROW);
        await expect(rows.first()).toBeVisible();

        const rowCount = await rows.count();

        for (let rowIndex = 0; rowIndex < rowCount; rowIndex += 1) {
            const row = rows.nth(rowIndex);

            await expect(row.locator(RECENT_EVENT_MANAGE)).toBeVisible();

            const registrantsAction = row.locator(RECENT_EVENT_REGISTRANTS);

            if ((await registrantsAction.count()) > 0) {
                await expect(registrantsAction.first()).toBeVisible();
            }

            const actions = row.locator(ACTION_SELECTOR);
            const actionCount = await actions.count();
            expect(actionCount).toBeGreaterThan(0);

            for (let actionIndex = 0; actionIndex < actionCount; actionIndex += 1) {
                const box = await actions.nth(actionIndex).boundingBox();
                expect(box).not.toBeNull();

                if (box === null) {
                    throw new Error('Row action has no measurable bounding box.');
                }

                expect(box.height).toBeGreaterThanOrEqual(MIN_TOUCH_TARGET);
                expect(box.width).toBeGreaterThanOrEqual(MIN_TOUCH_TARGET);
            }

            const overlapsQuota = await row.evaluate((element: Element): boolean => {
                const title = element.querySelector('h3');
                const quota = element.querySelector('[data-testid="recent-event-quota"]');

                if (!(title instanceof HTMLElement) || !(quota instanceof HTMLElement)) {
                    throw new Error('Row is missing its h3 title or quota block.');
                }

                return title.getBoundingClientRect().right > quota.getBoundingClientRect().left + 1;
            });

            expect(overlapsQuota).toBe(false);
        }

        const hasHorizontalOverflow = await page.evaluate((): boolean => {
            const root = document.documentElement;

            return root.scrollWidth > root.clientWidth + 1;
        });
        expect(hasHorizontalOverflow).toBe(false);
    });
});
