import { defineConfig, devices } from '@playwright/test';
import { adminStatePath } from './tests/e2e/support/authGuard';
import { readEnvironment } from './tests/e2e/support/environment';
import { createRunId } from './tests/e2e/support/marker';

// E2E reads its server and credentials from environment variables only.
const environment = readEnvironment();

// Share one run ID with all workers.
const runId = createRunId();
process.env.E2E_RUN_ID = runId;

// Setup and specs resolve the same per-run storage state.
const ADMIN_STATE = adminStatePath({ runId });

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    // Legacy specs flush shared cache state, so parallel workers corrupt rate-limit assertions.
    workers: 1,
    forbidOnly: true,
    retries: 0,
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
});
