import { spawnSync } from 'node:child_process';

/** Explicit E2E environment values; credentials have no fallback. */
export interface IEnvironment {
    baseUrl: string;
    artisan: string;
    adminEmail: string;
    adminPassword: string;
    /** Admin seeded dengan role `admin`; wajib hanya untuk proof 403 non-super-admin. */
    nonAdminEmail: string;
    nonAdminPassword: string;
}

export interface IEnvironmentOptions {
    env?: NodeJS.ProcessEnv;
    probeBaseUrl?: boolean;
    baseUrlTimeoutMs?: number;
    fetchImpl?: typeof fetch;
    /** Spec 403 membutuhkan akun `admin`; suite lain cukup kredensial admin saja. */
    requireNonAdmin?: boolean;
}

export class E2eEnvironmentError extends Error {
    constructor(
        readonly variable: string,
        message: string
    ) {
        super(`${variable}: ${message}`);
        this.name = 'E2eEnvironmentError';
    }
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Allows local PHP/Octane cold starts to pass the reachability probe. */
export const DEFAULT_BASE_URL_TIMEOUT_MS = 5000;

const PROBE_ATTEMPTS = 2;
const HEAD_METHOD_NOT_ALLOWED = 405;

export function readEnvironment(env: NodeJS.ProcessEnv = process.env): IEnvironment {
    return {
        baseUrl: (env.E2E_BASE_URL ?? '').trim(),
        artisan: (env.E2E_ARTISAN ?? '').trim(),
        adminEmail: (env.E2E_ADMIN_EMAIL ?? '').trim(),
        adminPassword: env.E2E_ADMIN_PASSWORD ?? '',
        nonAdminEmail: (env.E2E_NON_ADMIN_EMAIL ?? '').trim(),
        nonAdminPassword: env.E2E_NON_ADMIN_PASSWORD ?? '',
    };
}

/** Split a command while preserving simple quoted paths. */
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
 * Urutan: `E2E_ARTISAN` → `E2E_BASE_URL` → `E2E_ADMIN_EMAIL` → `E2E_ADMIN_PASSWORD`.
 * Kredensial `E2E_NON_ADMIN_*` hanya wajib saat `requireNonAdmin` (spec 403), tetapi
 * nilai yang diisi tetap divalidasi formatnya agar typo tidak lolos diam-diam.
 */
export async function assertEnvironment(options: IEnvironmentOptions = {}): Promise<IEnvironment> {
    const environment = readEnvironment(options.env);

    assertArtisanCommand(environment.artisan);
    await assertBaseUrl(environment.baseUrl, options);
    assertAdminEmail(environment.adminEmail, environment.adminPassword);
    assertAdminPassword(environment.adminPassword);
    assertNonAdminCredentials(
        environment.nonAdminEmail,
        environment.nonAdminPassword,
        options.requireNonAdmin === true
    );

    return environment;
}

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
            'must be an explicit command whose executable is available on PATH.'
        );
    }
}

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

function assertAdminPassword(password: string): void {
    if (password === '') {
        throw new E2eEnvironmentError('E2E_ADMIN_PASSWORD', 'must be set to the seeded admin password.');
    }
}

/**
 * Kredensial non-super-admin opsional untuk suite yang hanya memakai akun admin.
 * Saat diwajibkan (spec 403) keduanya harus terisi; saat tidak, email yang diisi
 * tetap harus berformat valid.
 */
function assertNonAdminCredentials(email: string, password: string, required: boolean): void {
    if (required) {
        assertNonAdminEmail(email, password);
        assertNonAdminPassword(password);

        return;
    }

    if (email !== '' && !EMAIL_PATTERN.test(email)) {
        throw new E2eEnvironmentError('E2E_NON_ADMIN_EMAIL', `"${email}" is not a valid email address.`);
    }
}

/**
 * Validasi kredensial non-super-admin yang wajib diberikan eksplisit untuk spec 403.
 */
function assertNonAdminEmail(email: string, password: string): void {
    if (email === '' && password === '') {
        throw new E2eEnvironmentError(
            'E2E_NON_ADMIN_EMAIL and E2E_NON_ADMIN_PASSWORD',
            'must both be set to the seeded non-super-admin credentials.'
        );
    }

    if (!EMAIL_PATTERN.test(email)) {
        throw new E2eEnvironmentError('E2E_NON_ADMIN_EMAIL', `"${email}" is not a valid email address.`);
    }
}

function assertNonAdminPassword(password: string): void {
    if (password === '') {
        throw new E2eEnvironmentError('E2E_NON_ADMIN_PASSWORD', 'must be set to the seeded non-super-admin password.');
    }
}
