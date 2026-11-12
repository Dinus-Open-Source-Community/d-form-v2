import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

/**
 * E2E FE-AUDIT-004: membuktikan page/component memakai adapter, bukan `axios`
 * atau `fetch` langsung. Yang diuji adalah kontrak yang dijaga adapter:
 * header JSON, body request, pemetaan 422 ke field error, dan guard submit ganda.
 *
 * Alur forgot-password dipilih karena tidak butuh fixture database dan endpoint
 * JSON-nya mengembalikan kontrak stabil (`{ message }` atau 422 `{ errors }`).
 */

const PASSWORD_RESET_LINK_PATH = '/auth/password-reset-link';
const FORGOT_PASSWORD_PATH = '/auth/forgot-password';

/** Jalankan test sebagai guest: jangan pakai storageState admin dari project. */
test.use({ storageState: { cookies: [], origins: [] } });

/** Buka halaman forgot-password dan tunggu form siap. */
async function openForgotPassword(page: Page): Promise<Locator> {
    await page.goto(FORGOT_PASSWORD_PATH, { waitUntil: 'domcontentloaded' });
    const emailInput = page.getByLabel('Email');
    await expect(emailInput).toBeVisible();

    return emailInput;
}

test.describe('FE-AUDIT-004 adapter boundary', () => {
    test('forgot password mengirim JSON dengan header adapter dan menampilkan pesan sukses', async ({ page }) => {
        const emailInput = await openForgotPassword(page);
        const requestPromise = page.waitForRequest(
            (request) => request.url().includes(PASSWORD_RESET_LINK_PATH) && request.method() === 'POST'
        );

        await emailInput.fill(`e2e-adapter-${Date.now()}@example.test`);
        await page.getByRole('button', { name: /kirim tautan reset/i }).click();

        const request = await requestPromise;
        const headers = request.headers();

        expect(headers['accept'] ?? '').toContain('application/json');
        expect(headers['x-requested-with']).toBe('XMLHttpRequest');
        expect(headers['content-type'] ?? '').toContain('application/json');

        const body: unknown = request.postDataJSON();
        expect(body).toMatchObject({ email: expect.stringContaining('@example.test') });

        await expect(
            page.getByText(/Jika akun dengan email tersebut ada, kami telah mengirim tautan reset kata sandi/i)
        ).toBeVisible();
    });

    test('anomali email tidak valid di server dipetakan 422 ke error field, bukan toast generik', async ({ page }) => {
        const emailInput = await openForgotPassword(page);
        const responsePromise = page.waitForResponse(
            (response) => response.url().includes(PASSWORD_RESET_LINK_PATH) && response.request().method() === 'POST'
        );

        // Isi nilai yang ditolak rule `email` server, lalu kirim lewat event submit
        // agar validasi native `type=email` tidak memblokir request (yang diuji adalah adapter 422).
        await emailInput.fill('bukan-email');
        await emailInput.locator('xpath=ancestor::form').evaluate((form): void => {
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        });

        const response = await responsePromise;
        expect(response.status()).toBe(422);

        const body: unknown = await response.json();
        expect(body).toMatchObject({ errors: expect.objectContaining({ email: expect.any(Array) }) });

        await expect(page.getByText(/valid Email address format/i).first()).toBeVisible();
        await expect(page.getByText(/Jika akun dengan email tersebut ada/i)).toHaveCount(0);
    });

    test('dua submit beruntun hanya mengirim satu request', async ({ page }) => {
        const emailInput = await openForgotPassword(page);
        let requestCount = 0;
        page.on('request', (request) => {
            if (request.url().includes(PASSWORD_RESET_LINK_PATH) && request.method() === 'POST') {
                requestCount += 1;
            }
        });
        // Tahan respons agar request pertama masih in-flight saat submit kedua.
        await page.route(`**${PASSWORD_RESET_LINK_PATH}`, async (route) => {
            await new Promise((resolve): void => {
                setTimeout(resolve, 250);
            });
            await route.continue();
        });

        const responsePromise = page.waitForResponse(
            (response) => response.url().includes(PASSWORD_RESET_LINK_PATH) && response.request().method() === 'POST'
        );

        await emailInput.fill(`e2e-double-${Date.now()}@example.test`);
        await emailInput.locator('xpath=ancestor::form').evaluate((form): void => {
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        });
        await responsePromise;
        await page.waitForTimeout(600);

        expect(requestCount).toBe(1);
    });

    test('email kosong ditolak sebelum request jaringan', async ({ page }) => {
        await openForgotPassword(page);
        let requestCount = 0;
        page.on('request', (request) => {
            if (request.url().includes(PASSWORD_RESET_LINK_PATH) && request.method() === 'POST') {
                requestCount += 1;
            }
        });

        await page.getByRole('button', { name: /kirim tautan reset/i }).click();
        await page.waitForTimeout(400);

        expect(requestCount).toBe(0);
    });
});
