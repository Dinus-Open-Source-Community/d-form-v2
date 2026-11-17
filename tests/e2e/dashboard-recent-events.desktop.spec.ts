import type { ConsoleMessage, Response } from '@playwright/test';
import { expect, test } from './support/fixtures';

const RECENT_EVENTS_SECTION = '[data-testid="recent-events-section"]';
const RECENT_EVENT_ROW = '[data-testid="recent-event-row"]';
const RECENT_EVENT_QUOTA = '[data-testid="recent-event-quota"]';
const RECENT_EVENT_MANAGE = '[data-testid="recent-event-action-manage"]';
const RECENT_EVENT_REGISTRANTS = '[data-testid="recent-event-action-registrants"]';

// Badge.vue tidak menulis `data-slot`, jadi chip dicocokkan lewat kelas dasar shadcn/variants.
const EVENT_STATUS_CHIP = '[data-slot="badge"], .inline-flex.rounded-xl.border';

const REGISTRANTS_PATH = /^\/admin\/events\/[^/]+\/registrants$/;

/** Banner sengaja di-abort di spec ini, jadi kegagalan muat `storage/` bukan error aplikasi. */
function isExpectedStorageAbort(message: ConsoleMessage): boolean {
    const url = message.location().url;

    if (url.includes('/storage/')) {
        return true;
    }

    return /net::ERR_ABORTED|net::ERR_FAILED/.test(message.text());
}

test.describe('dashboard recent events (desktop)', () => {
    test('renders a single section title, complete row anatomy, and a working registrants action', async ({ page }) => {
        const consoleErrors: string[] = [];

        page.on('console', (message: ConsoleMessage): void => {
            if (message.type() !== 'error' || isExpectedStorageAbort(message)) {
                return;
            }

            consoleErrors.push(`${message.text()} @ ${message.location().url}`);
        });

        await page.route('**/storage/**', (route) => route.abort());
        await page.route('https://seeyu.doscom.org/script.js', (route) =>
            route.fulfill({ contentType: 'application/javascript', body: '' })
        );
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        await expect(page.getByRole('heading', { name: 'Aktivitas terbaru', exact: true })).toHaveCount(1);
        await expect(page.getByText(/\d+ acara terbaru di workspace Anda/).first()).toBeVisible();

        const section = page.locator(RECENT_EVENTS_SECTION);
        await expect(section).toBeVisible();

        const rows = section.locator(RECENT_EVENT_ROW);
        await expect(rows.first()).toBeVisible();
        expect(await rows.count()).toBeGreaterThanOrEqual(1);

        const firstRow = rows.first();

        const titleText = (await firstRow.locator('h3').first().innerText()).trim();
        expect(titleText.length).toBeGreaterThan(0);

        const chipCount = await firstRow.locator(EVENT_STATUS_CHIP).count();
        expect(chipCount).toBeLessThanOrEqual(2);

        const quotaText = (await firstRow.locator(RECENT_EVENT_QUOTA).innerText()).trim();
        expect(quotaText).toMatch(/^(?:\d+\/\d+|-)$/);

        await expect(firstRow.locator(RECENT_EVENT_MANAGE)).toBeVisible();

        const hasHorizontalOverflow = await page.evaluate((): boolean => {
            const root = document.documentElement;

            return root.scrollWidth > root.clientWidth + 1;
        });
        expect(hasHorizontalOverflow).toBe(false);

        const registrantsAction = firstRow.locator(RECENT_EVENT_REGISTRANTS);
        await expect(registrantsAction).toBeVisible();

        const [visitResponse] = await Promise.all([
            page.waitForResponse((response: Response): boolean => REGISTRANTS_PATH.test(new URL(response.url()).pathname)),
            registrantsAction.click(),
        ]);

        expect(visitResponse.status()).toBeLessThan(400);
        await expect(page).toHaveURL(/\/admin\/events\/[^/]+\/registrants/);
        await expect(page.locator('body')).not.toContainText(/403 Forbidden|404 Not Found|Page Expired/i);

        expect(consoleErrors).toEqual([]);
    });
});
