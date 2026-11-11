import type { APIRequestContext, BrowserContext, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand, runArtisanJson } from './support/artisan';
import { readEnvironment } from './support/environment';
import { readXsrfToken } from './support/network';

/** Kolom turunan event beserta cascade form/form_field yang diperiksa lewat DB. */
interface IEventCascadeState {
    eventDeleted: boolean;
    formsTotal: number;
    formsDeleted: number;
    fieldsTotal: number;
    fieldsDeleted: number;
}

/** Hasil satu request mutasi tanpa mengikuti redirect. */
interface IMutationOutcome {
    status: number;
    location: string;
}

const ARCHIVE_EVENT_LABEL = 'Archive event';
const RESTORE_EVENT_LABEL = 'Restore event';

/** Bungkus nilai sebagai string literal PHP single-quote yang aman. */
function phpStringLiteral(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

/**
 * Pindahkan `events.created_by` fixture ke akun admin yang dipakai spec.
 * Fixture membuat event atas nama user fixture, sedangkan `EventPolicy` hanya mengizinkan
 * pemilik event (atau akun super-admin) untuk view/delete/restore. Akun E2E ber-role `admin`
 * bukan super-admin, jadi tanpa langkah ini halaman show akan 403.
 */
function claimEventForAdmin(eventId: string): void {
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

/** Baca seluruh baris cascade event (termasuk trashed) sebagai JSON lewat tinker. */
function readEventCascadeState(eventId: string): IEventCascadeState {
    const code = [
        `$eventId=${phpStringLiteral(eventId)};`,
        `$formIds=\\App\\Models\\Form::withTrashed()->where('event_id',$eventId)->pluck('id');`,
        `$event=\\App\\Models\\Event::withTrashed()->find($eventId);`,
        'echo json_encode([',
        `'eventDeleted'=>($event!==null && $event->trashed()),`,
        `'formsTotal'=>$formIds->count(),`,
        `'formsDeleted'=>\\App\\Models\\Form::withTrashed()->whereIn('id',$formIds)->whereNotNull('deleted_at')->count(),`,
        `'fieldsTotal'=>\\App\\Models\\FormField::withTrashed()->whereIn('form_id',$formIds)->count(),`,
        `'fieldsDeleted'=>\\App\\Models\\FormField::withTrashed()->whereIn('form_id',$formIds)` +
            `->whereNotNull('deleted_at')->count(),`,
        ']);',
    ].join(' ');

    return runArtisanJson<IEventCascadeState>(['tinker', '--execute', code]);
}

/**
 * Kirim mutasi seperti klien Inertia biasa: cookie sesi dari context, header XSRF dari cookie,
 * dan redirect tidak diikuti agar status 3xx asli bisa diperiksa.
 */
async function sendMutation(
    request: APIRequestContext,
    context: BrowserContext,
    method: 'POST' | 'DELETE',
    path: string,
    accept = 'text/html'
): Promise<IMutationOutcome> {
    const response = await request.fetch(path, {
        method,
        headers: {
            'X-XSRF-TOKEN': await readXsrfToken(context),
            Accept: accept,
        },
        maxRedirects: 0,
        failOnStatusCode: false,
    });

    return { status: response.status(), location: response.headers()['location'] ?? '' };
}

/**
 * Login ulang sebagai admin memakai kredensial environment yang sama dengan
 * `auth.setup.ts` (tidak pernah di-hardcode). Dipakai hanya bila shared session
 * store sudah di-flush spec lain sehingga storageState menjadi basi.
 */
async function signInAsAdmin(context: BrowserContext): Promise<void> {
    const environment = readEnvironment();
    const loginPath = '/auth/login';

    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD are required to refresh the admin session.');
    }

    await context.request.get(loginPath, { failOnStatusCode: false });

    const response = await context.request.post(loginPath, {
        form: { email: environment.adminEmail, password: environment.adminPassword },
        headers: { 'X-XSRF-TOKEN': await readXsrfToken(context), Accept: 'text/html' },
        maxRedirects: 0,
        failOnStatusCode: false,
    });
    const location = response.headers()['location'] ?? '';

    expect(location, 'admin relogin must not bounce back to the login page').not.toContain('/auth/login');
}

/**
 * Buka halaman show event; juga menyegarkan cookie XSRF sebelum request API.
 * Session disimpan di Redis bersama: bila spec lain mem-flush-nya, storageState
 * menjadi basi dan halaman show ter-redirect ke login, jadi login ulang sekali.
 */
async function openEventShow(page: Page, eventId: string): Promise<void> {
    const showPath = `/admin/events/${eventId}`;

    await page.goto(showPath, { waitUntil: 'domcontentloaded' });

    if (new URL(page.url()).pathname !== '/auth/login') {
        return;
    }

    await signInAsAdmin(page.context());
    await page.goto(showPath, { waitUntil: 'domcontentloaded' });
}

/** Konfirmasi dialog AlertDialog yang sedang terbuka lewat klik mouse nyata. */
async function confirmDialog(page: Page, confirmLabel: string): Promise<void> {
    const dialog = page.getByRole('alertdialog');

    await expect(dialog).toBeVisible();
    await page.waitForTimeout(300);
    await dialog.getByRole('button', { name: confirmLabel, exact: true }).click();
}

test.describe('BE-P2-004 cascade archive/restore event', () => {
    // Event happy-path memakai 3 form x 4 field = 12 form_field.
    test.use({ fixtureFormCount: 3, fixtureFieldCount: 4 });

    test('archive lalu restore meng-cascade forms dan form_fields', async ({ page, fixtureEvent, fixtureForm }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await openEventShow(page, fixtureEvent.eventId);

        // 1. Happy archive: ketiga form tampil di kartu Forms sebelum arsip.
        for (const index of [1, 2, 3]) {
            await expect(page.getByText(`Form ${index} [${fixtureEvent.marker}]`, { exact: true })).toBeVisible();
        }
        expect(fixtureForm.forms).toHaveLength(3);

        await page.getByRole('button', { name: ARCHIVE_EVENT_LABEL }).click();
        await confirmDialog(page, 'Archive');
        await expect(page).toHaveURL(/\/admin\/events$/);

        const archived = readEventCascadeState(fixtureEvent.eventId);
        expect(archived.eventDeleted).toBe(true);
        expect(archived.formsTotal).toBe(3);
        expect(archived.formsDeleted).toBe(3);
        expect(archived.fieldsTotal).toBe(12);
        expect(archived.fieldsDeleted).toBe(12);

        // 2. Happy restore: route binding `dashboard.events.*` tetap menemukan event trashed.
        await openEventShow(page, fixtureEvent.eventId);
        await expect(page.getByRole('button', { name: RESTORE_EVENT_LABEL })).toBeVisible();
        await page.getByRole('button', { name: RESTORE_EVENT_LABEL }).click();
        await confirmDialog(page, 'Restore');
        await expect(page).toHaveURL(new RegExp(`/admin/events/${fixtureEvent.eventId}$`));

        const restored = readEventCascadeState(fixtureEvent.eventId);
        expect(restored.eventDeleted).toBe(false);
        expect(restored.formsDeleted).toBe(0);
        expect(restored.fieldsDeleted).toBe(0);
        expect(restored.formsTotal).toBe(3);
        expect(restored.fieldsTotal).toBe(12);
    });

    test('restore pada event aktif tetap redirect dan tidak mengubah state', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await openEventShow(page, fixtureEvent.eventId);

        const restoreActive = await sendMutation(
            page.context().request,
            page.context(),
            'POST',
            `/admin/events/${fixtureEvent.eventId}/restore`
        );
        expect(restoreActive.status).toBe(302);
        expect(restoreActive.location).toContain(`/admin/events/${fixtureEvent.eventId}`);

        const state = readEventCascadeState(fixtureEvent.eventId);
        expect(state.eventDeleted).toBe(false);
        expect(state.formsDeleted).toBe(0);
        expect(state.fieldsDeleted).toBe(0);
        expect(state.formsTotal).toBe(fixtureForm.forms.length);
    });

    test('id tidak ada dan id malformed ditolak 404 tanpa 5xx', async ({ page, fixtureEvent }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await openEventShow(page, fixtureEvent.eventId);

        const missingUuid = '11111111-1111-1111-1111-111111111111';
        const request = page.context().request;
        const context = page.context();

        const restoreMissing = await sendMutation(request, context, 'POST', `/admin/events/${missingUuid}/restore`);
        expect(restoreMissing.status).toBe(404);

        const destroyMissing = await sendMutation(request, context, 'DELETE', `/admin/events/${missingUuid}`);
        expect(destroyMissing.status).toBe(404);

        const restoreMalformed = await sendMutation(request, context, 'POST', '/admin/events/not-a-uuid/restore');
        expect(restoreMalformed.status).toBe(404);

        const destroyMalformed = await sendMutation(request, context, 'DELETE', '/admin/events/not-a-uuid');
        expect(destroyMalformed.status).toBe(404);

        // JSON request juga 404, bukan halaman error 5xx.
        const restoreMissingJson = await sendMutation(
            request,
            context,
            'POST',
            `/admin/events/${missingUuid}/restore`,
            'application/json'
        );
        expect(restoreMissingJson.status).toBe(404);
    });

    test('guest tidak bisa restore event terarsip dan DB tetap terarsip', async ({
        page,
        browser,
        fixtureEvent,
        fixtureForm,
    }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await openEventShow(page, fixtureEvent.eventId);

        const archived = await sendMutation(
            page.context().request,
            page.context(),
            'DELETE',
            `/admin/events/${fixtureEvent.eventId}`
        );
        expect(archived.status).toBe(302);
        expect(archived.location).toMatch(/\/admin\/events$/);
        expect(readEventCascadeState(fixtureEvent.eventId).eventDeleted).toBe(true);

        const guestContext = await browser.newContext({ baseURL: readEnvironment().baseUrl });

        try {
            // `browser.newContext()` dapat mewarisi storageState project; kosongkan cookie
            // agar permintaan benar-benar anonim sebelum meminta token XSRF baru.
            await guestContext.clearCookies();
            // GET login membuat sesi guest sekaligus cookie XSRF yang valid.
            await guestContext.request.get('/auth/login', { failOnStatusCode: false });
            const restorePath = `/admin/events/${fixtureEvent.eventId}/restore`;

            const guestHtml = await sendMutation(guestContext.request, guestContext, 'POST', restorePath);
            expect(guestHtml.status).toBe(302);
            expect(guestHtml.location).toContain('/auth/login');

            const guestJson = await sendMutation(
                guestContext.request,
                guestContext,
                'POST',
                restorePath,
                'application/json'
            );
            expect(guestJson.status).toBe(401);
        } finally {
            await guestContext.close();
        }

        const state = readEventCascadeState(fixtureEvent.eventId);
        expect(state.eventDeleted).toBe(true);
        expect(state.formsDeleted).toBe(fixtureForm.forms.length);
    });

    test('arsip ganda tetap soft-deleted tanpa 5xx', async ({ page, fixtureEvent, fixtureForm }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await openEventShow(page, fixtureEvent.eventId);

        const firstArchive = await sendMutation(
            page.context().request,
            page.context(),
            'DELETE',
            `/admin/events/${fixtureEvent.eventId}`
        );
        expect(firstArchive.status).toBe(302);

        const secondArchive = await sendMutation(
            page.context().request,
            page.context(),
            'DELETE',
            `/admin/events/${fixtureEvent.eventId}`
        );
        expect(secondArchive.status).toBeLessThan(500);

        const state = readEventCascadeState(fixtureEvent.eventId);
        expect(state.eventDeleted).toBe(true);
        expect(state.formsDeleted).toBe(fixtureForm.forms.length);
    });

    test('event tanpa form tetap bisa diarsipkan dan dipulihkan', async ({ page, fixtureEvent }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await openEventShow(page, fixtureEvent.eventId);
        await expect(page.getByText('Belum ada form untuk event ini.')).toBeVisible();

        await page.getByRole('button', { name: ARCHIVE_EVENT_LABEL }).click();
        await confirmDialog(page, 'Archive');
        await expect(page).toHaveURL(/\/admin\/events$/);

        const archived = readEventCascadeState(fixtureEvent.eventId);
        expect(archived.eventDeleted).toBe(true);
        expect(archived.formsTotal).toBe(0);
        expect(archived.fieldsTotal).toBe(0);

        await openEventShow(page, fixtureEvent.eventId);
        await page.getByRole('button', { name: RESTORE_EVENT_LABEL }).click();
        await confirmDialog(page, 'Restore');
        await expect(page).toHaveURL(new RegExp(`/admin/events/${fixtureEvent.eventId}$`));

        const restored = readEventCascadeState(fixtureEvent.eventId);
        expect(restored.eventDeleted).toBe(false);
        expect(restored.formsTotal).toBe(0);
        expect(restored.fieldsTotal).toBe(0);
    });
});
