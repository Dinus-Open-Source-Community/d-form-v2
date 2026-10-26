import { expect, type Page } from '@playwright/test'
import { runArtisan } from './artisan'

/** Kredensial penyelenggara (super-admin) pada dataset stress. */
export const STRESS_ORGANIZER = {
    email: 'stress-user-1@stress.test',
    password: 'password',
} as const

/** Kosongkan cache limiter agar login e2e tidak memotong kuota 5/menit milik spec lain. */
export function resetLoginThrottle(): void {
    runArtisan('\\Illuminate\\Support\\Facades\\Cache::flush();')
}

/** Login lewat cookie sesi + header XSRF; gagal bila kredensial ditolak, bukan hanya cek 302. */
export async function loginAsOrganizer(page: Page): Promise<void> {
    await page.goto('/auth/login')

    const cookies = await page.context().cookies()
    const xsrf = cookies.find((entry) => entry.name === 'XSRF-TOKEN')

    const response = await page.context().request.post('/auth/login', {
        form: { email: STRESS_ORGANIZER.email, password: STRESS_ORGANIZER.password },
        headers: {
            'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf.value) : '',
            Accept: 'text/html',
        },
        maxRedirects: 0,
        failOnStatusCode: false,
    })

    expect(response.status(), 'login ditolak (cek kredensial / throttle)').toBe(302)
    expect(response.headers()['location'], 'login tidak mengarah ke dashboard').toContain('/dashboard')
}
