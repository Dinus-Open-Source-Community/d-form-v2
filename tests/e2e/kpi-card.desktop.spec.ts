import { expect, test } from './support/fixtures';

test.describe('editorial KPI cards', () => {
    test('keeps the metric hierarchy and renders transparent decorative icons', async ({ page }) => {
        await page.route('**/storage/**', (route) => route.abort());
        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

        await expect(page.getByRole('heading', { name: 'KPI', exact: true })).toBeVisible();

        for (const label of ['Total acara', 'Acara aktif', 'Total pendaftar', 'Tingkat isi kuota']) {
            await expect(page.getByText(label, { exact: true })).toBeVisible();
        }

        const iconSurfaces = page.locator('[data-slot="kpi-icon"]');
        await expect(iconSurfaces).toHaveCount(4);
        await expect(iconSurfaces.locator('svg[aria-hidden="true"]')).toHaveCount(4);

        const backgrounds = await iconSurfaces.evaluateAll((elements: Element[]): string[] =>
            elements.map((element: Element): string => getComputedStyle(element).backgroundColor)
        );

        expect(backgrounds.every((color: string): boolean => color === 'rgba(0, 0, 0, 0)')).toBe(true);
    });
});
