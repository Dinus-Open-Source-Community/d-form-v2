import { readFileSync } from 'node:fs';
import type { Browser, BrowserContext, Download, Page } from '@playwright/test';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    defaultFixtureRequest,
    expect,
    test,
} from './support/fixtures';
import { createOwnerIdentity, createRunId } from './support/marker';
import { readEnvironment } from './support/environment';
import { runArtisanCommand } from './support/artisan';
import {
    assertBrowserContextAuthenticatedAs,
    loginE2eUser,
    type TPlaywrightStorageState,
} from './support/authentication';

/**
 * E2E BE-P2-009: export CSV pendaftaran event (`dashboard.events.exports.registrations-csv`).
 *
 * Fixture: 1 event milik admin E2E (`events.created_by` = `E2E_ADMIN_EMAIL`) + 1 form
 * + 300 form_answers dengan `created_at` IDENTIK, sehingga urutan hanya bisa stabil
 * bila export memakai keyset `(created_at DESC, id DESC)` dan semua baris muncul
 * tepat sekali meskipun 300 melewati satu batas page 250.
 *
 * Assertion urutan memakai urutan `ORDER BY created_at DESC, id DESC` yang dihitung
 * DB sendiri (probe), bukan perbandingan string JavaScript, agar aturan collation
 * kolom `id` tidak diduplikasi di spec.
 *
 * Parser CSV dan helper unduhan identik dengan spec referensi
 * `recruitment-interview-export.desktop.spec.ts`; disalin karena tiket melarang
 * mengubah file lain (termasuk menambah modul support bersama).
 */

const IDENTICAL_CREATED_AT = '2026-05-10 09:00:00';
const ANSWER_COUNT = 300;

/** Urutan kolom kontrak `EventRegistrationsCsvExportController` (13 kolom). */
const CSV_HEADER: readonly string[] = [
    'submission_id',
    'form_id',
    'form_title',
    'user_name',
    'user_email',
    'registration_code',
    'review_status',
    'group_token',
    'registration_role',
    'member_confirmation_status',
    'leader_email',
    'submitted_at',
    'answers_summary',
];

const SUBMISSION_ID_INDEX = 0;
const FORM_ID_INDEX = 1;
const FORM_TITLE_INDEX = 2;
const USER_NAME_INDEX = 3;
const USER_EMAIL_INDEX = 4;
const REGISTRATION_CODE_INDEX = 5;
const REVIEW_STATUS_INDEX = 6;
const GROUP_TOKEN_INDEX = 7;
const REGISTRATION_ROLE_INDEX = 8;
const MEMBER_CONFIRMATION_INDEX = 9;
const LEADER_EMAIL_INDEX = 10;
const SUBMITTED_AT_INDEX = 11;
const ANSWERS_SUMMARY_INDEX = 12;

/** Aktor anomali otorisasi; password sama dengan admin E2E dari environment. */
const NON_OWNER_ADMIN_EMAIL = 'e2e-be-p2-009-admin@example.test';
const MEMBER_EMAIL = 'e2e-be-p2-009-member@example.test';

/** UUID valid yang tidak pernah ada di DB, dan id yang sama sekali bukan UUID. */
const MISSING_EVENT_ID = '00000000-0000-7000-8000-000000000000';
const NON_UUID_EVENT_ID = 'bukan-uuid-be-p2-009';

/** Marker kanonik 40 karakter milik run ini; dipakai fixture dan cleanup. */
const identity = createOwnerIdentity(createRunId(), {
    project: 'chromium-desktop',
    specSlug: 'event-registrations-export',
    testSlug: 'fixtures',
    retry: 0,
});

interface ICsvDownload {
    filename: string;
    raw: string;
    rows: string[][];
}

interface IProbeResult {
    rows: number;
    event_id: string;
    form_id: string;
    form_title: string;
    submitted_at: string;
    ordered_ids: string[];
    page_size: number;
    non_owner_admin_roles: string;
    member_roles: string;
    event_owner_is_null: boolean;
    non_owner_admin_owns_event: boolean;
    non_owner_admin_is_super_admin: boolean;
    member_is_super_admin: boolean;
    member_can_list_events: boolean;
    non_owner_admin_policy_allows_view: boolean;
}

