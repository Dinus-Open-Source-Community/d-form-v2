import { defineConfig, devices } from '@playwright/test'

// E2E berjalan terhadap server yang sudah hidup (tidak menyalakan server sendiri).
// - dari host: E2E_BASE_URL=http://127.0.0.1:3000 (default)
// - dari container: E2E_BASE_URL=http://127.0.0.1:8000
const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:3000'

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        ...devices['Desktop Chrome'],
    },
})
