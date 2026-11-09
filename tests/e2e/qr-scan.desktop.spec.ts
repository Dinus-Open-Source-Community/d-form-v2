import type { Locator, Page, Response, Route } from '@playwright/test';
import { expect, ownerIdentityForTest, test } from './support/fixtures';
import type { IOwnerIdentity } from './support/marker';
import { runArtisanCommand } from './support/artisan';
import { startRequestLog } from './support/network';
import { parseTinkerJsonRecord, requireTinkerNumber, requireTinkerString } from './support/tinker';

/**
 * E2E AUDIT-P1-015: check-in QR manual pada halaman scanner global (`/admin/scan`).
 *
 * `e2e:fixture` tidak bisa dipakai karena submission-nya tanpa `review_status`
 * accepted, sehingga `GlobalScanResolver` menolaknya. Setup memakai `tinker`
 * dengan marker per test: event published + form + satu `form_answers` accepted
 * yang punya `registration_code` unik. Baris ditulis lewat query builder (bukan
 * Eloquent) agar observer agregat tidak ikut berubah, dan cleanup menghapus
 * marker itu beserta attendance/email log yang mungkin dibuat job check-in.
 */

const SCAN_PATH = '/admin/scan';
const MANUAL_CODE_PLACEHOLDER = 'OPREC-2026-00001 atau kode registrasi event';
const HERO_HEADING = 'Hasil Scan Terakhir';

/** Label KPI di halaman scan; nilai diambil dari sibling `<p>` tepat setelah label. */
type TKpiLabel = 'Check-in berhasil' | 'Sudah scan' | 'Tidak valid';

interface IScanFixtureInput {
    slug: string;
    title: string;
    attendeeName: string;
    attendeeEmail: string;
    code: string;
}

interface IScanFixtureIds {
    eventId: string;
    formId: string;
    answerId: string;
}

/** Bentuk payload yang dikirim ke skrip tinker (snake_case mengikuti kolom PHP). */
interface IScanFixtureScriptPayload {
    slug: string;
    title: string;
    attendee_name: string;
    attendee_email: string;
    code: string;
}

function buildScanFixture(identity: IOwnerIdentity): IScanFixtureInput {
    return {
        slug: `e2e-audit-p1-015-${identity.ownerKey}`,
        title: `E2E AUDIT-P1-015 event ${identity.ownerKey}`,
        attendeeName: `E2E AUDIT-P1-015 attendee ${identity.ownerKey}`,
        attendeeEmail: `${identity.marker}@e2e.invalid`,
        code: `E2E-P1015-${identity.ownerKey.toUpperCase()}`,
    };
}

function toScriptPayload(fixture: IScanFixtureInput): IScanFixtureScriptPayload {
    return {
        slug: fixture.slug,
        title: fixture.title,
        attendee_name: fixture.attendeeName,
        attendee_email: fixture.attendeeEmail,
        code: fixture.code,
    };
}

function encodePayload(value: unknown): string {
    return Buffer.from(JSON.stringify(value), 'utf8').toString('base64');
}

function tinker(script: string): string {
    return runArtisanCommand(['tinker', '--execute', script]);
}

/**
 * Buat event published + form + satu submission accepted dengan `registration_code`.
 * Query builder dipakai agar `FormAnswerObserver`/`EventObserver` tidak mengubah
 * agregat global (`event_daily_registrations`, `event_category_counts`).
 */
