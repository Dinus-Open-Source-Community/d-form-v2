import { execFileSync } from 'node:child_process';

/**
 * Jalankan artisan untuk menyiapkan state test. Default lewat podman dari host;
 * set E2E_ARTISAN=php bila suite dijalankan di dalam container aplikasi.
 */
export function runArtisan(code: string): void {
    if (process.env.E2E_ARTISAN === 'php') {
        execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: process.cwd() });

        return;
    }

    execFileSync('podman', ['exec', '-i', 'd_form_app', 'php', 'artisan', 'tinker', '--execute', code]);
}
