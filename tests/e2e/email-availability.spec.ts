import { expect, test, type Locator, type Page } from '@playwright/test'
import { runArtisan } from './support/artisan'

const TAKEN_EMAIL = 'stress-user-1@stress.test'
const SCREENSHOT_DIR = '/tmp/opencode'
const STATUS_SELECTOR = '[data-email-availability]'

/** Kosongkan cache limiter agar kuota 20/menit tidak bocor antar test. */
function resetThrottleBuckets(): void {
    runArtisan('\\Illuminate\\Support\\Facades\\Cache::flush();')
}

/** Container input email register, agar tidak tertukar dengan form login yang ikut ter-mount. */
function registerEmailGroup(page: Page): Locator {
    return page.locator('[data-slot="input-group"]').filter({ has: page.locator('#register-email') })
}

interface IFieldSpacing {
    nameToEmailGap: number
    emailToPasswordGap: number
    emailHeight: number
    passwordHeight: number
}

test.describe('email availability', () => {
    test.beforeEach(() => {
        resetThrottleBuckets()
    })

    test('email terpakai menampilkan ikon X', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 })

        // Perlambat respons agar status checking sempat terlihat dan bisa di-screenshot.
        await page.route('**/auth/email-availability', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 800))
            await route.continue()
        })

        await page.goto('/auth/register')

        const status = registerEmailGroup(page).locator(STATUS_SELECTOR)

        await page.locator('#register-email').fill(TAKEN_EMAIL)
        await expect(status).toHaveAttribute('data-email-availability', 'checking', { timeout: 3_000 })
        await page.screenshot({ path: `${SCREENSHOT_DIR}/email-check-loading.png` })

        await expect(status).toHaveAttribute('data-email-availability', 'taken', { timeout: 5_000 })
        await expect(status.locator('svg')).toHaveClass(/text-destructive/)
        await page.screenshot({ path: `${SCREENSHOT_DIR}/email-check-taken.png` })
    })

    test('email baru menampilkan ikon centang', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 })
        await page.goto('/auth/register')

        const status = registerEmailGroup(page).locator(STATUS_SELECTOR)
        const email = `e2e-available-${Date.now()}@example.test`

        await page.locator('#register-email').fill(email)

        await expect(status).toHaveAttribute('data-email-availability', 'available', { timeout: 5_000 })
        await expect(status.locator('svg')).toHaveClass(/text-success/)
        await expect(page.locator('div[aria-live="polite"]').filter({ hasText: 'Email tersedia.' })).toBeAttached()
        await page.screenshot({ path: `${SCREENSHOT_DIR}/email-check-available.png` })
    })

    test('debounce meringkas ketikan cepat menjadi maksimal dua request', async ({ page }) => {
        await page.goto('/auth/register')

        const input = page.locator('#register-email')
        // Domain tanpa TLD belum valid, jadi pengisian awal tidak memicu request.
        await input.fill(`e2e-debounce-${Date.now()}@example`)

        let requestCount = 0
        page.on('request', (request) => {
            if (request.url().includes('/auth/email-availability')) requestCount += 1
        })

        // Lima karakter terakhir diketik cepat; hanya satu pengecekan yang boleh lolos debounce.
        await input.pressSequentially('.test', { delay: 20 })

        await expect(registerEmailGroup(page).locator(STATUS_SELECTOR)).toHaveAttribute(
            'data-email-availability',
            'available',
            { timeout: 5_000 },
        )
        await page.waitForTimeout(600)

        expect(requestCount).toBeGreaterThan(0)
        expect(requestCount).toBeLessThanOrEqual(2)
    })

    test('live region email tidak menambah jarak antar-field', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 })
        await page.goto('/auth/register')
        await page.locator('#register-email').waitFor()

        const spacing = await page.evaluate((): IFieldSpacing => {
            const group = (id: string): HTMLElement => {
                const wrapper = document.querySelector(`#${id}`)?.closest('[data-slot="input-group"]')
                if (!(wrapper instanceof HTMLElement)) throw new Error(`input group ${id} tidak ditemukan`)
                return wrapper
            }
            const labelTop = (id: string): number => {
                const label = document.querySelector(`label[for="${id}"]`)
                if (!(label instanceof HTMLElement)) throw new Error(`label ${id} tidak ditemukan`)
                return label.getBoundingClientRect().top
            }
            const name = group('register-name')
            const email = group('register-email')
            const password = group('register-password')
            return {
                nameToEmailGap: labelTop('register-email') - name.getBoundingClientRect().bottom,
                emailToPasswordGap: labelTop('register-password') - email.getBoundingClientRect().bottom,
                emailHeight: email.getBoundingClientRect().height,
                passwordHeight: password.getBoundingClientRect().height,
            }
        })

        expect(Math.abs(spacing.emailToPasswordGap - spacing.nameToEmailGap)).toBeLessThanOrEqual(2)
        expect(spacing.emailHeight).toBe(spacing.passwordHeight)
    })
})
