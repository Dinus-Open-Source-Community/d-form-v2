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

/** Enam kotak kode OTP, dicari lewat grup berlabel agar tidak ikut menangkap input lain. */
function otpBoxes(page: Page): Locator {
    return page.getByRole('group', { name: /kode verifikasi/i }).getByRole('textbox')
}

/** Angka pada baris "Sisa kirim N". */
async function readSendsRemaining(page: Page): Promise<number> {
    const text = await page.getByTestId('otp-sends-remaining').innerText()

    return Number(text.replace(/\D/g, ''))
}

/** Ubah label hitung mundur `mm:ss` menjadi detik. */
function toSeconds(label: string): number {
    const [minutes = '0', seconds = '0'] = label.trim().split(':')

    return Number(minutes) * 60 + Number(seconds)
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

/**
 * Tunggu cooldown kirim ulang habis memakai nilai dari UI (bukan angka hardcode).
 * Bila state klien sempat ter-reset, muat ulang halaman agar props diambil ulang dari server.
 */
async function waitForResendAvailable(page: Page, resendButton: Locator): Promise<void> {
    const label = (await page.getByTestId('otp-resend-countdown').innerText().catch(() => '')).trim()

    if (label !== '') await page.waitForTimeout((toSeconds(label) + 3) * 1000)

    if (await resendButton.isEnabled().catch(() => false)) return

    await page.reload()
    await expect(resendButton).toBeEnabled()
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
            const input = document.querySelector('input[aria-label="Digit 1 dari 6"]')

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

    test('menolak karakter non-digit dan backspace mundur satu kotak', async ({ page }) => {
        await registerUntilOtpPage(page, createTestEmail('ketik'))

        const boxes = otpBoxes(page)

        await boxes.first().click()
        // "a" harus dibuang tanpa menggeser fokus, lalu "3" tetap masuk ke kotak ketiga.
        await page.keyboard.type('12a3')

        await expect(boxes.nth(0)).toHaveValue('1')
        await expect(boxes.nth(1)).toHaveValue('2')
        await expect(boxes.nth(2)).toHaveValue('3')

        // Fokus ada di kotak kosong keempat: backspace harus menghapus kotak ketiga dan memundurkannya.
        await page.keyboard.press('Backspace')

        await expect(boxes.nth(2)).toHaveValue('')
        await expect(boxes.nth(2)).toBeFocused()
        await expect(boxes.nth(1)).toHaveValue('2')
    })

    test('kode kedaluwarsa: live region aktif dan fokus pindah ke kirim ulang', async ({ page }) => {
        // Jam palsu memajukan 10 menit supaya TTL OTP habis tanpa menunggu waktu nyata.
        await page.clock.install()

        await registerUntilOtpPage(page, createTestEmail('kedaluwarsa'))

        await expect(page.getByTestId('otp-countdown')).toBeVisible()

        await page.clock.fastForward(600_000)

        await expect(page.getByTestId('otp-expired')).toBeVisible()
        await expect(page.getByTestId('otp-expiry-live')).toHaveText(/kedaluwarsa/i)
        await expect(page.getByRole('button', { name: /^kirim ulang$/i })).toBeFocused()
        await expect(page.getByRole('button', { name: /^verifikasi$/i })).toBeDisabled()
    })

    test('kirim ulang: cooldown, sisa kirim berkurang, dan timer hidup lagi setelah kedaluwarsa', async ({ page }) => {
        // Cooldown kirim ulang mengikuti config server (60 detik); waktu tunggu dibaca dari UI, bukan hardcode.
        test.setTimeout(120_000)

        await registerUntilOtpPage(page, createTestEmail('resend'))

        const resendButton = page.getByRole('button', { name: /^kirim ulang$/i })
        const resendCountdown = page.getByTestId('otp-resend-countdown')

        const sendsBefore = await readSendsRemaining(page)

        await expect(resendButton).toBeDisabled()
        await expect(resendCountdown).toHaveText(/^\d{2}:\d{2}$/)

        await waitForResendAvailable(page, resendButton)

        // Majukan jam sampai kode lama kedaluwarsa: kedua hitung mundur nol sehingga interval berhenti.
        const expirySeconds = toSeconds((await page.getByTestId('otp-countdown').innerText()).trim())
        await page.clock.install()
        await page.clock.fastForward(expirySeconds * 1000 + 1_000)

        await expect(page.getByTestId('otp-expired')).toBeVisible()
        await expect(resendButton).toBeFocused()

        await resendButton.click()

        await expect.poll(() => readSendsRemaining(page)).toBe(sendsBefore - 1)

        // Props diganti tanpa remount: timer yang sudah berhenti harus hidup lagi dan nilainya turun.
        const expiryCountdown = page.getByTestId('otp-countdown')
        await expect(expiryCountdown).toBeVisible()

        // Tombol kirim ulang sempat nonaktif selama request; fokus tidak boleh tertinggal di body.
        await expect(page.locator('body')).not.toBeFocused()

        const secondsBeforeTick = toSeconds((await expiryCountdown.innerText()).trim())
        expect(secondsBeforeTick).toBeGreaterThan(1)

        await page.clock.fastForward(3_000)

        await expect
            .poll(async () => toSeconds((await expiryCountdown.innerText()).trim()))
            .toBeLessThan(secondsBeforeTick)
    })

    test('kode benar mengarahkan ke dashboard', async ({ page }) => {
        const email = createTestEmail('benar')

        await registerUntilOtpPage(page, email)

        // Dashboard tidak pernah dirender: dataset stress membuatnya lambat dan menahan worker dev.
        await page.route('**/dashboard*', (route) => route.abort())

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
