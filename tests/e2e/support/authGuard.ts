import { readFileSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';

/** Lokasi storageState admin, relatif terhadap root proyek Playwright. */
export const ADMIN_STATE_PATH = 'tests/e2e/.auth/admin.json';

// Laravel menamai cookie sesi `Str::slug(APP_NAME).'-session'`; APP_NAME proyek ini "DForm".
// Terverifikasi lewat `php artisan tinker --execute="echo config('session.cookie');"`.
export const DEFAULT_SESSION_COOKIE = 'dform-session';
const SETUP_HINT = 'Run "npm run test:e2e:setup" (project "setup") before authenticated specs.';

/** Satu cookie pada file storageState Playwright. */
export interface IStorageStateCookie {
    name: string;
    value: string;
    domain?: string;
    path?: string;
}

/** Bentuk minimum storageState yang wajib ada agar spec terautentikasi aman berjalan. */
export interface IAdminStorageState {
    cookies: IStorageStateCookie[];
    origins: unknown[];
}

/** Nama cookie sesi aplikasi; `E2E_SESSION_COOKIE` menimpa default bila APP_NAME berubah. */
export function sessionCookieName(env: NodeJS.ProcessEnv = process.env): string {
    return (env.E2E_SESSION_COOKIE ?? '').trim() || DEFAULT_SESSION_COOKIE;
}

/** Resolusi path state terhadap cwd proyek; path absolut dibiarkan apa adanya. */
export function resolveStatePath(statePath: string = ADMIN_STATE_PATH): string {
    return isAbsolute(statePath) ? statePath : resolve(process.cwd(), statePath);
}

/**
 * Baca dan validasi file storageState admin.
 * Melempar error (bukan skip) bila file hilang, rusak, atau tanpa cookie sesi.
 */
export function assertAdminState(statePath: string = ADMIN_STATE_PATH): IAdminStorageState {
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
        throw new Error(
            `Admin storage state at "${resolved}" has no "${sessionCookieName()}" cookie. ${SETUP_HINT}`,
        );
    }

    return parsed;
}

/**
 * Apakah state memuat cookie sesi Laravel yang membuat request dianggap terautentikasi.
 * `cookieName` diambil dari `sessionCookieName()` supaya spec tidak menebak nama cookie.
 */
export function hasSessionCookie(
    state: IAdminStorageState,
    cookieName: string = sessionCookieName(),
): boolean {
    return state.cookies.some((cookie) => cookie.name === cookieName && cookie.value !== '');
}

/** Guard bentuk minimum; mencegah `as` pada data eksternal dan memberi pesan yang jelas. */
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
