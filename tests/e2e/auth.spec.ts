import { expect, test, type APIResponse, type Page } from '@playwright/test'
import { runArtisan } from './support/artisan'

interface ViteManifestEntry {
    file: string
    isEntry?: boolean
    isDynamicEntry?: boolean
    src?: string
    dynamicImports?: string[]
}

type ViteManifest = Record<string, ViteManifestEntry>

const STRESS_USER = {
    email: 'stress-user-1@stress.test',
    password: 'password',
} as const

const VENDOR_MARKERS = ['tiptap', 'prosemirror', 'html5-qrcode', 'vue3-lottie', 'chart.js'] as const

/** Ambil token CSRF dari cookie XSRF-TOKEN milik context halaman. */
async function readXsrfToken(page: Page): Promise<string> {
    const cookies = await page.context().cookies()
    const cookie = cookies.find((entry) => entry.name === 'XSRF-TOKEN')
    return cookie ? decodeURIComponent(cookie.value) : ''
}

/** Kirim POST tanpa mengikuti redirect, memakai cookie + CSRF dari halaman. */
async function postAuth(
    page: Page,
    path: string,
    payload: Record<string, string>,
    accept: 'html' | 'json' = 'html',
): Promise<APIResponse> {
    const token = await readXsrfToken(page)

    return page.context().request.post(path, {
        form: payload,
        headers: {
            'X-XSRF-TOKEN': token,
            Accept: accept === 'json' ? 'application/json' : 'text/html',
        },
        maxRedirects: 0,
        failOnStatusCode: false,
    })
}

/** Kosongkan bucket limiter berbasis IP agar hasil test deterministik lintas run. */
function resetThrottleBuckets(): void {
    const limiterNames = ['auth-register', 'auth-password-email', 'auth-password-link', 'auth-password-reset']
    const calls = limiterNames
        .map((name) => `\\Illuminate\\Support\\Facades\\RateLimiter::clear(md5('${name}'.'127.0.0.1'));`)
        .join(' ')

    runArtisan(calls)
}

test.describe('auth login', () => {
    test('login sukses membuat sesi dan mengarah ke dashboard', async ({ page }) => {
        // Render /dashboard tidak diuji di sini: dengan dataset stress (943k form_answers)
        // query dashboard berjalan >7 menit, di luar scope optimasi auth.
        await page.goto('/auth/login')

        const response = await postAuth(page, '/auth/login', {
            email: STRESS_USER.email,
            password: STRESS_USER.password,
        })

        expect(response.status()).toBe(302)
        expect(response.headers()['location']).toContain('/dashboard')

        const afterLogin = await page.context().request.get('/auth/login', {
            maxRedirects: 0,
            failOnStatusCode: false,
        })
        expect(afterLogin.status()).toBe(302)
    })

    test('login gagal menampilkan pesan netral', async ({ page }) => {
        await page.goto('/auth/login')
        await page.getByLabel(/email/i).fill(`e2e-login-fail-${Date.now()}@example.test`)
        await page.getByLabel(/password/i).fill('password-salah')
        await page.getByRole('button', { name: /masuk|login|sign in/i }).click()

        await expect(
            page.locator('[data-sonner-toast]').filter({ hasText: /gagal masuk/i }),
        ).toBeVisible({ timeout: 15_000 })
        await expect(page).toHaveURL(/\/auth\/login/)
    })

    test('validasi gagal menampilkan error field', async ({ page }) => {
        await page.goto('/auth/login')

        // Validasi native browser dimatikan agar jalur validasi server yang diuji.
        const form = page.locator('form').filter({ has: page.locator('#login-email') })
        await form.evaluate((element: HTMLFormElement) => {
            element.noValidate = true
        })

        await page.getByLabel(/email/i).fill('bukan-email')
        await page.getByRole('button', { name: /masuk|login|sign in/i }).click()

        await expect(form.locator('.text-destructive').filter({ hasText: /valid email address/i })).toBeVisible()
        await expect(page).toHaveURL(/\/auth\/login/)
    })
})

