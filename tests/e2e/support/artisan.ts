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

/**
 * Jalankan satu command artisan (`php artisan <args>`) lalu kembalikan stdout tanpa newline akhir.
 * Dipakai test untuk membaca state nyata server (mis. `otp:current`) tanpa tinker.
 */
export function runArtisanCommand(args: string[]): string {
    const output =
        process.env.E2E_ARTISAN === 'php'
            ? execFileSync('php', ['artisan', ...args], { cwd: process.cwd(), encoding: 'utf8' })
            : execFileSync('podman', ['exec', '-i', '-w', '/app', 'd_form_app', 'php', 'artisan', ...args], {
                  encoding: 'utf8',
              });

    return output.trim();
}
