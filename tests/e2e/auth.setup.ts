import { mkdir, rename, rm } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { expect, test as setup } from './support/fixtures';
import { ADMIN_STATE_PATH, sessionCookieName } from './support/authGuard';
import { readEnvironment } from './support/environment';

/**
 * Login satu kali sebagai admin seeder lalu simpan storageState ke `tests/e2e/.auth/admin.json`.
 * Kredensial hanya dibaca dari environment; file ditulis lewat temp + rename agar tidak setengah jadi.
 */
setup('H-auth-setup: login admin dan simpan storageState', async ({ page, context }): Promise<void> => {
    const environment = readEnvironment();

    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error(
            'E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD must both be set to the seeded admin credentials before running the setup project.',
        );
    }

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });

    const xsrfCookie = (await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN');
    const xsrfToken = xsrfCookie === undefined ? '' : decodeURIComponent(xsrfCookie.value);

    const loginResponse = await context.request.post('/auth/login', {
        form: {
            email: environment.adminEmail,
            password: environment.adminPassword,
        },
        headers: {
            'X-XSRF-TOKEN': xsrfToken,
            Accept: 'text/html',
        },
        maxRedirects: 0,
        failOnStatusCode: false,
    });

    expect([200, 302]).toContain(loginResponse.status());

    const dashboardResponse = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    expect(new URL(page.url()).pathname).toBe('/dashboard');
    expect(dashboardResponse === null ? 0 : dashboardResponse.status()).toBe(200);

    const sessionCookie = (await context.cookies()).find(
        (cookie) => cookie.name === sessionCookieName() && cookie.value !== '',
    );

    expect(
        sessionCookie,
        `setup must end with a "${sessionCookieName()}" cookie`,
    ).toBeTruthy();

    const statePath = resolve(process.cwd(), ADMIN_STATE_PATH);
    const tempPath = `${statePath}.${process.pid}.${Date.now()}.tmp`;

    await mkdir(dirname(statePath), { recursive: true });

    try {
        await context.storageState({ path: tempPath });
        await rename(tempPath, statePath);
    } catch (error) {
        await rm(tempPath, { force: true });
        throw error;
    }
});