test.describe('bundle', () => {
    test('manifest membagi page menjadi dynamic chunk', async ({ page }) => {
        const response = await page.request.get('/build/manifest.json')
        expect(response.ok()).toBeTruthy()

        const manifest = (await response.json()) as ViteManifest
        const dynamicEntries = Object.values(manifest).filter((entry) => entry.isDynamicEntry)

        expect(dynamicEntries.length).toBeGreaterThan(0)
        expect(manifest['resources/js/pages/Auth/Login.vue']).toBeTruthy()
    })

    test('entry tidak memuat vendor berat', async ({ page }) => {
        const response = await page.request.get('/build/manifest.json')
        const manifest = (await response.json()) as ViteManifest
        const entry = manifest['resources/js/app.js']

        expect(entry?.file).toBeTruthy()

        const bundle = await page.request.get(`/build/${entry.file}`)
        expect(bundle.ok()).toBeTruthy()
        const source = await bundle.text()

        for (const marker of VENDOR_MARKERS) {
            expect(source, `entry masih memuat "${marker}"`).not.toContain(marker)
        }
    })
})

test.describe('throttle', () => {
    test.beforeEach(() => {
        resetThrottleBuckets()
    })

    test('login melewati limit mengembalikan 429 + Retry-After', async ({ page }) => {
        const email = `e2e-throttle-${Date.now()}@example.test`

        await page.goto('/auth/login')

        const attempts: APIResponse[] = []
        for (let index = 0; index < 5; index += 1) {
            attempts.push(await postAuth(page, '/auth/login', { email, password: 'salah' }))
        }

        for (const attempt of attempts) {
            expect(attempt.status()).toBe(302)
        }

        const limited = await postAuth(page, '/auth/login', { email, password: 'salah' })
        expect(limited.status()).toBe(429)
        expect(Number(limited.headers()['retry-after'])).toBeGreaterThan(0)

        await page.getByLabel(/email/i).fill(email)
        await page.getByLabel(/password/i).fill('salah')

        const submission = page.waitForResponse(
            (response) => response.url().includes('/auth/login') && response.request().method() === 'POST',
        )
        await page.getByRole('button', { name: /masuk|login|sign in/i }).click()

        const blocked = await submission
        expect(blocked.status()).toBe(429)
        expect(Number(blocked.headers()['retry-after'])).toBeGreaterThan(0)
        await expect(page.getByText(/terlalu banyak|coba lagi dalam/i).first()).toBeVisible()
    })

    test('register dibatasi per IP', async ({ page }) => {
        await page.goto('/auth/register')

        const attempts: APIResponse[] = []
        for (let index = 0; index < 3; index += 1) {
            attempts.push(
                await postAuth(page, '/auth/register', {
                    name: 'E2E Throttle',
                    email: `e2e-register-${Date.now()}-${index}@example.test`,
                    password: 'pendek',
                    password_confirmation: 'pendek',
                }),
            )
        }

        const limited = await postAuth(page, '/auth/register', {
            name: 'E2E Throttle',
            email: `e2e-register-${Date.now()}-final@example.test`,
            password: 'pendek',
            password_confirmation: 'pendek',
        })

        expect(limited.status()).toBe(429)
        expect(Number(limited.headers()['retry-after'])).toBeGreaterThan(0)
    })

    test('kuota forgot-password tidak memotong password-reset-link', async ({ page }) => {
        await page.goto('/auth/forgot-password')

        const first = await postAuth(page, '/auth/forgot-password', { email: 'e2e-forgot-0@example.test' })
        expect(first.status()).toBe(302)

        for (let index = 1; index < 5; index += 1) {
            const attempt = await postAuth(page, '/auth/forgot-password', {
                email: `e2e-forgot-${index}@example.test`,
            })
            expect(attempt.status()).toBe(302)
        }

        const blocked = await postAuth(page, '/auth/forgot-password', { email: 'e2e-forgot-blocked@example.test' })
        expect(blocked.status()).toBe(429)

        const link = await postAuth(
            page,
            '/auth/password-reset-link',
            { email: 'e2e-reset-link@example.test' },
            'json',
        )

        expect(link.status()).toBe(200)
    })
})
