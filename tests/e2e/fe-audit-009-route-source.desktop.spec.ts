import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';

/**
 * Regression for FE-AUDIT-009: `lib/routes.ts` is a thin adapter over generated
 * Wayfinder helpers. Guest and admin navigation must resolve to the same URLs as
 * the previous manual registry, and unknown paths must stay 404 instead of 5xx.
 */

/** Kumpulkan pageerror dan response 5xx; keduanya harus kosong di akhir test. */
function recordDiagnostics(page: Page): { pageErrors: string[]; serverErrors: string[] } {
    const pageErrors: string[] = [];
    const serverErrors: string[] = [];

    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('response', (response) => {
        if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
    });

    return { pageErrors, serverErrors };
}

function expectNoDiagnostics(diagnostics: { pageErrors: string[]; serverErrors: string[] }): void {
    expect(diagnostics.serverErrors, diagnostics.serverErrors.join('\n')).toEqual([]);
    expect(diagnostics.pageErrors, diagnostics.pageErrors.join('\n')).toEqual([]);
}

async function expectStatus(page: Page, path: string, status: number): Promise<void> {
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
    expect(response?.status(), `status for ${path}`).toBe(status);
    // A guard redirect would also answer 200, so pin the final path as well.
    expect(new URL(page.url()).pathname, `final path for ${path}`).toBe(path);
    await expect(page.locator('body')).toBeVisible();
}

test.describe('guest navigation', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('landing nav links come from the route adapter', async ({ page }) => {
        const diagnostics = recordDiagnostics(page);
        await expectStatus(page, '/', 200);

        const expectedLinks = [
            { name: 'Fitur', href: '/features' },
            { name: 'Acara', href: '/events' },
            { name: 'Dokumentasi', href: '/docs' },
        ];

        for (const link of expectedLinks) {
            const anchor = page.getByRole('link', { name: link.name, exact: true }).first();
            await expect(anchor).toHaveAttribute('href', link.href);
        }

        await expectStatus(page, '/recruitment', 200);
        expectNoDiagnostics(diagnostics);
    });

    test('events list opens a detail page from its own link', async ({ page }) => {
        const diagnostics = recordDiagnostics(page);
        await expectStatus(page, '/events', 200);

        const firstEventLink = page.locator('a[href^="/events/"]').first();
        await expect(firstEventLink).toBeVisible();

        const href = await firstEventLink.getAttribute('href');
        expect(href, 'event card must expose an href').not.toBeNull();

        if (href === null) {
            return;
        }

        const [response] = await Promise.all([
            page.waitForResponse((res) => res.request().method() === 'GET' && new URL(res.url()).pathname === href),
            firstEventLink.click(),
        ]);

        expect(new URL(page.url()).pathname).toBe(href);
        expect(response.status()).toBe(200);
        expectNoDiagnostics(diagnostics);
    });

    test('auth pages render', async ({ page }) => {
        const diagnostics = recordDiagnostics(page);

        for (const path of ['/auth/login', '/auth/register', '/auth/forgot-password']) {
            await expectStatus(page, path, 200);
        }

        expectNoDiagnostics(diagnostics);
    });

    test('unknown paths stay 404', async ({ page }) => {
        const diagnostics = recordDiagnostics(page);

        await expectStatus(page, '/events/__tidak_ada__', 404);
        await expectStatus(page, '/__route_tidak_ada__', 404);

        expectNoDiagnostics(diagnostics);
    });
});

test.describe('admin navigation', () => {
    test('dashboard pages resolve through the adapter', async ({ page }) => {
        const diagnostics = recordDiagnostics(page);

        for (const path of ['/dashboard', '/admin/events', '/admin/users', '/admin/recruitment', '/admin/scan']) {
            await expectStatus(page, path, 200);
        }

        expectNoDiagnostics(diagnostics);
    });

    test('sidebar links use the route builders', async ({ page }) => {
        const diagnostics = recordDiagnostics(page);

        await expectStatus(page, '/dashboard', 200);

        // Tautan flat sidebar dashboard. "Pusat kerja" berada di submenu
        // Rekrutmen dan baru dirender saat route rekrutmen aktif.
        for (const href of ['/admin/events', '/admin/users', '/admin/scan']) {
            await expect(page.locator(`a[href="${href}"]`).first(), `sidebar ${href}`).toBeVisible();
        }

        await expectStatus(page, '/admin/recruitment', 200);
        await expect(page.locator('a[href="/admin/recruitment"]').first(), 'sidebar /admin/recruitment').toBeVisible();

        expectNoDiagnostics(diagnostics);
    });

    test('unknown recruitment period is a 404, not a server error', async ({ page }) => {
        const diagnostics = recordDiagnostics(page);

        await expectStatus(page, '/admin/recruitment/periods/__tidak_ada__', 404);

        expectNoDiagnostics(diagnostics);
    });
});
