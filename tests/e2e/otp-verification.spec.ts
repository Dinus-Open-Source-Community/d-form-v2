import { expect, test, type Locator, type Page } from '@playwright/test'
import { runArtisan, runArtisanCommand } from './support/artisan'

const PASSWORD = 'Rahasia123'

/** Email unik per test supaya baris pendaftaran tertunda tidak bertabrakan. */
function createTestEmail(prefix: string): string {
    return `e2e-otp-${prefix}-${Date.now()}@example.test`
}

/** Daftar akun baru lewat form register lalu tunggu halaman verifikasi OTP tampil. */
async function registerUntilOtpPage(page: Page, email: string): Promise<void> {
    await page.goto('/auth/register')
    await page.locator('#register-name').fill('E2E OTP')
    await page.locator('#register-email').fill(email)
    await page.locator('#register-password').fill(PASSWORD)
    await page.locator('#register-password-confirmation').fill(PASSWORD)
    await page.getByRole('button', { name: /buat akun/i }).click()

    await expect(page).toHaveURL(/\/auth\/verify-otp/)
}

/** Enam kotak kode OTP pada halaman verifikasi. */
function otpBoxes(page: Page): Locator {
    return page.locator('input[inputmode="numeric"]')
}

/** Baca kode OTP aktif milik email lewat command artisan `otp:current`. */
function readActiveOtp(email: string): string {
    return runArtisanCommand(['otp:current', email])
}

/** Kode salah yang dijamin berbeda dari kode asli: digit pertama diganti. */
function toWrongCode(code: string): string {
    return (code.startsWith('9') ? '1' : '9') + code.slice(1)
}

/** Kosongkan bucket rate limiter agar beberapa test boleh mendaftar dalam satu menit (pola sama dengan auth.spec). */
function resetThrottleBuckets(): void {
    runArtisan('\\Illuminate\\Support\\Facades\\Cache::flush();')
}

test.describe('verifikasi OTP', () => {
    test.beforeEach(() => {
        resetThrottleBuckets()
    })

    test('daftar menampilkan halaman OTP dengan hitung mundur berjalan', async ({ page }) => {
        const email = createTestEmail('halaman')

        await registerUntilOtpPage(page, email)

        await expect(page.getByRole('heading', { name: 'Check your messages' })).toBeVisible()
        await expect(page.getByTestId('otp-email')).toHaveText(email)

        const boxes = otpBoxes(page)
        await expect(boxes).toHaveCount(6)
        await expect(boxes.first()).toBeFocused()

        const countdown = page.getByTestId('otp-countdown')
        await expect(countdown).toHaveText(/^\d{2}:\d{2}$/)

        const before = await countdown.textContent()
        await expect(countdown).not.toHaveText(before ?? '', { timeout: 4_000 })
    })

    test('paste 6 digit mengisi semua kotak', async ({ page }) => {
        await registerUntilOtpPage(page, createTestEmail('paste'))

        await otpBoxes(page).first().click()

        // Dispatch event paste manual: clipboard OS tidak tersedia di headless tanpa izin.
        await page.evaluate(() => {
            const input = document.querySelector('input[inputmode="numeric"]')

            if (input === null) throw new Error('Kotak OTP tidak ditemukan')

            const data = new DataTransfer()
            data.setData('text/plain', '135792')
            input.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true }))
        })

        for (const [index, digit] of ['1', '3', '5', '7', '9', '2'].entries()) {
            await expect(otpBoxes(page).nth(index)).toHaveValue(digit)
        }
    })

    test('kode salah menampilkan pesan error di bawah kotak', async ({ page }) => {
        const email = createTestEmail('salah')

        await registerUntilOtpPage(page, email)

        const wrongCode = toWrongCode(readActiveOtp(email))

        await otpBoxes(page).first().click()
        await page.keyboard.type(wrongCode)
        await page.getByRole('button', { name: /^verifikasi$/i }).click()

        await expect(page.getByRole('alert')).toContainText(/kode verifikasi salah/i)
        await expect(page).toHaveURL(/\/auth\/verify-otp/)
    })

    test('kirim ulang menampilkan cooldown dan mengurangi sisa kirim', async ({ page }) => {
        // Cooldown kirim ulang 60 detik menurut server, jadi test ini sengaja menunggu.
        test.setTimeout(150_000)

        await registerUntilOtpPage(page, createTestEmail('resend'))

        await expect(page.getByTestId('otp-sends-remaining')).toHaveText(/sisa kirim\s*2/i)

        const resendButton = page.getByRole('button', { name: /^kirim ulang$/i })
        await expect(resendButton).toBeDisabled()
        await expect(page.getByTestId('otp-resend-countdown')).toHaveText(/^\d{2}:\d{2}$/)

        await expect(resendButton).toBeEnabled({ timeout: 75_000 })
        await resendButton.click()

        await expect(page.getByTestId('otp-sends-remaining')).toHaveText(/sisa kirim\s*1/i)
        await expect(page.getByTestId('otp-resend-countdown')).toHaveText(/^\d{2}:\d{2}$/)
        await expect(resendButton).toBeDisabled()
    })

    test('kode benar mengarahkan ke dashboard', async ({ page }) => {
        const email = createTestEmail('benar')

        await registerUntilOtpPage(page, email)

        await otpBoxes(page).first().click()
        await page.keyboard.type(readActiveOtp(email))

        const [response] = await Promise.all([
            page.waitForResponse(
                (candidate) =>
                    candidate.request().method() === 'POST' && candidate.url().endsWith('/auth/verify-otp'),
            ),
            page.getByRole('button', { name: /^verifikasi$/i }).click(),
        ])

        // Render dashboard tidak ditunggu: dataset stress membuatnya lambat di luar scope OTP.
        expect(response.status()).toBe(302)
        expect(response.headers()['location']).toContain('/dashboard')
    })

    test('gunakan email lain kembali ke form pendaftaran', async ({ page }) => {
        await registerUntilOtpPage(page, createTestEmail('ganti'))

        await page.getByRole('button', { name: /gunakan email lain/i }).click()

        await expect(page).toHaveURL(/\/auth\/register/)
        await expect(page.getByRole('heading', { name: /buat akun/i })).toBeVisible()
    })
})
