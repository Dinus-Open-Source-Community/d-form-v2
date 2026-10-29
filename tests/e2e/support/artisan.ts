import { execFileSync } from 'node:child_process';
import { readEnvironment, splitCommandLine } from './environment';

/**
 * Rangkai pesan kegagalan artisan. `execFileSync` hanya menaruh stderr di properti error,
 * sehingga tanpa ini setiap kegagalan muncul sebagai "Command failed" tanpa sebabnya.
 */
function artisanFailure(args: string[], error: unknown): Error {
    const base = error instanceof Error ? error.message : String(error);
    // Node menempelkan stderr/stdout sebagai properti; tipe `unknown` di catch tidak membawanya.
    const details = error as { stderr?: unknown; stdout?: unknown };
    const stderr = typeof details.stderr === 'string' ? details.stderr.trim() : '';
    const stdout = typeof details.stdout === 'string' ? details.stdout.trim() : '';
    const output = [stderr, stdout].filter((line: string): boolean => line !== '').join('\n');

    return new Error(`artisan ${args.join(' ')} gagal: ${base}${output === '' ? '' : `\n${output}`}`, {
        cause: error,
    });
}

/** Token perintah artisan dari `E2E_ARTISAN` (default podman exec ke container aplikasi). */
export function artisanCommand(env: NodeJS.ProcessEnv = process.env): string[] {
    return splitCommandLine(readEnvironment(env).artisan);
}

/**
 * Jalankan satu command artisan dan kembalikan stdout tanpa newline akhir.
 * Semua helper artisan memakai jalur ini agar kredensial dan container tetap dari environment.
 */
export function runArtisanCommand(args: string[], input?: string): string {
    const [executable, ...prefix] = artisanCommand();

    if (executable === undefined) {
        throw new Error('E2E_ARTISAN is empty; cannot run artisan.');
    }

    try {
        return execFileSync(executable, [...prefix, ...args], {
            encoding: 'utf8',
            input,
            maxBuffer: 8 * 1024 * 1024,
        }).trim();
    } catch (error: unknown) {
        throw artisanFailure(args, error);
    }
}

/** Jalankan command artisan lalu parse stdout sebagai JSON. */
export function runArtisanJson<TResult>(args: string[], input?: string): TResult {
    return JSON.parse(runArtisanCommand(args, input)) as TResult;
}

/** Jalankan command artisan dengan payload stdin (dipakai `e2e:fixture`). */
export function runArtisanWithInput(args: string[], input: string): string {
    return runArtisanCommand(args, input);
}

/** Jalankan kode tinker; dipertahankan untuk skrip lama yang membutuhkan state langsung. */
export function runArtisan(code: string): void {
    runArtisanCommand(['tinker', '--execute', code]);
}
