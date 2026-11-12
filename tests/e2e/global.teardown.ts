import { runArtisanCommand } from './support/artisan';
import { hasLiveHeartbeat, removeRunHeartbeatDirectory } from './support/heartbeat';
import { runMarkerPrefix, runSlugPrefix } from './support/marker';

/**
 * Hapus seluruh baris milik run ini lalu folder heartbeat-nya.
 * Dilewati dengan warning bila masih ada worker yang hidup, sehingga run paralel tidak saling hapus.
 */
export default async function globalTeardown(): Promise<void> {
    const runId = process.env.E2E_RUN_ID;

    if (runId === undefined || runId === '') {
        return;
    }

    if (hasLiveHeartbeat(runId)) {
        console.warn(`[e2e] global teardown skipped for run ${runId}: a live worker heartbeat still exists.`);

        return;
    }

    runArtisanCommand([
        'e2e:cleanup',
        `--marker-prefix=${runMarkerPrefix(runId)}`,
        `--slug-prefix=${runSlugPrefix(runId)}`,
        '--json',
    ]);
    removeRunHeartbeatDirectory(runId);
}