function createScanFixture(fixture: IScanFixtureInput): IScanFixtureIds {
    const encoded = encodePayload(toScriptPayload(fixture));
    const script = String.raw`
$payload = json_decode(base64_decode('${encoded}'), true, 512, JSON_THROW_ON_ERROR);
$now = now();
$eventId = (string) Illuminate\Support\Str::uuid();
Illuminate\Support\Facades\DB::table('events')->insert([
    'id' => $eventId,
    'title' => $payload['title'],
    'slug' => $payload['slug'],
    'description' => '<p>E2E AUDIT-P1-015</p>',
    'start_date' => $now->toDateString(),
    'end_date' => $now->copy()->addDay()->toDateString(),
    'registration_start' => $now->copy()->subDay(),
    'registration_end' => $now->copy()->addDay(),
    'location' => 'E2E',
    'quota' => 10,
    'registered_count' => 0,
    'banner' => 'events/banners/placeholder.jpg',
    'price' => 0,
    'session' => 'general',
    'status' => 'published',
    'category' => 'etc',
    'created_at' => $now,
    'updated_at' => $now,
]);
$formId = (string) Illuminate\Support\Str::uuid();
Illuminate\Support\Facades\DB::table('forms')->insert([
    'id' => $formId,
    'title' => $payload['title'].' form',
    'description' => '<p>E2E AUDIT-P1-015 form</p>',
    'visible_for' => json_encode(['public'], JSON_THROW_ON_ERROR),
    'closed_at' => null,
    'event_id' => $eventId,
    'banner_url' => null,
    'banner_caption' => null,
    'metadata' => null,
    'created_at' => $now,
    'updated_at' => $now,
]);
$answerId = (string) Illuminate\Support\Str::uuid();
Illuminate\Support\Facades\DB::table('form_answers')->insert([
    'id' => $answerId,
    'answers' => json_encode(['name' => $payload['attendee_name']], JSON_THROW_ON_ERROR),
    'user_id' => null,
    'form_id' => $formId,
    'is_active' => 1,
    'review_status' => 'accepted',
    'reviewed_at' => $now,
    'reviewed_by' => null,
    'registration_code' => $payload['code'],
    'invited_email' => $payload['attendee_email'],
    'created_at' => $now,
    'updated_at' => $now,
]);
echo json_encode(['event_id' => $eventId, 'form_id' => $formId, 'answer_id' => $answerId, 'code' => $payload['code']]);
`;

    const record = parseTinkerJsonRecord(tinker(script), 'create scan fixture');
    const ids: IScanFixtureIds = {
        eventId: requireTinkerString(record, 'event_id', 'create scan fixture'),
        formId: requireTinkerString(record, 'form_id', 'create scan fixture'),
        answerId: requireTinkerString(record, 'answer_id', 'create scan fixture'),
    };

    if (requireTinkerString(record, 'code', 'create scan fixture') !== fixture.code) {
        throw new Error('create scan fixture: registration_code yang dikembalikan tidak cocok.');
    }

    return ids;
}

/**
 * Hapus attendance + email log + answer + field + form + event milik marker,
 * berurutan agar FK `restrict`/`nullOnDelete` tidak menghalangi. Idempoten:
 * marker yang tidak ada menghasilkan hitungan 0. Setiap sisa baris membuat
 * spec gagal, sehingga kebocoran data tidak lolos diam-diam.
 */
