import { defineConfig, devices } from '@playwright/test'
import { readEnvironment } from './tests/e2e/support/environment'
import { createRunId } from './tests/e2e/support/marker'

// E2E berjalan terhadap server yang sudah hidup. Base URL, perintah artisan, dan
// kredensial admin hanya dibaca dari environment; spec tidak pernah menyimpan kredensial.
const environment = readEnvironment()

// Satu runId per invokasi, diturunkan ke seluruh worker lewat process.env.
const runId = createRunId()
process.env.E2E_RUN_ID = runId

const ADMIN_STATE = 'tests/e2e/.auth/admin.json'

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    // Empat spec lama mem-flush cache global di beforeEach (auth/email/otp/event-search), jadi
    // dua file yang jalan bersamaan saling merusak hitungan rate limit dan assert 429 gagal.
    // fullyParallel tetap true; batasnya ada di jumlah worker sampai spec lama berhenti flush global.
    workers: 1,
    forbidOnly: true,
    retries: 1,
    reporter: [['list']],
    globalSetup: './tests/e2e/global.setup.ts',
    globalTeardown: './tests/e2e/global.teardown.ts',
    use: {
        baseURL: environment.baseUrl,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts$/,
            fullyParallel: false,
            workers: 1,
            retries: 0,
            use: { storageState: undefined },
        },
        {
            name: 'chromium-desktop',
            dependencies: ['setup'],
            testIgnore: /.*\.mobile\.spec\.ts$/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
                storageState: ADMIN_STATE,
            },
        },
        {
            name: 'chromium-mobile',
            dependencies: ['setup'],
            testIgnore: /.*\.desktop\.spec\.ts$/,
            use: {
                ...devices['Pixel 7'],
                storageState: ADMIN_STATE,
            },
        },
    ],
})
