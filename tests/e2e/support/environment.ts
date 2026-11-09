import { spawnSync } from 'node:child_process';

/** Nilai environment yang dipakai seluruh harness e2e, sudah diberi default aman. */
export interface IEnvironment {
    baseUrl: string;
    artisan: string;
    adminEmail: string;
    adminPassword: string;
}

/** Opsi validasi; `probeBaseUrl: false` dipakai unit test agar tidak menyentuh jaringan. */
export interface IEnvironmentOptions {
    env?: NodeJS.ProcessEnv;
    probeBaseUrl?: boolean;
    baseUrlTimeoutMs?: number;
    fetchImpl?: typeof fetch;
}

/** Kegagalan environment yang selalu menyebut nama variabel penyebabnya. */
export class E2eEnvironmentError extends Error {
    constructor(
        readonly variable: string,
        message: string
    ) {
        super(`${variable}: ${message}`);
        this.name = 'E2eEnvironmentError';
    }
}

const DEFAULT_BASE_URL = 'http://localhost:8000';
const DEFAULT_ARTISAN = 'podman exec -i -w /app d_form_app php artisan';
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Timeout probe default > 2000 ms agar cold start `php -S`/Octane tidak dianggap mati. */
export const DEFAULT_BASE_URL_TIMEOUT_MS = 5000;

const PROBE_ATTEMPTS = 2;
const HEAD_METHOD_NOT_ALLOWED = 405;

/** Baca konfigurasi environment tanpa validasi; aman dipanggil saat modul config dievaluasi. */
export function readEnvironment(env: NodeJS.ProcessEnv = process.env): IEnvironment {
    return {
        baseUrl: (env.E2E_BASE_URL ?? '').trim() || DEFAULT_BASE_URL,
        artisan: (env.E2E_ARTISAN ?? '').trim() || DEFAULT_ARTISAN,
        adminEmail: (env.E2E_ADMIN_EMAIL ?? '').trim(),
        adminPassword: env.E2E_ADMIN_PASSWORD ?? '',
    };
}

/** Pecah command menjadi token; mendukung kutip tunggal/ganda sederhana untuk path berspasi. */
export function splitCommandLine(command: string): string[] {
    const tokens: string[] = [];
    let current = '';
    let quote: '"' | "'" | null = null;

    for (const character of command) {
        if (quote !== null) {
            if (character === quote) {
                quote = null;
            } else {
                current += character;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;

            continue;
        }

        if (/\s/.test(character)) {
            if (current !== '') {
                tokens.push(current);
                current = '';
            }

            continue;
        }

        current += character;
    }

    if (current !== '') {
        tokens.push(current);
    }

    return tokens;
}

/**
 * Validasi environment dengan urutan tetap lalu hentikan suite pada kegagalan pertama.
 * Urutan: `E2E_BASE_URL` → `E2E_ARTISAN` → `E2E_ADMIN_EMAIL` → `E2E_ADMIN_PASSWORD`.
 */
export async function assertEnvironment(options: IEnvironmentOptions = {}): Promise<IEnvironment> {
    const environment = readEnvironment(options.env);

    await assertBaseUrl(environment.baseUrl, options);
    assertArtisanCommand(environment.artisan);
    assertAdminEmail(environment.adminEmail, environment.adminPassword);
    assertAdminPassword(environment.adminPassword);

    return environment;
}

/** Pastikan base URL absolut http(s) dan hostnya menjawab HEAD dalam batas waktu. */
async function assertBaseUrl(baseUrl: string, options: IEnvironmentOptions): Promise<void> {
    if (baseUrl.trim() === '') {
        throw new E2eEnvironmentError('E2E_BASE_URL', 'must be set to an absolute http(s) URL.');
    }

    let parsed: URL;

    try {
        parsed = new URL(baseUrl);
    } catch {
        throw new E2eEnvironmentError('E2E_BASE_URL', `"${baseUrl}" is not an absolute URL.`);
    }

    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        throw new E2eEnvironmentError('E2E_BASE_URL', `"${baseUrl}" must use http or https.`);
    }

    if (options.probeBaseUrl === false) {
        return;
    }

    const timeoutMs = resolveBaseUrlTimeout(options);
    const fetchImpl = options.fetchImpl ?? fetch;

    await probeBaseUrl(parsed, timeoutMs, fetchImpl);
}

