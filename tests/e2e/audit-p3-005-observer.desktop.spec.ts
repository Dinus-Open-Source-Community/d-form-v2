import { expect, test } from '@playwright/test';

declare global {
    interface Window {
        auditP3ObserverCount: number;
    }
}

test.describe('AUDIT-P3-005 landing observer lifecycle', () => {
    test('disconnects landing observers during back-and-forth navigation', async ({ page }) => {
        await page.addInitScript((): void => {
            const nativeObserver = globalThis.IntersectionObserver;
            let activeObserverCount = 0;

            class TrackingIntersectionObserver extends nativeObserver {
                private disconnected = false;

                public constructor(callback: IntersectionObserverCallback, options?: IntersectionObserverInit) {
                    super(callback, options);
                    activeObserverCount += 1;
                }

                public override disconnect(): void {
                    if (this.disconnected) {
                        super.disconnect();
                        return;
                    }

                    this.disconnected = true;
                    activeObserverCount = Math.max(0, activeObserverCount - 1);
                    super.disconnect();
                }
            }

            globalThis.IntersectionObserver = TrackingIntersectionObserver;
            window.auditP3ObserverCount = activeObserverCount;
            Object.defineProperty(window, 'auditP3ObserverCount', {
                configurable: true,
                get: (): number => activeObserverCount,
            });
        });

        await page.goto('/', { waitUntil: 'networkidle' });
        await expect.poll(() => page.evaluate(() => window.auditP3ObserverCount)).toBeGreaterThan(0);

        await page.goto('/features', { waitUntil: 'networkidle' });
        await expect.poll(() => page.evaluate(() => window.auditP3ObserverCount)).toBe(0);

        await page.goBack({ waitUntil: 'networkidle' });
        await expect.poll(() => page.evaluate(() => window.auditP3ObserverCount)).toBeGreaterThan(0);
    });
});
