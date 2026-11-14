import { expect, test } from './support/fixtures';
import { readPageProps } from './support/network';
import type { IWeeklyKpiTrends } from '../../resources/js/types/kpi';

interface IWeeklyKpiPageProps {
    weeklyKpiTrends: IWeeklyKpiTrends;
}

test.describe('weekly KPI trends', () => {
    test('renders weekly line charts, change percentages, and point-linked hover values', async ({ page }) => {
        await page.route('**/storage/**', (route) => route.abort());
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        const pageProps = await readPageProps<IWeeklyKpiPageProps>(page);
        const weeklySeries = [
            pageProps.weeklyKpiTrends.totalEvents,
            pageProps.weeklyKpiTrends.activeEvents,
            pageProps.weeklyKpiTrends.totalRegistrants,
            pageProps.weeklyKpiTrends.completionRate,
        ];
        const charts = page.locator('[data-slot="weekly-kpi-chart"]');
        await expect(charts).toHaveCount(4);
        const chartTypes = await charts.evaluateAll((elements: Element[]): Array<string | null> =>
            elements.map((element: Element): string | null => element.getAttribute('data-chart-type'))
        );
        expect(chartTypes).toEqual(['line', 'line', 'line', 'line']);
        const trendLabels = page.locator('[data-slot="weekly-kpi-trend"]');
        await expect(trendLabels).toHaveCount(4);
        await expect(page.locator('[data-slot="weekly-kpi-chart"] canvas')).toHaveCount(4);

        for (const [index, points] of weeklySeries.entries()) {
            const currentValue = points[points.length - 1]?.value ?? 0;
            const previousValue = points[points.length - 2]?.value ?? 0;
            const absoluteDelta = index === 3
                ? `+${currentValue.toLocaleString('id-ID')} pp`
                : `+${currentValue.toLocaleString('id-ID')}`;
            const expectedTrendLabel = previousValue === 0
                ? currentValue === 0
                    ? 'Belum ada aktivitas minggu ini'
                    : `Baru minggu ini · ${absoluteDelta}`
                : currentValue === previousValue
                  ? 'Tetap · 0% dari minggu lalu'
                  : currentValue > previousValue
                    ? `Naik ${Math.round(((currentValue - previousValue) / previousValue) * 100)}% dari minggu lalu`
                    : `Turun ${Math.abs(Math.round(((currentValue - previousValue) / previousValue) * 100))}% dari minggu lalu`;

            await expect(trendLabels.nth(index)).toHaveText(expectedTrendLabel);
        }

        const allZeroSeriesIndexes: number[] = [];

        for (const [index, points] of weeklySeries.entries()) {
            const zeroCount = points.filter((point) => point.value === 0).length;
            const markers = charts.nth(index).locator('[data-slot="weekly-kpi-zero-marker"]');
            await expect(markers).toHaveCount(zeroCount);

            if (zeroCount === points.length) allZeroSeriesIndexes.push(index);

            if (zeroCount > 0) {
                const markerBounds = await markers.first().boundingBox();
                expect(markerBounds?.width ?? 0).toBeGreaterThan(0);
                expect(markerBounds?.height ?? 0).toBeGreaterThan(0);
            }
        }

        expect(allZeroSeriesIndexes.length).toBeGreaterThan(0);

        const metricSection = page.getByRole('heading', { name: 'KPI', exact: true }).locator('xpath=..');
        const activitySection = page.getByRole('heading', { name: 'Aktivitas terbaru', exact: true }).locator('xpath=..');
        const metricBounds = await metricSection.boundingBox();
        const activityBounds = await activitySection.boundingBox();
        const sectionGap = (activityBounds?.y ?? 0) - ((metricBounds?.y ?? 0) + (metricBounds?.height ?? 0));
        expect(sectionGap).toBeLessThanOrEqual(32);

        const cardHeights = await metricSection.locator(':scope > div.grid > *').evaluateAll((cards: Element[]) =>
            cards.map((card: Element) => card.getBoundingClientRect().height)
        );
        expect(cardHeights).toHaveLength(4);
        expect(Math.max(...cardHeights)).toBeLessThanOrEqual(190);

        const canvas = charts.first().locator('canvas');
        const box = await canvas.boundingBox();
        expect(box).not.toBeNull();

        if (box === null) {
            throw new Error('Weekly KPI chart canvas is not measurable.');
        }

        await page.mouse.move(box.x + box.width * 0.15, box.y + box.height * 0.5);
        await expect(page.locator('[data-slot="weekly-kpi-tooltip"]').first()).toBeVisible();
        await expect(page.locator('[data-slot="weekly-kpi-tooltip"]').first()).toContainText(/\d/);
        await expect(page.locator('[data-slot="weekly-kpi-tooltip"]').first()).toHaveClass(/bg-popover/);
        const firstTooltipPosition = await page.locator('[data-slot="weekly-kpi-tooltip"]').first().boundingBox();

        await page.mouse.move(box.x + box.width * 0.85, box.y + box.height * 0.5);
        await expect(page.locator('[data-slot="weekly-kpi-tooltip"]').first()).toBeVisible();
        const secondTooltipPosition = await page.locator('[data-slot="weekly-kpi-tooltip"]').first().boundingBox();

        expect(firstTooltipPosition).not.toBeNull();
        expect(secondTooltipPosition).not.toBeNull();
        if (firstTooltipPosition === null || secondTooltipPosition === null) {
            throw new Error('Weekly KPI tooltip position is unavailable.');
        }
        expect(Math.abs(secondTooltipPosition.x - firstTooltipPosition.x)).toBeGreaterThan(20);

        await page.mouse.move(0, 0);
        await expect(page.locator('[data-slot="weekly-kpi-tooltip"]')).toHaveCount(0);

        const hasHorizontalOverflow = await page.evaluate(
            (): boolean => document.documentElement.scrollWidth > document.documentElement.clientWidth
        );
        expect(hasHorizontalOverflow).toBe(false);
    });
});
