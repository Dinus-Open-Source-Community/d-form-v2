import { mkdir } from 'node:fs/promises';
import { assertEnvironment } from './support/environment';
import { heartbeatDirectory } from './support/heartbeat';
import { sweepAbandonedRuns } from './support/sweep';

/**
 * Validasi environment, siapkan folder heartbeat run, lalu sapu run yang ditinggal.
 * Dijalankan sekali sebelum project `setup` dan project browser mana pun.
 */
export default async function globalSetup(): Promise<void> {
    await assertEnvironment();

    const runId = process.env.E2E_RUN_ID;

    if (runId === undefined || runId === '') {
        throw new Error('E2E_RUN_ID is not set; playwright.config.ts must generate it once per invocation.');
    }

    await mkdir(heartbeatDirectory(runId), { recursive: true });
    sweepAbandonedRuns();
}