function removeScanFixture(fixture: IScanFixtureInput): void {
    const encoded = encodePayload(toScriptPayload(fixture));
    const script = String.raw`
$payload = json_decode(base64_decode('${encoded}'), true, 512, JSON_THROW_ON_ERROR);
$eventId = Illuminate\Support\Facades\DB::table('events')->where('slug', $payload['slug'])->value('id');
$formIds = $eventId === null
    ? []
    : Illuminate\Support\Facades\DB::table('forms')->where('event_id', $eventId)->pluck('id')->all();
$answerIds = Illuminate\Support\Facades\DB::table('form_answers')
    ->where('registration_code', $payload['code'])
    ->pluck('id')
    ->all();
if ($formIds !== []) {
    $answerIds = array_values(array_unique(array_merge(
        $answerIds,
        Illuminate\Support\Facades\DB::table('form_answers')->whereIn('form_id', $formIds)->pluck('id')->all(),
    )));
}
if ($answerIds !== []) {
    Illuminate\Support\Facades\DB::table('event_attendances')->whereIn('form_answer_id', $answerIds)->delete();
    Illuminate\Support\Facades\DB::table('email_logs')->whereIn('form_answer_id', $answerIds)->delete();
    Illuminate\Support\Facades\DB::table('form_answers')->whereIn('id', $answerIds)->delete();
}
if ($eventId !== null) {
    Illuminate\Support\Facades\DB::table('email_logs')->where('event_id', $eventId)->delete();
    if ($formIds !== []) {
        Illuminate\Support\Facades\DB::table('form_fields')->whereIn('form_id', $formIds)->delete();
        Illuminate\Support\Facades\DB::table('forms')->whereIn('id', $formIds)->delete();
    }
    Illuminate\Support\Facades\DB::table('events')->where('id', $eventId)->delete();
}
echo json_encode([
    'attendance_by_answer' => $answerIds === []
        ? 0
        : Illuminate\Support\Facades\DB::table('event_attendances')->whereIn('form_answer_id', $answerIds)->count(),
    'email_by_answer' => $answerIds === []
        ? 0
        : Illuminate\Support\Facades\DB::table('email_logs')->whereIn('form_answer_id', $answerIds)->count(),
    'email_by_event' => $eventId === null
        ? 0
        : Illuminate\Support\Facades\DB::table('email_logs')->where('event_id', $eventId)->count(),
    'answers_by_code' => Illuminate\Support\Facades\DB::table('form_answers')
        ->where('registration_code', $payload['code'])
        ->count(),
    'fields_by_form' => $formIds === []
        ? 0
        : Illuminate\Support\Facades\DB::table('form_fields')->whereIn('form_id', $formIds)->count(),
    'forms_by_form' => $formIds === []
        ? 0
        : Illuminate\Support\Facades\DB::table('forms')->whereIn('id', $formIds)->count(),
    'events_by_slug' => Illuminate\Support\Facades\DB::table('events')->where('slug', $payload['slug'])->count(),
]);
`;

    const counts = parseTinkerJsonRecord(tinker(script), 'cleanup scan fixture');
    const leftover = {
        attendance_by_answer: requireTinkerNumber(counts, 'attendance_by_answer', 'cleanup scan fixture'),
        email_by_answer: requireTinkerNumber(counts, 'email_by_answer', 'cleanup scan fixture'),
        email_by_event: requireTinkerNumber(counts, 'email_by_event', 'cleanup scan fixture'),
        answers_by_code: requireTinkerNumber(counts, 'answers_by_code', 'cleanup scan fixture'),
        fields_by_form: requireTinkerNumber(counts, 'fields_by_form', 'cleanup scan fixture'),
        forms_by_form: requireTinkerNumber(counts, 'forms_by_form', 'cleanup scan fixture'),
        events_by_slug: requireTinkerNumber(counts, 'events_by_slug', 'cleanup scan fixture'),
    };

    if (Object.values(leftover).some((count: number): boolean => count !== 0)) {
        throw new Error(`cleanup scan fixture menyisakan baris: ${JSON.stringify(leftover)}`);
    }
}

/** Jumlah baris `event_attendances` untuk satu submission (bukti utama DB). */
function readAttendanceCount(answerId: string): number {
    const script = String.raw`
echo json_encode(['count' => Illuminate\Support\Facades\DB::table('event_attendances')->where('form_answer_id', '${answerId}')->count()]);
`;
    return requireTinkerNumber(parseTinkerJsonRecord(tinker(script), 'attendance count'), 'count', 'attendance count');
}

/** Nilai KPI diambil dari `<p>` nilai yang mengikuti `<p>` label. */
function kpiValue(page: Page, label: TKpiLabel): Locator {
    return page.locator(`xpath=//p[normalize-space()="${label}"]/following-sibling::p[1]`);
}

async function readKpi(page: Page, label: TKpiLabel): Promise<number> {
    const raw = await kpiValue(page, label).textContent();
    const value = Number((raw ?? '').trim());

    if (!Number.isFinite(value)) {
        throw new Error(`KPI "${label}" tidak numerik: ${raw ?? 'null'}`);
    }

    return value;
}

function heroCard(page: Page): Locator {
    return page
        .locator('[data-slot="card"]')
        .filter({ has: page.getByRole('heading', { name: HERO_HEADING, exact: true }) });
}

function isScanStoreResponse(response: Response): boolean {
    return response.request().method() === 'POST' && new URL(response.url()).pathname === SCAN_PATH;
}

/** Isi input manual lalu klik `Proses check-in`; kembalikan response POST yang terjadi. */
async function submitManualScan(page: Page, code: string): Promise<Response> {
    await page.getByPlaceholder(MANUAL_CODE_PLACEHOLDER).fill(code);

    const responsePromise = page.waitForResponse(isScanStoreResponse);
    await page.getByRole('button', { name: 'Proses check-in' }).click();

    return responsePromise;
}

