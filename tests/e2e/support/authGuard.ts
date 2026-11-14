import { existsSync, readFileSync, readdirSync, rmSync, statSync } from 'node:fs';
import { isAbsolute, join, resolve } from 'node:path';

export interface IAdminStateOptions {
    env?: NodeJS.ProcessEnv;
    runId?: string;
}

const AUTH_DIRECTORY = 'tests/e2e/.auth';
const STALE_STATE_MAX_AGE_MS = 24 * 60 * 60 * 1000;
const STATE_FILE_PATTERN = /^admin-(\d{8}T\d{6}-[0-9a-f]{6})\.json$/;

/**
 * Path storageState admin untuk satu run.
 *
 * Default diturunkan dari `E2E_RUN_ID` agar dua run tidak saling menimpa;
 * `E2E_ADMIN_STATE` tetap menjadi override eksplisit (mis. untuk server terpisah).
 */
export function adminStatePath(options: IAdminStateOptions = {}): string {
    const env = options.env ?? process.env;
    const override = (env.E2E_ADMIN_STATE ?? '').trim();

    if (override !== '') {
        return override;
    }

    const runId = (options.runId ?? env.E2E_RUN_ID ?? '').trim();

    if (runId === '') {
        throw new Error('E2E_RUN_ID must be set before resolving the admin storageState path.');
    }

    return `${AUTH_DIRECTORY}/admin-${runId}.json`;
}

/**
 * Hapus file state run lain yang lebih tua dari 24 jam (sisa run yang crash).
 * File run yang sedang berjalan selalu dipertahankan.
 */
export function pruneStaleAdminStates(currentRunId: string, now: number = Date.now()): string[] {
    const directory = resolve(process.cwd(), AUTH_DIRECTORY);

    if (!existsSync(directory)) {
        return [];
    }

    const removed: string[] = [];

    for (const name of readdirSync(directory)) {
        const match = STATE_FILE_PATTERN.exec(name);

        if (match === null || match[1] === currentRunId) {
            continue;
        }

        const filePath = join(directory, name);

        if (now - statSync(filePath).mtimeMs <= STALE_STATE_MAX_AGE_MS) {
            continue;
        }

        rmSync(filePath, { force: true });
        removed.push(name);
    }

    return removed;
}

// Laravel menamai cookie sesi `Str::slug(APP_NAME).'-session'`; APP_NAME proyek ini "DForm".
// Terverifikasi lewat `php artisan tinker --execute="echo config('session.cookie');"`.
export const DEFAULT_SESSION_COOKIE = 'dform-session';
const SETUP_HINT = 'Run "npm run test:e2e:setup" (project "setup") before authenticated specs.';

export interface IStorageStateCookie {
    name: string;
    value: string;
    domain?: string;
    path?: string;
}

export interface IAdminStorageState {
    cookies: IStorageStateCookie[];
    origins: unknown[];
}

export function sessionCookieName(env: NodeJS.ProcessEnv = process.env): string {
    return (env.E2E_SESSION_COOKIE ?? '').trim() || DEFAULT_SESSION_COOKIE;
}

export function resolveStatePath(statePath: string = adminStatePath()): string {
    return isAbsolute(statePath) ? statePath : resolve(process.cwd(), statePath);
}

/**
 * Baca dan validasi file storageState admin.
 * Melempar error (bukan skip) bila file hilang, rusak, atau tanpa cookie sesi.
 */
export function assertAdminState(statePath: string = adminStatePath()): IAdminStorageState {
    const resolved = resolveStatePath(statePath);
    let raw: string;

    try {
        raw = readFileSync(resolved, 'utf8');
    } catch {
        throw new Error(`Admin storage state is missing at "${resolved}". ${SETUP_HINT}`);
    }

    let parsed: unknown;

    try {
        parsed = JSON.parse(raw);
    } catch {
        throw new Error(`Admin storage state at "${resolved}" is not valid JSON. ${SETUP_HINT}`);
    }

    if (!isAdminStorageState(parsed)) {
        throw new Error(`Admin storage state at "${resolved}" has an unexpected shape. ${SETUP_HINT}`);
    }

    if (!hasSessionCookie(parsed)) {
        throw new Error(`Admin storage state at "${resolved}" has no "${sessionCookieName()}" cookie. ${SETUP_HINT}`);
    }

    return parsed;
}

/**
 * Apakah state memuat cookie sesi Laravel yang membuat request dianggap terautentikasi.
 * `cookieName` diambil dari `sessionCookieName()` supaya spec tidak menebak nama cookie.
 */
export function hasSessionCookie(state: IAdminStorageState, cookieName: string = sessionCookieName()): boolean {
    return state.cookies.some((cookie) => cookie.name === cookieName && cookie.value !== '');
}

/** Validate untrusted storage-state JSON before narrowing it. */
function isAdminStorageState(value: unknown): value is IAdminStorageState {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as { cookies?: unknown; origins?: unknown };

    if (!Array.isArray(candidate.cookies) || !Array.isArray(candidate.origins)) {
        return false;
    }

    return candidate.cookies.every((cookie: unknown): boolean => {
        if (typeof cookie !== 'object' || cookie === null) {
            return false;
        }

        const entry = cookie as { name?: unknown; value?: unknown };

        return typeof entry.name === 'string' && typeof entry.value === 'string';
    });
}