/** Hasil probe fixture; satu sumber kebenaran untuk seluruh assertion. */
let fixture: IProbeResult | null = null;

function fixtureOrThrow(): IProbeResult {
    if (fixture === null) {
        throw new Error('Fixture BE-P2-009 belum terbentuk; beforeAll gagal.');
    }

    return fixture;
}

function laporanPath(eventId: string): string {
    return `/admin/events/${eventId}/laporan`;
}

function exportPath(eventId: string): string {
    return `/admin/events/${eventId}/exports/registrations.csv`;
}

/** Parser CSV RFC 4180 minimum: cukup untuk keluaran `fputcsv` tanpa nested newline. */
function parseCsvRows(content: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let quoted = false;

    for (let index = 0; index < content.length; index += 1) {
        const character = content.charAt(index);

        if (quoted) {
            if (character !== '"') {
                cell += character;
                continue;
            }

            if (content.charAt(index + 1) === '"') {
                cell += '"';
                index += 1;
                continue;
            }

            quoted = false;
            continue;
        }

        if (character === '"') {
            quoted = true;
            continue;
        }

        if (character === ',') {
            row.push(cell);
            cell = '';
            continue;
        }

        if (character === '\n') {
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
            continue;
        }

        if (character !== '\r') {
            cell += character;
        }
    }

    if (cell !== '' || row.length > 0) {
        row.push(cell);
        rows.push(row);
    }

    return rows;
}

const UTF8_BOM = '\uFEFF';

function stripBom(content: string): string {
    return content.startsWith(UTF8_BOM) ? content.slice(1) : content;
}

/** Baca berkas unduhan; BOM tetap ada di `raw` agar bisa di-assert. */
async function collectDownload(download: Download): Promise<ICsvDownload> {
    const filePath = await download.path();

    if (filePath === null) {
        throw new Error('Unduhan CSV tidak memiliki path sementara.');
    }

    const raw = readFileSync(filePath, 'utf8');

    return { filename: download.suggestedFilename(), raw, rows: parseCsvRows(stripBom(raw)) };
}

/**
 * Buat 300 answer ber-`created_at` identik pada form fixture, lalu buat dua user aktor
 * anomali (role `admin` non-pemilik dan role `member`). Idempoten: answer form dihapus
 * lebih dulu agar retry tidak menggandakan baris.
 */