/** Baca `E2E_BASE_URL_TIMEOUT_MS`; nilai tidak valid ditolak agar salah ketik tidak menutupi masalah. */
function resolveBaseUrlTimeout(options: IEnvironmentOptions): number {
    if (options.baseUrlTimeoutMs !== undefined) {
        return options.baseUrlTimeoutMs;
    }

    const env = options.env ?? process.env;
    const raw = (env.E2E_BASE_URL_TIMEOUT_MS ?? '').trim();

    if (raw === '') {
        return DEFAULT_BASE_URL_TIMEOUT_MS;
    }

    const parsed = Number(raw);

    if (!Number.isFinite(parsed) || parsed <= 0) {
        throw new E2eEnvironmentError('E2E_BASE_URL_TIMEOUT_MS', `"${raw}" must be a positive number of milliseconds.`);
    }

    return parsed;
}

/**
 * Probe reachability: HEAD dulu, GET sebagai fallback saat HEAD timeout/405, ulang sekali.
 * Status apa pun yang dijawab server dianggap hidup; hanya kegagalan transport yang menggagalkan probe.
 */
async function probeBaseUrl(baseUrl: URL, timeoutMs: number, fetchImpl: typeof fetch): Promise<void> {
    let lastReason = 'no response';

    for (let attempt = 0; attempt < PROBE_ATTEMPTS; attempt += 1) {
        try {
            const head = await fetchImpl(baseUrl, {
                method: 'HEAD',
                redirect: 'manual',
                signal: AbortSignal.timeout(timeoutMs),
            });

            if (head.status !== HEAD_METHOD_NOT_ALLOWED) {
                return;
            }
        } catch (error) {
            lastReason = error instanceof Error ? error.message : String(error);
        }

        try {
            await fetchImpl(baseUrl, {
                method: 'GET',
                redirect: 'manual',
                signal: AbortSignal.timeout(timeoutMs),
            });

            return;
        } catch (error) {
            lastReason = error instanceof Error ? error.message : String(error);
        }
    }

    throw new E2eEnvironmentError(
        'E2E_BASE_URL',
        `host ${baseUrl.origin} did not answer within ${timeoutMs}ms after ${PROBE_ATTEMPTS} attempts (${lastReason}).`
    );
}

/** Pastikan token pertama `E2E_ARTISAN` benar-benar executable di PATH. */
function assertArtisanCommand(artisan: string): void {
    const tokens = splitCommandLine(artisan);

    if (tokens.length === 0) {
        throw new E2eEnvironmentError('E2E_ARTISAN', 'must contain an executable command.');
    }

    const executable = tokens[0] ?? '';
    const probe = spawnSync('sh', ['-c', 'command -v "$1" >/dev/null 2>&1', 'sh', executable], {
        stdio: 'ignore',
    });

    if (probe.status !== 0) {
        throw new E2eEnvironmentError(
            'E2E_ARTISAN',
            `first token "${executable}" is not an executable found on PATH (resolved command: "${artisan}").`
        );
    }
}

/** Pastikan kredensial admin tersedia; dua-duanya kosong disebut bersama agar setup tidak menebak. */
function assertAdminEmail(email: string, password: string): void {
    if (email === '' && password === '') {
        throw new E2eEnvironmentError(
            'E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD',
            'must both be set to the seeded admin credentials.'
        );
    }

    if (email === '') {
        throw new E2eEnvironmentError('E2E_ADMIN_EMAIL', 'must be set to the seeded admin email address.');
    }

    if (!EMAIL_PATTERN.test(email)) {
        throw new E2eEnvironmentError('E2E_ADMIN_EMAIL', `"${email}" is not a valid email address.`);
    }
}

/** Pastikan password admin tidak kosong; nilainya sendiri tidak pernah dicetak. */
function assertAdminPassword(password: string): void {
    if (password === '') {
        throw new E2eEnvironmentError('E2E_ADMIN_PASSWORD', 'must be set to the seeded admin password.');
    }
}
