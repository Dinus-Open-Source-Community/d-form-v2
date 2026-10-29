import { expect, test, type Locator, type Page, type Response } from '@playwright/test'
import { runArtisan, runArtisanCommand } from './support/artisan'

// Alur pendaftaran OTP dimulai dari halaman guest; state admin project akan di-redirect 302.
test.use({ storageState: { cookies: [], origins: [] } })

const PASSWORD = 'Rahasia123'

/** Email unik per test supaya baris pendaftaran tertunda tidak bertabrakan. */
function createTestEmail(prefix: string): string {
    return `e2e-otp-${prefix}-${Date.now()}@example.test`
}

/**
 * Klik "Buat akun" lalu tunggu halaman OTP. Dev server sesekali "menelan" klik pertama
 * (halaman masih sibuk) tanpa mengirim POST, jadi klik diulang hanya saat tombol idle.
 */
async function submitRegisterForm(page: Page): Promise<void> {
    const button = page.getByRole('button', { name: /buat akun/i })

    for (let attempt = 0; attempt < 3; attempt += 1) {
        await button.click()

        try {
            await page.waitForURL(/\/auth\/verify-otp/, { timeout: 5_000 })
            return
        } catch {
            // Tombol berlabel "Membuat akun" berarti POST sedang jalan: cukup tunggu lebih lama.
            if (/membuat akun/i.test(await button.innerText())) break
        }
    }

    await expect(page).toHaveURL(/\/auth\/verify-otp/, { timeout: 25_000 })
}

/** Daftar akun baru lewat form register lalu tunggu halaman verifikasi OTP tampil. */
async function registerUntilOtpPage(page: Page, email: string): Promise<void> {
    await page.goto('/auth/register')
    await page.locator('#register-name').fill('E2E OTP')
    await page.locator('#register-email').fill(email)
    await page.locator('#register-password').fill(PASSWORD)
    await page.locator('#register-password-confirmation').fill(PASSWORD)

    // Cek ketersediaan email hanya jalan setelah Vue ter-hydrate: penanda form siap diklik.
    await expect(page.locator('[data-email-availability="available"]')).toBeVisible()

    await submitRegisterForm(page)
}

/** Enam kotak kode OTP, dicari lewat grup berlabel agar tidak ikut menangkap input lain. */
function otpBoxes(page: Page): Locator {
    return page.getByRole('group', { name: /kode verifikasi/i }).getByRole('textbox')
}

/** Respons halaman OTP versi JSON Inertia (bukan dokumen HTML) untuk navigasi GET. */
function isOtpPageJsonResponse(response: Response): boolean {
    return (
        response.request().method() === 'GET' &&
        response.url().includes('/auth/verify-otp') &&
        (response.headers()['content-type'] ?? '').includes('application/json')
    )
}

/** Respons submit kode (POST) apa pun hasilnya. */
function isVerifyPostResponse(response: Response): boolean {
    return response.request().method() === 'POST' && response.url().endsWith('/auth/verify-otp')
}

interface IInertiaSendQuotaPayload {
    props: { sendsRemaining: number }
}

/** Cek bentuk payload Inertia sebelum membaca props (JSON eksternal harus dipersempit dulu). */
function isInertiaSendQuotaPayload(value: unknown): value is IInertiaSendQuotaPayload {
    if (typeof value !== 'object' || value === null || !('props' in value)) return false

    const { props } = value

    return typeof props === 'object' && props !== null && 'sendsRemaining' in props && typeof props.sendsRemaining === 'number'
}

/** Sisa kuota kirim dari props respons Inertia: kuota dibuktikan tanpa baris metadata di UI. */
async function readSendsRemaining(response: Response): Promise<number> {
    const payload: unknown = await response.json()

    if (!isInertiaSendQuotaPayload(payload)) throw new Error('Props sendsRemaining tidak ada di respons halaman OTP')

    return payload.props.sendsRemaining
}

/** Pastikan respons verifikasi berupa JSON sukses (`200 { verified, redirect }`). */
async function expectVerifiedJson(response: Response): Promise<void> {
    const body: unknown = await response.json()

    if (typeof body !== 'object' || body === null || !('verified' in body) || !('redirect' in body)) {
        throw new Error('Respons verifikasi bukan JSON sukses')
    }

    if (typeof body.redirect !== 'string') throw new Error('Respons verifikasi tanpa tujuan redirect')

    expect(body.verified).toBe(true)
    expect(body.redirect).toContain('/dashboard')
}

/** Ubah label hitung mundur `mm:ss` menjadi detik. */
function toSeconds(label: string): number {
    const [minutes = '0', seconds = '0'] = label.trim().split(':')

    return Number(minutes) * 60 + Number(seconds)
}