test.describe('AUDIT-P1-015 QR scan manual', () => {
    test.beforeEach(async ({ page }): Promise<void> => {
        await page.route('**/seeyu.doscom.org/**', (route: Route) => route.abort());
    });

    test('happy path: kode registrasi manual membuat tepat satu attendance', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = buildScanFixture(identity);
        const requestLog = startRequestLog(page);

        try {
            const ids = createScanFixture(fixture);

            await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
            await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

            const beforeSuccess = await readKpi(page, 'Check-in berhasil');
            const response = await submitManualScan(page, fixture.code);

            expect(response.status(), 'check-in kode valid harus 200').toBe(200);

            // Envelope JSON tidak dibaca ulang: Chromium bisa membuang body response
            // setelah halaman re-render. Status + hero + baris DB adalah bukti flow.
            const hero = heroCard(page);
            await expect(hero.getByText(fixture.attendeeName, { exact: true })).toBeVisible();
            await expect(hero.getByText('Check-in berhasil', { exact: true })).toBeVisible();
            await expect(hero.getByText('Manual', { exact: true })).toBeVisible();
            // Feed lintas meja bisa menambah KPI lain; bukti eksak ada pada jumlah attendance.
            await expect
                .poll(() => readKpi(page, 'Check-in berhasil'), { timeout: 15_000 })
                .toBeGreaterThanOrEqual(beforeSuccess + 1);

            expect(readAttendanceCount(ids.answerId), 'tepat satu attendance').toBe(1);
            expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
        } finally {
            try {
                expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
            } finally {
                requestLog.stop();
                removeScanFixture(fixture);
            }
        }
    });

    test('failure duplicate: scan kode yang sama dua kali tetap satu attendance', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = buildScanFixture(identity);
        const requestLog = startRequestLog(page);

        try {
            const ids = createScanFixture(fixture);

            await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
            await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

            const first = await submitManualScan(page, fixture.code);
            expect(first.status(), 'scan pertama harus 200').toBe(200);
            expect(readAttendanceCount(ids.answerId)).toBe(1);

            const beforeDuplicate = await readKpi(page, 'Sudah scan');
            const second = await submitManualScan(page, fixture.code);

            expect(second.status(), 'scan kedua harus 409').toBe(409);

            await expect(
                page.locator('[data-sonner-toast]').filter({ hasText: 'Peserta sudah pernah scan.' })
            ).toBeVisible();

            const hero = heroCard(page);
            await expect(hero.getByText(fixture.attendeeName, { exact: true })).toBeVisible();
            await expect(hero.getByText('Sudah pernah scan', { exact: true })).toBeVisible();
            await expect.poll(() => readKpi(page, 'Sudah scan'), { timeout: 15_000 }).toBe(beforeDuplicate + 1);

            expect(readAttendanceCount(ids.answerId), 'duplikat tidak menambah attendance').toBe(1);
            expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
        } finally {
            try {
                expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
            } finally {
                requestLog.stop();
                removeScanFixture(fixture);
            }
        }
    });

    test('anomaly invalid: kode acak ditolak 422 tanpa attendance', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = buildScanFixture(identity);
        const invalidCode = `TIDAK-VALID-${identity.ownerKey.toUpperCase()}`;
        const requestLog = startRequestLog(page);

        try {
            const ids = createScanFixture(fixture);

            await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
            await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

            const beforeInvalid = await readKpi(page, 'Tidak valid');
            const response = await submitManualScan(page, invalidCode);

            expect(response.status(), 'kode acak harus 422').toBe(422);

            const hero = heroCard(page);
            await expect(hero.getByText('Tidak dapat diproses', { exact: true })).toBeVisible();
            await expect.poll(() => readKpi(page, 'Tidak valid'), { timeout: 15_000 }).toBe(beforeInvalid + 1);

            expect(readAttendanceCount(ids.answerId), 'kode acak tidak boleh check-in submission lain').toBe(0);
            expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
        } finally {
            try {
                expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
            } finally {
                requestLog.stop();
                removeScanFixture(fixture);
            }
        }
    });
});
