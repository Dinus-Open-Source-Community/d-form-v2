import { spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { artisanCommand } from './artisan';
import { hasLiveHeartbeat, listHeartbeatRunIds } from './heartbeat';

const MAX_BUFFER_BYTES = 8 * 1024 * 1024;

/**
 * Sapu run yang ditinggal (tanpa heartbeat hidup, baris terbaru > grace) di bawah flock eksklusif.
 * Liveness dihitung di host (pid Playwright valid di sini) lalu diteruskan ke command artisan.
 * Kegagalan sweep hanya dicatat sebagai warning agar tidak menggagalkan suite yang sedang mulai.
 */
export function sweepAbandonedRuns(graceHours: number = 24, budgetSeconds: number = 120): void {
    const lockPath = resolve(process.cwd(), 'storage/app/e2e/sweep.lock');
    mkdirSync(dirname(lockPath), { recursive: true });

    const [executable, ...prefix] = artisanCommand();

    if (executable === undefined) {
        console.warn('[e2e] abandoned-run sweep skipped: E2E_ARTISAN has no executable.');

        return;
    }

    const liveRuns = listHeartbeatRunIds().filter((runId: string): boolean => hasLiveHeartbeat(runId));

    const result = spawnSync(
        'flock',
        [
            '-n',
            lockPath,
            executable,
            ...prefix,
            'e2e:sweep',
            `--grace-hours=${graceHours}`,
            `--budget=${budgetSeconds}`,
            `--live=${liveRuns.join(',')}`,
            '--json',
        ],
        { encoding: 'utf8', maxBuffer: MAX_BUFFER_BYTES },
    );

    if (result.error !== undefined || result.status !== 0) {
        const reason = result.error?.message ?? result.stderr.trim() ?? `exit ${String(result.status)}`;
        console.warn(`[e2e] abandoned-run sweep skipped (${reason}).`);

        return;
    }

    console.log(`[e2e] abandoned-run sweep: ${result.stdout.trim()}`);
}