/** Detik sisa pada sebuah hitung mundur, dibaca dari tampilan. */
async function readCountdownSeconds(locator: Locator): Promise<number> {
    return toSeconds((await locator.innerText()).trim())
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

/** Isi kotak OTP lewat event paste; clipboard OS tidak tersedia di headless tanpa izin. */
async function pasteOtp(page: Page, code: string): Promise<void> {
    await page.evaluate((value) => {
        const input = document.querySelector('input[aria-label="Digit 1 dari 6"]')

        if (input === null) throw new Error('Kotak OTP tidak ditemukan')

        const data = new DataTransfer()
        data.setData('text/plain', value)
        input.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true }))
    }, code)
}

/**
 * Ketik kode pada kotak pertama lalu tunggu server menolaknya (422) beserta props yang disegarkan.
 * Kotak harus dikosongkan sendiri oleh halaman setelah penolakan.
 */
async function submitWrongCode(page: Page, code: string): Promise<void> {
    const rejected = page.waitForResponse(isVerifyPostResponse)
    const refreshed = page.waitForResponse(isOtpPageJsonResponse)

    await otpBoxes(page).first().click()
    await page.keyboard.type(code)
    await rejected
    await refreshed

    // Server lambat di lingkungan dev; beri ruang lebih dari timeout assertion bawaan.
    await expect(otpBoxes(page).first()).toHaveValue('', { timeout: 20_000 })
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
    // Dev server kadang lambat; test yang menunggu cooldown/dashboard menaikkan timeout sendiri.
    test.describe.configure({ timeout: 60_000 })

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

    test('paste lima digit mengisi kotak berurutan tanpa auto-submit', async ({ page }) => {
        await registerUntilOtpPage(page, createTestEmail('paste'))

        await otpBoxes(page).first().click()
        await pasteOtp(page, '13579')

        for (const [index, digit] of ['1', '3', '5', '7', '9'].entries()) {
            await expect(otpBoxes(page).nth(index)).toHaveValue(digit)
        }

        // Kode belum lengkap: tidak ada kiriman ke server dan halaman tetap di tempat.
        await expect(otpBoxes(page).nth(5)).toHaveValue('')
        await expect(page).toHaveURL(/\/auth\/verify-otp/)
    })

    test('mengetik 6 digit: auto-submit, kotak hijau, lalu ke dashboard', async ({ page }) => {
        // Dashboard nyata dimuat setelah jeda hijau; beri ruang lebih dari timeout bawaan.
        test.setTimeout(60_000)

        const email = createTestEmail('auto-ketik')

        await registerUntilOtpPage(page, email)

        const code = readActiveOtp(email)
        const successBoxes = page.locator('[data-state="success"]')

        await otpBoxes(page).first().click()
        await page.keyboard.type(code.slice(0, 5))

        // Lima digit belum dikirim: belum ada kotak yang hijau.
        await expect(successBoxes).toHaveCount(0)

        const verified = page.waitForResponse(isVerifyPostResponse)
        await page.keyboard.type(code.slice(5))

        // Tanpa klik tombol: jalur JSON menerima kode, bukan redirect server-side.
        const response = await verified
        expect(response.status()).toBe(200)
        await expectVerifiedJson(response)

        // Hijau tampil lebih dulu (jeda ±600 ms), baru halaman pindah sendiri.
        await page.waitForFunction(() => document.querySelectorAll('[data-state="success"]').length === 6)
        await expect(page).toHaveURL(/\/dashboard/, { timeout: 30_000 })
    })

    test('paste 6 digit langsung submit tanpa klik Verifikasi', async ({ page }) => {
        test.setTimeout(60_000)

        const email = createTestEmail('auto-paste')

        await registerUntilOtpPage(page, email)

        // Navigasi tujuan diamati dari request-nya; render dashboard tidak ditunggu di test ini
        // (test "mengetik 6 digit" sudah membuktikan URL akhirnya).
        await page.route('**/dashboard*', (route) => route.abort())
        const navigated = page.waitForRequest((request) => request.url().includes('/dashboard'))

        const verified = page.waitForResponse(isVerifyPostResponse)

        await otpBoxes(page).first().click()
        await pasteOtp(page, readActiveOtp(email))

        const response = await verified
        expect(response.status()).toBe(200)
        await expectVerifiedJson(response)

        await page.waitForFunction(() => document.querySelectorAll('[data-state="success"]').length === 6)
        await navigated
    })

    test('kode salah: kotak merah, pesan error, dan kode dikosongkan', async ({ page }) => {
        const email = createTestEmail('salah')

        await registerUntilOtpPage(page, email)

        const wrongCode = toWrongCode(readActiveOtp(email))

        await otpBoxes(page).first().click()
        await page.keyboard.type(wrongCode)

        await expect(page.getByRole('alert')).toContainText(/kode verifikasi salah/i)
        await expect(page.locator('[data-state="invalid"]')).toHaveCount(6)
        await expect(otpBoxes(page).first()).toHaveValue('')
        await expect(page).toHaveURL(/\/auth\/verify-otp/)
    })

    test('tiga percobaan salah: kode kedaluwarsa dan hitung mundur 00:00', async ({ page }) => {
        const email = createTestEmail('habis')

        await registerUntilOtpPage(page, email)

        const wrongCode = toWrongCode(readActiveOtp(email))

        for (let attempt = 1; attempt <= 3; attempt += 1) await submitWrongCode(page, wrongCode)

        // Server mengosongkan kode setelah percobaan ketiga; hitung mundur langsung nol.
        await expect(page.getByTestId('otp-countdown')).toHaveText('00:00')
        await expect(page.getByTestId('otp-expired')).toHaveText(/kode sudah kedaluwarsa\. kirim ulang otp\./i)
        await expect(otpBoxes(page).first()).toBeDisabled()
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
        await expect(page.getByTestId('otp-countdown')).toHaveText('00:00')
        await expect(page.getByTestId('otp-expiry-live')).toHaveText(/kedaluwarsa/i)
        await expect(page.getByRole('button', { name: /^kirim ulang otp$/i })).toBeFocused()
        await expect(page.getByRole('button', { name: /^verifikasi$/i })).toBeDisabled()
    })

    test('kirim ulang: cooldown habis → tombol aktif, kuota turun, hitung mundur kembali 5 menit', async ({ page }) => {
        // Cooldown kirim ulang mengikuti config server (60 detik); waktu tunggu dibaca dari UI, bukan hardcode.
        test.setTimeout(120_000)

        const resendButton = page.getByRole('button', { name: /^kirim ulang otp$/i })
        const resendCountdown = page.getByTestId('otp-resend-countdown')
        const codeCountdown = page.getByTestId('otp-countdown')

        const initialOtpPage = page.waitForResponse(isOtpPageJsonResponse)
        await registerUntilOtpPage(page, createTestEmail('resend'))
        const sendsBefore = await readSendsRemaining(await initialOtpPage)

        // Selama cooldown: tombol nonaktif, sisa detik tampil, dan angkanya berjalan.
        await expect(resendButton).toBeDisabled()
        await expect(resendCountdown).toHaveText(/^\d{2}:\d{2}$/)

        const cooldownStart = (await resendCountdown.innerText()).trim()
        await expect(resendCountdown).not.toHaveText(cooldownStart, { timeout: 4_000 })

        // Cooldown habis: tombol aktif kembali dan sisa detik hilang dari baris kirim ulang.
        await waitForResendAvailable(page, resendButton)
        await expect(resendButton).toBeEnabled()
        await expect(resendCountdown).toHaveCount(0)

        const [resendPage] = await Promise.all([page.waitForResponse(isOtpPageJsonResponse), resendButton.click()])

        // Kuota server turun satu setelah kirim ulang — dibuktikan dari props Inertia, bukan baris UI.
        expect(await readSendsRemaining(resendPage)).toBe(sendsBefore - 1)

        // Props baru dipakai tanpa remount: kode kembali 5 menit dan kirim ulang terkunci 60 detik.
        // Batas atas memberi ruang untuk pembulatan detik (`ceil`) dan latensi request.
        await expect(codeCountdown).toHaveText(/^\d{2}:\d{2}$/)
        const codeSeconds = await readCountdownSeconds(codeCountdown)
        expect(codeSeconds).toBeGreaterThan(280)
        expect(codeSeconds).toBeLessThan(310)

        await expect(resendButton).toBeDisabled()
        const cooldownSeconds = await readCountdownSeconds(resendCountdown)
        expect(cooldownSeconds).toBeGreaterThan(50)
        expect(cooldownSeconds).toBeLessThan(65)

        // Interval benar-benar hidup lagi: angkanya turun tanpa reload.
        const secondsBeforeTick = await readCountdownSeconds(codeCountdown)
        await expect
            .poll(async () => readCountdownSeconds(codeCountdown), { timeout: 5_000 })
            .toBeLessThan(secondsBeforeTick)
    })

    test('ganti akun kembali ke form pendaftaran', async ({ page }) => {
        await registerUntilOtpPage(page, createTestEmail('ganti'))

        await page.getByRole('button', { name: /ganti akun/i }).click()

        await expect(page).toHaveURL(/\/auth\/register/)
        await expect(page.getByRole('heading', { name: /buat akun/i })).toBeVisible()
    })
})
