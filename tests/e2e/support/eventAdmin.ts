import { expect, test, type APIRequestContext, type BrowserContext, type Page } from '@playwright/test';
import { runArtisanCommand, runArtisanJson } from './artisan';
import { assertBrowserContextAuthenticatedAs, loginE2eUser } from './authentication';
import { readEnvironment } from './environment';
import { readXsrfToken } from './network';

/** Keberadaan event (termasuk trashed) dan jumlah form-nya di database. */
export interface IEventExistenceState {
    eventExists: boolean;
    /** Baris event masih soft-deleted. */
    trashed: boolean;
    /** Form milik event, dihitung dengan `withTrashed()` sehingga tetap tampak setelah diarsipkan. */
    formsTotal: number;
}

/** Hasil satu request mutasi tanpa mengikuti redirect. */
export interface IMutationOutcome {
    status: number;
    location: string;
}

const ACTIVE_EVENTS_PATH = '/admin/events';
const LOGIN_PATHNAME = '/auth/login';
const SESSION_RECOVERY_FLAG = 'E2E_ALLOW_SESSION_RECOVERY';

/** Bungkus nilai sebagai string literal PHP single-quote yang aman. */
function phpStringLiteral(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

/**
 * Pindahkan `events.created_by` fixture ke akun admin pemilik storageState.
 *
 * `EventPolicy` hanya mengizinkan pemilik event atau super-admin; akun E2E bisa
 * ber-role `admin`, jadi tanpa langkah ini arsip/restore/purge akan 403.
 */
export function claimEventForAdmin(eventId: string): void {
    const adminEmail = (process.env.E2E_ADMIN_EMAIL ?? '').trim();

    if (adminEmail === '') {
        throw new Error('E2E_ADMIN_EMAIL must be set; the fixture event owner is reassigned to that account.');
    }

    runArtisanCommand([
        'tinker',
        '--execute',
        `$user=\\App\\Models\\User::where('email', ${phpStringLiteral(adminEmail)})->firstOrFail(); ` +
            `\\App\\Models\\Event::whereKey(${phpStringLiteral(eventId)})->update(['created_by'=>$user->id]); ` +
            `echo json_encode(['adminId'=>$user->id]);`,
    ]);
}

/** Baca status baris event (termasuk trashed) dan jumlah form-nya lewat tinker. */
export function readEventState(eventId: string): IEventExistenceState {
    const code = [
        `$eventId=${phpStringLiteral(eventId)};`,
        `$event=\\App\\Models\\Event::withTrashed()->find($eventId);`,
        'echo json_encode([',
        `'eventExists'=>($event!==null),`,
        `'trashed'=>($event!==null && $event->trashed()),`,
        `'formsTotal'=>\\App\\Models\\Form::withTrashed()->where('event_id',$eventId)->count(),`,
        ']);',
    ].join(' ');

    return runArtisanJson<IEventExistenceState>(['tinker', '--execute', code]);
}

/**
 * Kirim mutasi seperti klien Inertia: cookie sesi dari context, header XSRF dari cookie,
 * dan redirect tidak diikuti agar status 3xx asli bisa diperiksa.
 */
export async function sendMutation(
    request: APIRequestContext,
    context: BrowserContext,
    method: 'POST' | 'DELETE',
    path: string
): Promise<IMutationOutcome> {
    const response = await request.fetch(path, {
        method,
        headers: {
            'X-XSRF-TOKEN': await readXsrfToken(context),
            Accept: 'text/html',
        },
        maxRedirects: 0,
        failOnStatusCode: false,
    });

    return { status: response.status(), location: response.headers()['location'] ?? '' };
}

/** Arsipkan event lewat endpoint yang sama dengan aksi kartu di daftar aktif. */
export async function archiveEvent(context: BrowserContext, eventId: string): Promise<void> {
    const outcome = await sendMutation(context.request, context, 'DELETE', `${ACTIVE_EVENTS_PATH}/${eventId}`);

    expect(outcome.status, 'archive must redirect').toBe(302);
    expect(outcome.location, 'archive returns to the active list').toMatch(/\/admin\/events$/);
}

/** Ganti seluruh cookie context dengan sesi hasil login API untuk aktor tertentu. */
async function attachSession(page: Page, email: string, password: string): Promise<void> {
    const state = await loginE2eUser(email, password);

    await page.context().addCookies(state.cookies);
    await assertBrowserContextAuthenticatedAs(page.context(), email);
}

/**
 * Login sebagai admin ber-role `admin` (bukan super-admin) pada context yang sedang
 * dipakai. Kredensial hanya dibaca dari environment; tidak ada fallback.
 */
export async function signInAsNonSuperAdmin(page: Page): Promise<void> {
    const environment = readEnvironment();

    if (environment.nonAdminEmail === '' || environment.nonAdminPassword === '') {
        throw new Error(
            'E2E_NON_ADMIN_EMAIL and E2E_NON_ADMIN_PASSWORD are required to act as the seeded (role `admin`) user.'
        );
    }

    await attachSession(page, environment.nonAdminEmail, environment.nonAdminPassword);
}

function isLoginUrl(url: string): boolean {
    return new URL(url).pathname === LOGIN_PATHNAME;
}

/** Recovery hanya boleh saat operator menyatakan sesi bersama memang bermasalah. */
function sessionRecoveryAllowed(): boolean {
    return (process.env[SESSION_RECOVERY_FLAG] ?? '').trim() === '1';
}

/**
 * Buka path sebagai admin pemilik storageState.
 *
 * Navigasi yang ter-redirect ke `/auth/login` **gagal keras** secara default: sesi
 * basi adalah regresi autentikasi, bukan sesuatu yang boleh ditutupi helper. Hanya
 * bila `E2E_ALLOW_SESSION_RECOVERY=1`, satu kali login ulang dilakukan dan dicatat
 * sebagai anotasi `session-recovery` agar tetap terlihat di laporan.
 */
export async function openAuthenticatedPath(page: Page, path: string): Promise<void> {
    await page.goto(path, { waitUntil: 'domcontentloaded' });

    if (!isLoginUrl(page.url())) {
        return;
    }

    if (!sessionRecoveryAllowed()) {
        throw new Error(
            `Navigation to "${path}" was redirected to ${LOGIN_PATHNAME}: the stored E2E session is no longer valid. ` +
                `Fix the session source instead of masking it, or set ${SESSION_RECOVERY_FLAG}=1 to allow one explicit recovery.`
        );
    }

    const environment = readEnvironment();

    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error(`E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD are required to recover the session for "${path}".`);
    }

    await attachSession(page, environment.adminEmail, environment.adminPassword);
    test.info().annotations.push({
        type: 'session-recovery',
        description: `Navigation to "${path}" bounced to ${LOGIN_PATHNAME}; re-authenticated as the stored admin.`,
    });

    await page.goto(path, { waitUntil: 'domcontentloaded' });

    if (isLoginUrl(page.url())) {
        throw new Error(`Session recovery could not reopen "${path}"; the page is still at ${LOGIN_PATHNAME}.`);
    }
}