function createAnswersAndAnomalyUsers(formId: string): void {
    const env = readEnvironment();
    // Password tidak pernah ditulis ke repo: diambil dari environment sebagai base64.
    const passwordBase64 = Buffer.from(env.adminPassword, 'utf8').toString('base64');

    const script = String.raw`
$formId = '${formId}';
Illuminate\Support\Facades\DB::table('form_answers')->where('form_id', $formId)->delete();

$rows = [];
for ($index = 1; $index <= ${ANSWER_COUNT}; $index++) {
    $rows[] = [
        'id' => (string) Illuminate\Support\Str::orderedUuid(),
        'answers' => '{}',
        'user_id' => null,
        'form_id' => $formId,
        'review_status' => 'pending',
        'is_active' => 1,
        'created_at' => '${IDENTICAL_CREATED_AT}',
        'updated_at' => '${IDENTICAL_CREATED_AT}',
    ];
}
foreach (array_chunk($rows, 100) as $chunk) {
    Illuminate\Support\Facades\DB::table('form_answers')->insert($chunk);
}

$password = Illuminate\Support\Facades\Hash::make(base64_decode('${passwordBase64}'));
$roles = [
    '${NON_OWNER_ADMIN_EMAIL}' => Spatie\Permission\Models\Role::findOrCreate('admin', 'web'),
    '${MEMBER_EMAIL}' => Spatie\Permission\Models\Role::findOrCreate('member', 'web'),
];
foreach ($roles as $email => $role) {
    $user = App\Models\User::withTrashed()->where('email', $email)->first();
    if ($user !== null) {
        $user->restore();
    }
    if ($user === null) {
        $user = App\Models\User::query()->create([
            'name' => 'E2E BE-P2-009 '.$role->name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }
    $user->syncRoles([$role]);
}

echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Tinker tidak memberi exit code non-zero saat exception; hasil tulis diverifikasi ulang di sini. */
function probeFixture(formId: string): IProbeResult {
    const script = String.raw`
$form = App\Models\Form::query()->where('id', '${formId}')->first();
if ($form === null) {
    echo json_encode(['rows' => 0]);
    exit(0);
}
$ids = Illuminate\Support\Facades\DB::table('form_answers')
    ->where('form_id', $form->id)
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->pluck('id')
    ->all();
$sample = App\Models\FormAnswer::query()->where('form_id', $form->id)->first();
$nonOwnerAdmin = App\Models\User::withTrashed()->where('email', '${NON_OWNER_ADMIN_EMAIL}')->first();
$member = App\Models\User::withTrashed()->where('email', '${MEMBER_EMAIL}')->first();
$eventOwnerId = Illuminate\Support\Facades\DB::table('events')->where('id', $form->event_id)->value('created_by');
$event = App\Models\Event::query()->find($form->event_id);
echo json_encode([
    'rows' => count($ids),
    'event_id' => $form->event_id,
    'form_id' => $form->id,
    'form_title' => $form->title,
    // Ekspresi sama dengan controller: satu-satunya cara memastikan format waktu identik.
    'submitted_at' => $sample === null
        ? ''
        : $sample->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
    'ordered_ids' => array_values($ids),
    'page_size' => (int) config('registration.event_export_page_size'),
    'non_owner_admin_roles' => $nonOwnerAdmin?->getRoleNames()->implode(',') ?? '',
    'member_roles' => $member?->getRoleNames()->implode(',') ?? '',
    'event_owner_is_null' => $eventOwnerId === null,
    'non_owner_admin_owns_event' => $eventOwnerId === $nonOwnerAdmin?->id,
    'non_owner_admin_is_super_admin' => $nonOwnerAdmin?->hasRole('super-admin') ?? false,
    'member_is_super_admin' => $member?->hasRole('super-admin') ?? false,
    'member_can_list_events' => $member?->can('events.list') ?? false,
    'non_owner_admin_policy_allows_view' => $nonOwnerAdmin !== null && $event !== null
        ? Illuminate\Support\Facades\Gate::forUser($nonOwnerAdmin)->allows('view', $event)
        : false,
]);
`;

    const output = runArtisanCommand(['tinker', '--execute', script]);
    const probe = JSON.parse(output) as Partial<IProbeResult>;

    if (
        probe.rows !== ANSWER_COUNT ||
        probe.submitted_at === '' ||
        probe.submitted_at === undefined ||
        probe.ordered_ids === undefined ||
        probe.ordered_ids.length !== ANSWER_COUNT ||
        probe.page_size === undefined ||
        probe.page_size <= 0 ||
        probe.event_id === undefined ||
        probe.form_id === undefined ||
        probe.form_title === undefined ||
        probe.event_owner_is_null === undefined ||
        probe.non_owner_admin_owns_event === undefined ||
        probe.non_owner_admin_is_super_admin === undefined ||
        probe.member_is_super_admin === undefined ||
        probe.member_can_list_events === undefined ||
        probe.non_owner_admin_policy_allows_view === undefined
    ) {
        throw new Error(`Fixture BE-P2-009 tidak sesuai rencana: ${output.slice(0, 500)}`);
    }

    if (probe.non_owner_admin_roles !== 'admin' || probe.member_roles !== 'member') {
        throw new Error(`User anomali fixture tidak memegang role yang diharapkan: ${output.slice(0, 500)}`);
    }

    if (
        probe.event_owner_is_null ||
        probe.non_owner_admin_owns_event ||
        probe.non_owner_admin_is_super_admin ||
        probe.member_is_super_admin ||
        probe.member_can_list_events ||
        probe.non_owner_admin_policy_allows_view
    ) {
        throw new Error(
            'Fixture authorization harus memakai pemilik event berbeda dan aktor non-super-admin yang tidak memiliki events.list.'
        );
    }

    return {
        rows: probe.rows,
        event_id: probe.event_id,
        form_id: probe.form_id,
        form_title: probe.form_title,
        submitted_at: probe.submitted_at,
        ordered_ids: probe.ordered_ids,
        page_size: probe.page_size,
        non_owner_admin_roles: probe.non_owner_admin_roles,
        member_roles: probe.member_roles,
        event_owner_is_null: probe.event_owner_is_null,
        non_owner_admin_owns_event: probe.non_owner_admin_owns_event,
        non_owner_admin_is_super_admin: probe.non_owner_admin_is_super_admin,
        member_is_super_admin: probe.member_is_super_admin,
        member_can_list_events: probe.member_can_list_events,
        non_owner_admin_policy_allows_view: probe.non_owner_admin_policy_allows_view,
    };
}

/** Hapus dua user aktor anomali beserta role-nya; idempoten. */
function removeAnomalyUsers(): void {
    const script = String.raw`
foreach (['${NON_OWNER_ADMIN_EMAIL}', '${MEMBER_EMAIL}'] as $email) {
    $user = App\Models\User::withTrashed()->where('email', $email)->first();
    if ($user !== null) {
        $user->syncRoles([]);
        $user->forceDelete();
    }
}
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Hasil login sekali di `beforeAll`, dipakai ulang semua test agar tidak kena throttle `auth-login`. */
let adminState: TPlaywrightStorageState | null = null;

/** Context baru dengan sesi admin hasil `beforeAll`; test tidak perlu login ulang. */
async function openAdminPage(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
    if (adminState === null) {
        throw new Error('Sesi admin E2E belum dibuat di beforeAll.');
    }

    const context = await browser.newContext({ baseURL: readEnvironment().baseUrl, storageState: adminState });
    const page = await context.newPage();
    // Skrip analitik pihak ketiga menggantungkan event load dan tidak relevan bagi test.
    await page.route('**/seeyu.doscom.org/**', (route) => route.abort());

    return { context, page };
}

/**
 * Login lewat HTTP API (pola `auth.setup.ts`): GET `/auth/login` untuk cookie XSRF,
 * lalu POST kredensial tanpa mengikuti redirect. Form Inertia tidak dipakai karena
 * submit-nya bergantung pada round-trip yang bisa menggantung pada server tunggal ini.
 * `intended` tidak dibutuhkan: response redirect tidak diikuti (`maxRedirects: 0`).
 */
async function downloadRegistrationsCsv(page: Page, eventId: string): Promise<ICsvDownload> {
    const response = await page.goto(laporanPath(eventId), { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    // Tombol export kini memakai `downloadWithProgress` (bukan anchor), jadi unduhan
    // tetap berupa event download browser dengan nama file dari `Content-Disposition`.
    const button = page.getByRole('button', { name: 'Registrations CSV' }).first();
    await expect(button).toBeVisible({ timeout: 30_000 });

    const downloadPromise = page.waitForEvent('download');
    await button.click();

    return collectDownload(await downloadPromise);
}

test.describe('BE-P2-009 export CSV pendaftaran event', () => {
    test.beforeAll(async (): Promise<void> => {
        // Fixture butuh 300 baris + beberapa panggilan artisan; beri ruang lebih dari timeout default.
        test.setTimeout(180_000);

        // Idempoten: sisa run sebelumnya (gagal di tengah) dibersihkan lebih dulu.
        cleanupOwnerFixture(identity);
        removeAnomalyUsers();

        const env = readEnvironment();
        const owner = createOwnerFixture(identity, env.adminEmail);
        const forms = createFormsFixture(owner, {
            ...defaultFixtureRequest(identity, 1, 1, 0),
            ownerEmail: env.adminEmail,
        });
        const form = forms[0];

        if (form === undefined) {
            throw new Error('e2e:fixture tidak mengembalikan form untuk BE-P2-009.');
        }

        createAnswersAndAnomalyUsers(form.formId);
        fixture = probeFixture(form.formId);

        // Satu login admin untuk seluruh test: `auth-login` dibatasi 5 percobaan/menit per email+IP.
        adminState = await loginE2eUser(env.adminEmail, env.adminPassword);
    });

    test.afterAll((): void => {
        test.setTimeout(120_000);
        cleanupOwnerFixture(identity);
        removeAnomalyUsers();
    });

    test('happy path: BOM, 13 kolom, 300 baris unik lintas batas page 250, urut (submitted_at DESC, submission_id DESC)', async ({
        browser,
    }): Promise<void> => {
        test.setTimeout(300_000);

        const probe = fixtureOrThrow();
        const { context, page } = await openAdminPage(browser);

        try {
            const csv = await downloadRegistrationsCsv(page, probe.event_id);

            expect(csv.filename).toBe(`registrations-event-${probe.event_id}.csv`);
            // BOM UTF-8 wajib ada agar Excel membaca karakter Indonesia dengan benar.
            expect(csv.raw.charCodeAt(0)).toBe(0xfeff);

            expect(csv.rows[0]).toEqual([...CSV_HEADER]);
            expect(csv.rows[0]).toHaveLength(13);

            const dataRows = csv.rows.slice(1);
            expect(dataRows).toHaveLength(ANSWER_COUNT);
            // 300 baris harus benar-benar melewati satu batas page agar tie teruji;
            // page size efektif dibaca dari config, bukan disalin sebagai konstanta.
            expect(dataRows.length, '300 answer harus melewati satu batas page export').toBeGreaterThan(
                probe.page_size
            );

            for (const row of dataRows) {
                expect(row).toHaveLength(13);
            }

            const submissionIds = dataRows.map((row: string[]): string => row[SUBMISSION_ID_INDEX] ?? '');
            expect(new Set<string>(submissionIds).size, 'submission_id wajib unik').toBe(ANSWER_COUNT);
            // Ordering dari DB sendiri: ORDER BY created_at DESC, id DESC (kontrak keyset).
            expect(submissionIds).toEqual(probe.ordered_ids);

            expect(dataRows.map((row: string[]): string => row[SUBMITTED_AT_INDEX] ?? '')).toEqual(
                new Array<string>(ANSWER_COUNT).fill(probe.submitted_at)
            );

            // Baris guest fixture: kolom user/leader/kode/group kosong, summary jawaban kosong.
            for (const row of dataRows) {
                expect(row[SUBMISSION_ID_INDEX] ?? '').not.toBe('');
                expect(row[FORM_ID_INDEX]).toBe(probe.form_id);
                expect(row[FORM_TITLE_INDEX]).toBe(probe.form_title);
                expect(row[USER_NAME_INDEX]).toBe('');
                expect(row[USER_EMAIL_INDEX]).toBe('');
                expect(row[REGISTRATION_CODE_INDEX]).toBe('');
                expect(row[REVIEW_STATUS_INDEX]).toBe('pending');
                expect(row[GROUP_TOKEN_INDEX]).toBe('');
                expect(row[REGISTRATION_ROLE_INDEX]).toBe('');
                expect(row[MEMBER_CONFIRMATION_INDEX]).toBe('');
                expect(row[LEADER_EMAIL_INDEX]).toBe('');
                expect(row[ANSWERS_SUMMARY_INDEX]).toBe('');
            }
        } finally {
            await context.close();
        }
    });

    test('user role admin tetapi bukan created_by event mendapat 403 tanpa unduhan', async ({
        browser,
    }): Promise<void> => {
        test.setTimeout(120_000);

        const probe = fixtureOrThrow();
        const env = readEnvironment();
        const context = await browser.newContext({
            baseURL: env.baseUrl,
            storageState: await loginE2eUser(NON_OWNER_ADMIN_EMAIL, env.adminPassword),
        });

        try {
            await assertBrowserContextAuthenticatedAs(context, NON_OWNER_ADMIN_EMAIL);

            const response = await context.request.get(exportPath(probe.event_id), { failOnStatusCode: false });

            expect(response.status()).toBe(403);
            expect(response.headers()['content-disposition']).toBeUndefined();
            expect(response.headers()['content-type'] ?? '').not.toContain('text/csv');
        } finally {
            await context.close();
        }
    });

    test('user role member diarahkan ke dashboard oleh middleware organizer', async ({ browser }): Promise<void> => {
        test.setTimeout(120_000);

        const probe = fixtureOrThrow();
        const env = readEnvironment();
        const context = await browser.newContext({
            baseURL: env.baseUrl,
            storageState: await loginE2eUser(MEMBER_EMAIL, env.adminPassword),
        });

        try {
            await assertBrowserContextAuthenticatedAs(context, MEMBER_EMAIL);

            const response = await context.request.get(exportPath(probe.event_id), {
                failOnStatusCode: false,
                maxRedirects: 0,
            });

            // `EnsureOrganizerDashboardAccess`: tanpa `events.list` → redirect ke route dashboard.
            expect(response.status()).toBe(302);
            expect(response.headers()['location'] ?? '').toMatch(/\/dashboard$/);
            expect(response.headers()['content-disposition']).toBeUndefined();
        } finally {
            await context.close();
        }
    });

    test('event tidak ada dan id non-UUID menghasilkan 404, bukan 500', async ({ browser }): Promise<void> => {
        test.setTimeout(120_000);

        const { context } = await openAdminPage(browser);

        try {
            const missing = await context.request.get(exportPath(MISSING_EVENT_ID), { failOnStatusCode: false });
            expect(missing.status()).toBe(404);
            expect(missing.headers()['content-disposition']).toBeUndefined();

            const nonUuid = await context.request.get(exportPath(NON_UUID_EVENT_ID), { failOnStatusCode: false });
            expect(nonUuid.status()).toBe(404);
            expect(nonUuid.headers()['content-disposition']).toBeUndefined();

            const body = await nonUuid.text();
            expect(body).not.toContain('Whoops');
            expect(body).not.toContain('SQLSTATE');
        } finally {
            await context.close();
        }
    });

    test('dua unduhan paralel pada data tie menghasilkan isi identik', async ({ browser }): Promise<void> => {
        test.setTimeout(120_000);

        const probe = fixtureOrThrow();
        const url = exportPath(probe.event_id);
        const { context } = await openAdminPage(browser);

        try {
            const [first, second] = await Promise.all([
                context.request.get(url, { failOnStatusCode: false }),
                context.request.get(url, { failOnStatusCode: false }),
            ]);

            expect(first.status()).toBe(200);
            expect(second.status()).toBe(200);

            const firstText = await first.text();
            const secondText = await second.text();

            expect(firstText.charCodeAt(0)).toBe(0xfeff);
            expect(secondText.charCodeAt(0)).toBe(0xfeff);

            const firstRows = parseCsvRows(stripBom(firstText));
            const secondRows = parseCsvRows(stripBom(secondText));

            expect(firstRows).toHaveLength(ANSWER_COUNT + 1);
            expect(secondRows).toHaveLength(firstRows.length);
            expect(firstRows[0]).toEqual([...CSV_HEADER]);

            // `answers_summary` bisa memuat temporary signed URL dengan `expires`
            // bertimestamp, jadi dua unduhan paralel tidak harus byte-identik; yang
            // dibandingkan adalah identitas baris dan urutannya.
            expect(secondRows.map((row: string[]): string => row[SUBMISSION_ID_INDEX] ?? '')).toEqual(
                firstRows.map((row: string[]): string => row[SUBMISSION_ID_INDEX] ?? '')
            );
        } finally {
            await context.close();
        }
    });
});
