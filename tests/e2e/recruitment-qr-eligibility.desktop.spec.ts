import { randomUUID } from 'node:crypto';
import type { Page, Response } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import { readXsrfToken } from './support/network';

/**
 * E2E BE-P2-003: eligibility kirim QR pada halaman periode recruitment.
 *
 * Spec membuat satu periode fixture kecil dengan matriks applicant deterministik,
 * lalu membuktikan lewat HTTP nyata bahwa:
 * 1. `qr_eligible_count` / `qr_sent_count` / `qr_status_summary` sesuai aturan
 *    eligibility (tahap interview, tidak cancelled, belum absen, dan belum punya
 *    `recruitment_interview_scheduled` berstatus sent atau queued).
 * 2. Kirim QR tanpa `include_sent` hanya membuat baris `email_logs` untuk
 *    applicant eligible.
 * 3. Kirim ulang dengan `include_sent=true` menambah baris baru untuk applicant
 *    yang sudah terkirim tanpa merusak semantik eligibility.
 * 4. `qr_sent_count` melacak delivery sukses sebelumnya, sedangkan
 *    `qr_status_summary` menunjukkan status terbaru; keduanya dapat overlap saat resend.
 * 5. Input anomali tidak pernah menghasilkan 5xx.
 *
 * Data stress (1 juta baris) tidak dipakai untuk angka eksak; periode stress
 * hanya dipakai sebagai pembanding waktu navigasi (bukti, bukan gate).
 */

/** Prefix domain untuk seluruh fixture pada spec ini. */
const FIXTURE_MARKER = 'E2E BE-P2-003';
let fixtureMarker = '';
let periodName = '';
let periodSlug = '';
let otherPeriodName = '';
let otherPeriodSlug = '';
let fixtureDivisionCode = '';
let otherPeriodPath = '';
let fixtureCreated = false;

/** Notifikasi QR yang diuji dan notifikasi lain sebagai pembanding. */
const QR_NOTIFICATION = 'recruitment_interview_scheduled';
const OTHER_NOTIFICATION = 'recruitment_passed_screening';

/** Batas atas waktu navigasi periode fixture; generik, bukan gate performa. */
const FIXTURE_NAVIGATION_BUDGET_MS = 30_000;
/** Navigasi periode stress hanya bukti; timeout besar agar tidak flaky. */
const STRESS_NAVIGATION_TIMEOUT_MS = 120_000;
/** Batas polling baris `email_logs` setelah POST (baris dibuat sinkron oleh request). */
const EMAIL_ROW_POLL_TIMEOUT_MS = 30_000;

const MISSING_PERIOD_ID = '11111111-1111-1111-1111-111111111111';

type TLogStatus = 'sent' | 'queued' | 'failed';

interface IApplicationRow {
    key: string;
    registrationNumber: string;
    name: string;
    nim: string;
    email: string;
    stage: 'interview' | 'screening';
    cancelled: boolean;
    attendance: boolean;
    otherPeriod: boolean;
    log: { status: TLogStatus; notificationType: string } | null;
    minutesAgo: number;
}

interface IEmailLogRow {
    registration_number: string;
    status: string;
    delivery_key: string | null;
}

/** Bentuk baris yang dikirim ke skrip tinker (snake_case mengikuti kolom PHP). */
interface IPhpApplicationRow {
    registration_number: string;
    name: string;
    nim: string;
    email: string;
    stage: 'interview' | 'screening';
    cancelled: boolean;
    attendance: boolean;
    other_period: boolean;
    log: { status: TLogStatus; notification_type: string } | null;
    minutes_ago: number;
}

type TFixturePayload =
    | readonly IApplicationRow[]
    | readonly IPhpApplicationRow[]
    | readonly string[]
    | { email: string; password: string; name: string };

interface IFixtureProbe {
    period_id: string | null;
    other_period_id: string | null;
    applications: number;
    eligible: number;
    sent: number;
    summary: { sent: number; failed: number; queued: number };
    other_applications: number;
    stress_period_id: string | null;
}

interface IInertiaPayload {
    component: string;
    version: string;
    props: {
        tab?: string;
        qr_eligible_count?: number;
        qr_sent_count?: number;
        qr_status_summary?: { sent: number; failed: number; queued: number } | null;
    };
}

interface IQrStatusPayload {
    sent: number;
    failed: number;
    queued: number;
    recipients: { application_id: string; status: string }[];
}

interface IValidationErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
}

interface IAnomalyQuery {
    label: string;
    query: string;
    errorKey: string;
}

/**
 * Matriks fixture. Setiap baris menguji satu cabang aturan eligibility.
 *
 * eligible = tahap interview + `cancelled_at` null + belum punya attendance +
 * tidak punya log `recruitment_interview_scheduled` berstatus sent atau queued.
 */
const APPLICATION_TEMPLATES: IApplicationRow[] = [
    {
        key: 'eligible-primary',
        registrationNumber: 'OPREC-E2E-P2003-01',
        name: `${FIXTURE_MARKER} Eligible Primary`,
        nim: 'A11.2024.23001',
        email: 'e2e-p2003-01@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: false,
        otherPeriod: false,
        log: null,
        minutesAgo: 9,
    },
    {
        key: 'eligible-secondary',
        registrationNumber: 'OPREC-E2E-P2003-02',
        name: `${FIXTURE_MARKER} Eligible Secondary`,
        nim: 'A11.2024.23002',
        email: 'e2e-p2003-02@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: false,
        otherPeriod: false,
        log: null,
        minutesAgo: 8,
    },
    {
        // Sudah terkirim: masuk qr_sent_count, bukan pool kirim biasa.
        key: 'already-sent',
        registrationNumber: 'OPREC-E2E-P2003-03',
        name: `${FIXTURE_MARKER} Already Sent`,
        nim: 'A11.2024.23003',
        email: 'e2e-p2003-03@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: false,
        otherPeriod: false,
        log: { status: 'sent', notificationType: QR_NOTIFICATION },
        minutesAgo: 7,
    },
    {
        // QR queued masih in-flight, jadi tidak masuk pool kirim biasa maupun kirim ulang.
        key: 'queued-log',
        registrationNumber: 'OPREC-E2E-P2003-04',
        name: `${FIXTURE_MARKER} Queued Log`,
        nim: 'A11.2024.23004',
        email: 'e2e-p2003-04@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: false,
        otherPeriod: false,
        log: { status: 'queued', notificationType: QR_NOTIFICATION },
        minutesAgo: 6,
    },
    {
        // Failed bukan sent, jadi masih eligible untuk dikirim ulang.
        key: 'failed-log',
        registrationNumber: 'OPREC-E2E-P2003-05',
        name: `${FIXTURE_MARKER} Failed Log`,
        nim: 'A11.2024.23005',
        email: 'e2e-p2003-05@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: false,
        otherPeriod: false,
        log: { status: 'failed', notificationType: QR_NOTIFICATION },
        minutesAgo: 5,
    },
    {
        // Sudah absen: tidak boleh dikirimi QR lagi.
        key: 'attended',
        registrationNumber: 'OPREC-E2E-P2003-06',
        name: `${FIXTURE_MARKER} Attended`,
        nim: 'A11.2024.23006',
        email: 'e2e-p2003-06@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: true,
        otherPeriod: false,
        log: null,
        minutesAgo: 4,
    },
    {
        // Bukan tahap interview.
        key: 'screening-stage',
        registrationNumber: 'OPREC-E2E-P2003-07',
        name: `${FIXTURE_MARKER} Screening Stage`,
        nim: 'A11.2024.23007',
        email: 'e2e-p2003-07@example.test',
        stage: 'screening',
        cancelled: false,
        attendance: false,
        otherPeriod: false,
        log: null,
        minutesAgo: 3,
    },
    {
        // Interview yang dibatalkan (application cancelled): dikecualikan lewat cancelled_at.
        key: 'cancelled-interview',
        registrationNumber: 'OPREC-E2E-P2003-08',
        name: `${FIXTURE_MARKER} Cancelled Interview`,
        nim: 'A11.2024.23008',
        email: 'e2e-p2003-08@example.test',
        stage: 'interview',
        cancelled: true,
        attendance: false,
        otherPeriod: false,
        log: null,
        minutesAgo: 2,
    },
    {
        // Punya log sent dengan notification_type lain: tetap eligible untuk QR.
        key: 'other-notification-sent',
        registrationNumber: 'OPREC-E2E-P2003-09',
        name: `${FIXTURE_MARKER} Other Notification Sent`,
        nim: 'A11.2024.23009',
        email: 'e2e-p2003-09@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: false,
        otherPeriod: false,
        log: { status: 'sent', notificationType: OTHER_NOTIFICATION },
        minutesAgo: 1,
    },
    {
        // Interview di periode lain: tidak boleh masuk hitungan periode fixture.
        key: 'other-period',
        registrationNumber: 'OPREC-E2E-P2003-10',
        name: `${FIXTURE_MARKER} Other Period`,
        nim: 'A11.2024.23010',
        email: 'e2e-p2003-10@example.test',
        stage: 'interview',
        cancelled: false,
        attendance: false,
        otherPeriod: true,
        log: null,
        minutesAgo: 10,
    },
];

let applications: IApplicationRow[] = [];
let registrationNumbers: string[] = [];
let keyByRegistration = new Map<string, string>();

/** QR queued dan sent masih in-flight; failed tetap eligible untuk dikirim ulang. */
const ELIGIBLE_KEYS: string[] = ['eligible-primary', 'eligible-secondary', 'failed-log', 'other-notification-sent'];
const SENT_KEYS: string[] = ['already-sent'];
const QUEUED_KEYS: string[] = ['queued-log'];
const QR_IN_FLIGHT_KEYS: string[] = [...SENT_KEYS, ...QUEUED_KEYS];
/** Sudah absen / bukan interview / cancelled tidak pernah menerima QR baru. */
const EXCLUDED_KEYS: string[] = ['attended', 'screening-stage', 'cancelled-interview'];
/** Kirim biasa: hanya pool eligible. */
const SEND_RECIPIENT_KEYS: string[] = [...ELIGIBLE_KEYS];
/** Kirim ulang: eligible + sent; QR yang masih queued tetap tidak dikirim ulang. */
const RESEND_RECIPIENT_KEYS: string[] = [...ELIGIBLE_KEYS, ...SENT_KEYS];

/** Jumlah applicant periode fixture yang memenuhi dasar QR (sebelum sent/queued dipisah). */
const PERIOD_APPLICATION_COUNT = 9;
let interviewQrCandidateCount = 0;

/** Input GET liar: harus ditolak validasi dan parameter dibuang dari target redirect. */
const ANOMALY_QUERIES: IAnomalyQuery[] = [
    { label: 'stage asing', query: 'stage=bogus-value', errorKey: 'stage' },
    { label: 'semester di luar batas', query: 'semester=99', errorKey: 'semester' },
    { label: 'division_id bukan uuid', query: 'division_id=not-a-uuid', errorKey: 'division_id' },
    { label: 'queue path traversal', query: `queue=${encodeURIComponent('../../etc/passwd')}`, errorKey: 'queue' },
    { label: 'page nol', query: 'page=0', errorKey: 'page' },
];

let periodId = '';
let periodPath = '';
let stressPeriodId: string | null = null;

/** Skenario anomali mengumpulkan response >= 500 agar tidak ada 5xx yang lolos. */
let serverErrors: string[] = [];

/** Alasan kirim QR dilewati bila konfigurasi mail berisiko mengirim email nyata. */
let dispatchSkipReason: string | null = null;

let memberEmail = '';
let memberName = '';
let memberCreated = false;

function prepareFixtureIdentity(): void {
    const fixtureToken = randomUUID().replaceAll('-', '');
    const registrationToken = Buffer.from(fixtureToken, 'hex').toString('base64url');

    fixtureMarker = `${FIXTURE_MARKER} ${fixtureToken}`;
    periodName = `${fixtureMarker} qr eligibility`;
    periodSlug = `e2e-be-p2-003-${fixtureToken}`;
    otherPeriodName = `${fixtureMarker} other period`;
    otherPeriodSlug = `${periodSlug}-other`;
    fixtureDivisionCode = `e2e-p2-003-${fixtureToken}`;
    memberEmail = `e2e-be-p2-003-member-${fixtureToken}@example.test`;
    memberName = `${fixtureMarker} Member`;
    memberCreated = false;
    periodId = '';
    periodPath = '';
    otherPeriodPath = '';
    stressPeriodId = null;
    fixtureCreated = false;

    applications = APPLICATION_TEMPLATES.map((application, index): IApplicationRow => {
        const rowId = String(index + 1).padStart(2, '0');

        return {
            ...application,
            registrationNumber: `E2E-${registrationToken}-${rowId}`,
            name: application.name.replace(FIXTURE_MARKER, fixtureMarker),
            nim: `E2E${fixtureToken}${rowId}`,
            email: `e2e-${fixtureToken}-${rowId}@example.test`,
        };
    });
    registrationNumbers = applications.map((application) => application.registrationNumber);
    keyByRegistration = new Map(
        applications.map((application): [string, string] => [application.registrationNumber, application.key])
    );
    interviewQrCandidateCount = applications.filter(
        (application: IApplicationRow): boolean =>
            !application.otherPeriod &&
            application.stage === 'interview' &&
            !application.cancelled &&
            !application.attendance
    ).length;
}

function encodePayload(value: TFixturePayload): string {
    return Buffer.from(JSON.stringify(value), 'utf8').toString('base64');
}

function tinker(script: string): string {
    return runArtisanCommand(['tinker', '--execute', script]);
}

/** Payload Inertia awal ada di `script[data-page="app"]` (Inertia v2), bukan atribut #app. */
async function readInertiaPage(page: Page): Promise<IInertiaPayload> {
    const raw = await page.locator('script[data-page="app"]').textContent();

    if (raw === null) {
        throw new Error('Payload Inertia tidak ditemukan pada script[data-page="app"].');
    }

    return JSON.parse(raw) as IInertiaPayload;
}

/**
 * Periksa apakah mengirim QR aman. `log`/`array` tidak pernah mengirim email nyata.
 * Dev memakai SMTP, tetapi `MAIL_TEST_REDIRECT` (CONTEXT.md §10) mengalihkan seluruh
 * email ke satu inbox aman, sehingga fixture tidak pernah sampai ke penerima asli.
 * Tanpa redirect itu, kirim QR dilewati agar spec tidak memicu email nyata.
 */
function resolveDispatchSkipReason(): string | null {
    const raw = tinker(
        "echo json_encode(['mailer' => config('mail.default'), 'redirect' => config('mail.test_redirect'), 'env' => app()->environment()]);"
    );
    const config = JSON.parse(raw) as { mailer: string; redirect: string | null; env: string };

    if (config.mailer === 'log' || config.mailer === 'array') {
        return null;
    }

    if (config.env === 'local' && config.redirect !== null && config.redirect !== '') {
        return null;
    }

    return `Mailer "${config.mailer}" aktif tanpa MAIL_TEST_REDIRECT; kirim QR dilewati agar tidak mengirim email nyata.`;
}

/** Rencana fixture dikirim sebagai base64 agar tidak ada masalah kutip di tinker. */
function createFixtures(): void {
    const rows = applications.map((application) => ({
        registration_number: application.registrationNumber,
        name: application.name,
        nim: application.nim,
        email: application.email,
        stage: application.stage,
        cancelled: application.cancelled,
        attendance: application.attendance,
        other_period: application.otherPeriod,
        log:
            application.log === null
                ? null
                : { status: application.log.status, notification_type: application.log.notificationType },
        minutes_ago: application.minutesAgo,
    }));

    const script = String.raw`
$rows = json_decode(base64_decode('${encodePayload(rows)}'), true, 512, JSON_THROW_ON_ERROR);
$period = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${periodName}',
    'slug' => '${periodSlug}',
    'created_by' => null,
    'interview_starts_at' => today()->subDay()->toDateString(),
    'interview_ends_at' => today()->addMonth()->toDateString(),
]);
$otherPeriod = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${otherPeriodName}',
    'slug' => '${otherPeriodSlug}',
    'created_by' => null,
]);
$division = App\Models\Recruitment\RecruitmentDivision::query()
    ->where('is_active', true)
    ->orderBy('code')
    ->first();
if ($division === null) {
    $division = App\Models\Recruitment\RecruitmentDivision::factory()->create([
        'name' => 'Divisi '.${JSON.stringify(fixtureMarker)},
        'code' => '${fixtureDivisionCode}',
        'is_active' => true,
    ]);
}
$session = App\Models\Recruitment\RecruitmentInterviewSession::query()->create([
    'recruitment_period_id' => $period->id,
    'recruitment_division_id' => $division->id,
    'session_date' => today()->toDateString(),
    'starts_at' => '09:00:00',
    'ends_at' => '10:00:00',
    'location' => 'Lab E2E',
    'room' => 'R1',
    'is_active' => true,
]);
$base = now()->startOfSecond();
foreach ($rows as $row) {
    $target = $row['other_period'] ? $otherPeriod : $period;
    $application = App\Models\Recruitment\RecruitmentApplication::factory()
        ->for($target, 'period')
        ->create([
            'registration_number' => $row['registration_number'],
            'full_name' => $row['name'],
            'nim' => $row['nim'],
            'personal_email' => $row['email'],
            'student_email' => $row['email'],
            'primary_division_id' => $division->id,
            'secondary_division_id' => null,
            'stage' => $row['stage'],
            'result' => 'pending',
            'revision_required' => false,
            'cancelled_at' => $row['cancelled'] ? now() : null,
            'submitted_at' => $base->copy()->subMinutes($row['minutes_ago']),
        ]);
    if ($row['attendance']) {
        App\Models\Recruitment\RecruitmentAttendance::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'method' => 'qr',
            'checked_in_at' => now(),
            'checked_in_by' => null,
        ]);
    }
    if ($row['log'] !== null) {
        App\Models\EmailLog::query()->create([
            'recruitment_application_id' => $application->id,
            'event_id' => null,
            'user_id' => null,
            'recipient_email' => $row['email'],
            'status' => $row['log']['status'],
            'notification_type' => $row['log']['notification_type'],
            'error_message' => $row['log']['status'] === 'failed' ? 'SMTP timeout' : null,
            'sent_at' => $row['log']['status'] === 'sent' ? now() : null,
        ]);
    }
}
echo 'ok';
`;

    tinker(script);
}

/** Tinker tidak memberi exit code non-zero saat exception; hasil create diverifikasi ulang. */
function assertFixturesExist(): void {
    const script = String.raw`
$period = App\Models\Recruitment\RecruitmentPeriod::query()->where('slug', '${periodSlug}')->first();
if ($period === null) {
    echo json_encode(['period_id' => null, 'other_period_id' => null, 'applications' => 0, 'eligible' => 0, 'sent' => 0, 'summary' => ['sent' => 0, 'failed' => 0, 'queued' => 0], 'other_applications' => 0, 'stress_period_id' => null]);
    exit(0);
}
$base = App\Models\Recruitment\RecruitmentApplication::query()
    ->where('recruitment_period_id', $period->id)
    ->whereNull('cancelled_at')
    ->where('stage', 'interview')
    ->whereDoesntHave('attendance');
$eligible = (clone $base)->whereNotExists(function ($query): void {
    $query->selectRaw('1')
        ->from('email_logs')
        ->whereColumn('email_logs.recruitment_application_id', 'recruitment_applications.id')
        ->where('email_logs.notification_type', '${QR_NOTIFICATION}')
        ->whereIn('email_logs.status', ['sent', 'queued']);
})->count();
$sent = (clone $base)->whereExists(function ($query): void {
    $query->selectRaw('1')
        ->from('email_logs')
        ->whereColumn('email_logs.recruitment_application_id', 'recruitment_applications.id')
        ->where('email_logs.notification_type', '${QR_NOTIFICATION}')
        ->where('email_logs.status', 'sent');
})->count();
$applications = App\Models\Recruitment\RecruitmentApplication::query()
    ->where('recruitment_period_id', $period->id)
    ->count();
$applicationIds = App\Models\Recruitment\RecruitmentApplication::query()
    ->where('recruitment_period_id', $period->id)
    ->pluck('id');
$logs = App\Models\EmailLog::query()
    ->whereIn('recruitment_application_id', $applicationIds->all())
    ->where('notification_type', '${QR_NOTIFICATION}')
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->get(['recruitment_application_id', 'status']);
$latest = [];
foreach ($logs as $log) {
    $latest[$log->recruitment_application_id] ??= $log->status->value;
}
$summary = ['sent' => 0, 'failed' => 0, 'queued' => 0];
foreach ($latest as $status) {
    if (isset($summary[$status])) {
        $summary[$status]++;
    }
}
$other = App\Models\Recruitment\RecruitmentPeriod::query()->where('slug', '${otherPeriodSlug}')->first();
$otherApplications = $other === null
    ? 0
    : App\Models\Recruitment\RecruitmentApplication::query()->where('recruitment_period_id', $other->id)->count();
$stress = App\Models\Recruitment\RecruitmentPeriod::query()
    ->where('slug', 'like', 'stress-oprec-%')
    ->orderByDesc('slug')
    ->first();
echo json_encode([
    'period_id' => $period->id,
    'other_period_id' => $other?->id,
    'applications' => $applications,
    'eligible' => $eligible,
    'sent' => $sent,
    'summary' => $summary,
    'other_applications' => $otherApplications,
    'stress_period_id' => $stress?->id,
]);
`;

    const probe = JSON.parse(tinker(script)) as IFixtureProbe;

    if (
        probe.period_id === null ||
        probe.period_id === '' ||
        probe.other_period_id === null ||
        probe.other_period_id === ''
    ) {
        throw new Error('Fixture BE-P2-003 tidak mengembalikan period IDs.');
    }

    if (probe.applications !== PERIOD_APPLICATION_COUNT || probe.other_applications !== 1) {
        throw new Error(
            `Jumlah applicant fixture tidak sesuai: periode=${probe.applications}, periode lain=${probe.other_applications}`
        );
    }

    if (probe.eligible !== ELIGIBLE_KEYS.length || probe.sent !== SENT_KEYS.length) {
        throw new Error(`Eligibility fixture tidak sesuai: eligible=${probe.eligible}, sent=${probe.sent}`);
    }

    if (probe.summary.sent !== 1 || probe.summary.failed !== 1 || probe.summary.queued !== 1) {
        throw new Error(`Ringkasan QR fixture tidak sesuai: ${JSON.stringify(probe.summary)}`);
    }

    periodId = probe.period_id;
    periodPath = `/admin/recruitment/periods/${periodId}`;
    otherPeriodPath = `/admin/recruitment/periods/${probe.other_period_id}`;
    stressPeriodId = probe.stress_period_id;
}

/** Hapus email log, attendance, sesi, lalu periode (termasuk soft-deleted) agar slug tidak bentrok. */
function removeFixtures(): void {
    const script = String.raw`
$registrations = json_decode(base64_decode('${encodePayload(registrationNumbers)}'), true, 512, JSON_THROW_ON_ERROR);
$applicationIds = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', $registrations)
    ->pluck('id')
    ->all();
App\Models\EmailLog::query()->whereIn('recruitment_application_id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentAttendance::query()->whereIn('recruitment_application_id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentInterview::query()->whereIn('recruitment_application_id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentActivityLog::query()->whereIn('recruitment_application_id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentApplication::query()->whereIn('id', $applicationIds)->delete();
$periodIds = App\Models\Recruitment\RecruitmentPeriod::withTrashed()
    ->whereIn('slug', ['${periodSlug}', '${otherPeriodSlug}'])
    ->pluck('id')
    ->all();
App\Models\Recruitment\RecruitmentInterviewSession::query()->whereIn('recruitment_period_id', $periodIds)->delete();
App\Models\Recruitment\RecruitmentPeriod::withTrashed()
    ->whereIn('slug', ['${periodSlug}', '${otherPeriodSlug}'])
    ->forceDelete();
App\Models\Recruitment\RecruitmentDivision::query()
    ->where('code', '${fixtureDivisionCode}')
    ->where('name', 'Divisi '.${JSON.stringify(fixtureMarker)})
    ->delete();
echo 'ok';
`;

    tinker(script);
}

/** Baris `email_logs` tipe QR untuk seluruh applicant fixture. */
function readQrLogs(): IEmailLogRow[] {
    const script = String.raw`
$registrations = json_decode(base64_decode('${encodePayload(registrationNumbers)}'), true, 512, JSON_THROW_ON_ERROR);
$applications = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', $registrations)
    ->pluck('id', 'registration_number');
$logs = App\Models\EmailLog::query()
    ->whereIn('recruitment_application_id', $applications->values()->all())
    ->where('notification_type', '${QR_NOTIFICATION}')
    ->get(['recruitment_application_id', 'status', 'delivery_key']);
$output = [];
foreach ($logs as $log) {
    $registration = $applications->search($log->recruitment_application_id);
    if ($registration === false || $registration === null) {
        continue;
    }
    $output[] = [
        'registration_number' => (string) $registration,
        'status' => $log->status->value,
        'delivery_key' => $log->delivery_key,
    ];
}
echo json_encode($output);
`;

    return JSON.parse(tinker(script)) as IEmailLogRow[];
}

/** Jumlah log QR per key fixture. */
function countQrLogsByKey(): Record<string, number> {
    const counts: Record<string, number> = {};

    for (const row of readQrLogs()) {
        const key = keyByRegistration.get(row.registration_number);
        if (key === undefined) {
            continue;
        }
        counts[key] = (counts[key] ?? 0) + 1;
    }

    return counts;
}

/** Baris log QR untuk satu key fixture (hanya status/delivery_key). */
function qrLogsFor(key: string): IEmailLogRow[] {
    return readQrLogs().filter((row: IEmailLogRow): boolean => keyByRegistration.get(row.registration_number) === key);
}

async function openPeriod(page: Page): Promise<void> {
    const list = page.locator('[data-applicant-list]');

    // Server dev dipakai bersama lane lain (Octane 1 worker), jadi satu render bisa
    // melewati 30 detik saat spec lain menekan DB. Satu percobaan ulang navigasi
    // tidak mengubah assertion: halaman tetap wajib merender list.
    for (let attempt = 0; attempt < 2; attempt += 1) {
        await page.goto(`${periodPath}?tab=peserta`, { waitUntil: 'load' });

        try {
            await expect(list).toBeVisible({ timeout: 30_000 });

            return;
        } catch (error: unknown) {
            if (attempt === 1) {
                throw error;
            }
        }
    }
}

/** Klik tombol Kirim QR lalu konfirmasi dialog AlertDialog yang terbuka. */
async function confirmQrDialog(page: Page): Promise<void> {
    await page.getByRole('button', { name: /^Kirim QR ke \d+ applicant tahap interview$/ }).click();

    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('heading', { name: 'Kirim QR interview?' })).toBeVisible();

    // Overlay dialog pada stack ini menutupi kontennya sendiri, sehingga klik pointer
    // terintersep. Fokus + Enter adalah jalur user nyata (sama seperti spec upload).
    const confirm = dialog.getByRole('button', { name: 'Kirim', exact: true });
    await confirm.focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeHidden({ timeout: 30_000 });
}

/** Tunggu sampai setiap key memiliki jumlah log QR sesuai harapan. */
async function waitForQrLogCounts(expected: Record<string, number>): Promise<void> {
    await expect
        .poll(
            (): boolean => {
                const counts = countQrLogsByKey();

                return Object.entries(expected).every(
                    ([key, value]: [string, number]): boolean => (counts[key] ?? 0) === value
                );
            },
            { timeout: EMAIL_ROW_POLL_TIMEOUT_MS, intervals: [500, 1_000] }
        )
        .toBe(true);
}

/** Status kirim QR lewat endpoint JSON (bukti ringkasan tanpa menebak status job). */
async function readQrStatus(page: Page): Promise<IQrStatusPayload> {
    const response = await page.request.get(`${periodPath}/qr-status`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        failOnStatusCode: false,
    });

    expect(response.status(), 'endpoint qr-status harus 200').toBe(200);

    return (await response.json()) as IQrStatusPayload;
}

function createMemberUser(email: string, password: string): void {
    const script = String.raw`
$payload = json_decode(base64_decode('${encodePayload({ email, password, name: memberName })}'), true, 512, JSON_THROW_ON_ERROR);
$role = Spatie\Permission\Models\Role::findOrCreate('member', 'web');
$user = App\Models\User::factory()->create([
    'name' => $payload['name'],
    'email' => $payload['email'],
    'password' => Illuminate\Support\Facades\Hash::make($payload['password']),
    'email_verified_at' => now(),
]);
$user->assignRole($role);
echo 'ok';
`;

    tinker(script);
}

function removeMemberUser(email: string): void {
    const script = String.raw`
$user = App\Models\User::query()->where('email', '${email}')->first();
if ($user !== null) {
    Illuminate\Support\Facades\DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->where('model_type', App\Models\User::class)
        ->delete();
    $user->delete();
}
echo 'ok';
`;

    tinker(script);
}

function removeCurrentFixtures(): void {
    if (fixtureCreated) {
        removeFixtures();
        fixtureCreated = false;
    }

    if (memberCreated && memberEmail !== '') {
        removeMemberUser(memberEmail);
        memberCreated = false;
        memberEmail = '';
    }
}

test.describe('BE-P2-003 eligibility QR periode recruitment', () => {
    test.beforeAll((): void => {
        dispatchSkipReason = resolveDispatchSkipReason();
    });

    test.beforeEach(async ({ page }): Promise<void> => {
        prepareFixtureIdentity();
        fixtureCreated = true;
        serverErrors = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(`${response.status()} ${response.url()}`);
            }
        });
        // Skrip analitik pihak ketiga menggantungkan event load dan tidak relevan bagi test.
        await page.route('**/seeyu.doscom.org/**', (route) => route.abort());

        // Identitas baru per test/retry mengisolasi fixture dan limiter bucket.
        createFixtures();
        assertFixturesExist();
    });

    test.afterEach((): void => {
        removeCurrentFixtures();
    });

    test.afterAll((): void => {
        removeCurrentFixtures();
    });

    test('menampilkan count QR sesuai aturan eligibility fixture', async ({ page }) => {
        await openPeriod(page);

        const inertia = await readInertiaPage(page);
        expect(inertia.component, 'halaman periode harus merender Show, bukan Error').toBe(
            'Dashboard/Recruitment/Periods/Show'
        );
        expect(inertia.props.tab).toBe('peserta');
        expect(
            inertia.props.qr_eligible_count,
            'pool eligible = interview, tidak cancelled/absen, dan QR tidak sent/queued'
        ).toBe(ELIGIBLE_KEYS.length);
        expect(inertia.props.qr_sent_count, 'hanya satu applicant yang sudah terkirim').toBe(SENT_KEYS.length);
        expect(inertia.props.qr_status_summary).toEqual({ sent: 1, failed: 1, queued: 1 });

        // Count yang sama harus terlihat oleh user pada tombol dan chip progres.
        await expect(
            page.getByRole('button', { name: `Kirim QR ke ${ELIGIBLE_KEYS.length} applicant tahap interview` })
        ).toBeVisible();
        await expect(
            page.getByRole('status', { name: 'Progres kirim QR: 1 antrean, 1 terkirim, 1 gagal' })
        ).toBeVisible();

        // Endpoint JSON memakai agregat yang sama.
        const status = await readQrStatus(page);
        expect({ sent: status.sent, failed: status.failed, queued: status.queued }).toEqual({
            sent: 1,
            failed: 1,
            queued: 1,
        });
        expect(status.recipients, 'hanya tiga applicant punya log tipe QR').toHaveLength(3);

        expect(serverErrors, 'tidak boleh ada response >= 500').toEqual([]);
    });

    test('kirim QR tanpa include_sent hanya mengirim applicant eligible', async ({ page }) => {
        test.skip(dispatchSkipReason !== null, dispatchSkipReason ?? '');
        test.setTimeout(150_000);

        const before = countQrLogsByKey();
        await openPeriod(page);
        await confirmQrDialog(page);

        const expected: Record<string, number> = { ...before };
        for (const key of SEND_RECIPIENT_KEYS) {
            expected[key] = (before[key] ?? 0) + 1;
        }

        await waitForQrLogCounts(expected);

        // Applicant di luar pool tidak menerima baris baru.
        for (const key of [...EXCLUDED_KEYS, ...QR_IN_FLIGHT_KEYS, 'other-period']) {
            expect(countQrLogsByKey()[key] ?? 0, `${key} tidak boleh menerima QR baru`).toBe(before[key] ?? 0);
        }

        // Setiap penerima punya baris queued/sent (status job boleh berpindah selama test).
        for (const key of SEND_RECIPIENT_KEYS) {
            const statuses: string[] = qrLogsFor(key).map((row: IEmailLogRow): string => row.status);
            expect(
                statuses.some((status: string): boolean => status === 'queued' || status === 'sent'),
                `${key} harus punya baris queued/sent, dapat: ${statuses.join(',')}`
            ).toBe(true);
        }

        // Muat ulang halaman: eligible, sent, dan queued tetap menjadi partisi terpisah.
        await page.goto(`${periodPath}?tab=peserta`, { waitUntil: 'load' });
        const inertia = await readInertiaPage(page);
        const eligible = inertia.props.qr_eligible_count ?? 0;
        const sent = inertia.props.qr_sent_count ?? 0;

        const queued = inertia.props.qr_status_summary?.queued ?? 0;
        expect(
            eligible + sent + queued,
            'eligible + sent + queued harus mencakup seluruh applicant interview yang memenuhi dasar QR'
        ).toBe(interviewQrCandidateCount);
        expect(sent, 'applicant yang sudah terkirim tetap terhitung').toBeGreaterThanOrEqual(SENT_KEYS.length);

        const summary = inertia.props.qr_status_summary;
        if (summary === null || summary === undefined) {
            throw new Error('qr_status_summary harus ada pada tab peserta.');
        }
        expect(summary.sent + summary.failed + summary.queued, 'enam applicant punya log QR setelah kirim').toBe(
            interviewQrCandidateCount
        );

        const status = await readQrStatus(page);
        expect(status.recipients).toHaveLength(interviewQrCandidateCount);
        expect(serverErrors, 'tidak boleh ada response >= 500').toEqual([]);
    });

    test('kirim ulang include_sent menambah baris baru untuk applicant yang sudah terkirim', async ({ page }) => {
        test.skip(dispatchSkipReason !== null, dispatchSkipReason ?? '');
        test.setTimeout(150_000);

        const before = countQrLogsByKey();
        await openPeriod(page);
        await page
            .getByRole('button', {
                name: new RegExp(`^Kirim QR ke ${ELIGIBLE_KEYS.length} applicant tahap interview$`),
            })
            .click();

        const dialog = page.getByRole('alertdialog');
        await expect(dialog).toBeVisible();
        const checkbox = dialog.getByRole('checkbox');
        await expect(checkbox).toBeVisible();
        // Overlay dialog menutupi kontennya; Space pada checkbox terfokus = jalur user nyata.
        await checkbox.focus();
        await page.keyboard.press('Space');
        await expect(checkbox).toHaveAttribute('aria-checked', 'true');

        const confirm = dialog.getByRole('button', { name: 'Kirim', exact: true });
        await confirm.focus();
        await page.keyboard.press('Enter');
        await expect(dialog).toBeHidden({ timeout: 30_000 });

        const expected: Record<string, number> = { ...before };
        for (const key of RESEND_RECIPIENT_KEYS) {
            expected[key] = (before[key] ?? 0) + 1;
        }

        await waitForQrLogCounts(expected);

        for (const key of [...EXCLUDED_KEYS, ...QUEUED_KEYS, 'other-period']) {
            expect(countQrLogsByKey()[key] ?? 0, `${key} tidak boleh menerima QR baru`).toBe(before[key] ?? 0);
        }

        // Sent adalah riwayat sukses untuk affordance resend; summary adalah status terbaru.
        await page.goto(`${periodPath}?tab=peserta`, { waitUntil: 'load' });
        const inertia = await readInertiaPage(page);
        const eligible = inertia.props.qr_eligible_count ?? 0;
        const sent = inertia.props.qr_sent_count ?? 0;

        const summary = inertia.props.qr_status_summary;
        if (summary === null || summary === undefined) {
            throw new Error('qr_status_summary harus ada pada tab peserta.');
        }
        expect(eligible, 'setelah resend semua kandidat memiliki QR sent atau queued').toBe(0);
        expect(sent, 'delivery sebelumnya tetap tersedia untuk opsi resend').toBeGreaterThanOrEqual(SENT_KEYS.length);
        expect(summary.sent + summary.failed + summary.queued).toBe(interviewQrCandidateCount);
        expect(serverErrors, 'tidak boleh ada response >= 500').toEqual([]);
    });

    test('input anomali ditolak tanpa response 5xx', async ({ page }) => {
        test.setTimeout(180_000);

        await openPeriod(page);

        // a. tab asing dinormalkan server menjadi peserta, halaman tetap merender.
        const navigation = await page.goto(`${periodPath}?tab=bogus`, { waitUntil: 'load' });
        expect(navigation?.status() ?? 0).toBeLessThan(500);
        const bogus = await readInertiaPage(page);
        expect(bogus.component, 'tab asing tidak boleh merender halaman Error').not.toBe('Error');
        expect(bogus.props.tab, 'tab asing harus dinormalkan ke peserta').toBe('peserta');
        await expect(page.locator('[data-applicant-list]')).toBeVisible({ timeout: 30_000 });

        // b. Query liar: navigasi penuh harus redirect 302 dan parameter buruk dibuang.
        for (const anomaly of ANOMALY_QUERIES) {
            const url = `${periodPath}?tab=peserta&${anomaly.query}`;
            const plain = await page.request.get(url, { maxRedirects: 0, failOnStatusCode: false });

            expect(plain.status(), `${anomaly.label}: navigasi penuh harus redirect 302`).toBe(302);

            const location = plain.headers()['location'];
            expect(location, `${anomaly.label}: redirect harus menyertakan Location`).toBeTruthy();

            const redirected = new URL(location ?? url);
            expect(redirected.pathname, `${anomaly.label}: redirect harus ke halaman periode`).toBe(periodPath);
            expect(
                redirected.searchParams.has(anomaly.errorKey),
                `${anomaly.label}: parameter tidak valid harus dibuang dari target redirect`
            ).toBe(false);
        }

        // Jalur JSON: 422 dengan errors.<key>.
        const jsonGet = await page.request.get(`${periodPath}?tab=peserta&stage=bogus-value`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            maxRedirects: 0,
            failOnStatusCode: false,
        });
        expect(jsonGet.status(), 'query stage liar harus 422 untuk request JSON').toBe(422);
        const jsonGetBody = (await jsonGet.json()) as IValidationErrorBody;
        expect(Object.keys(jsonGetBody.errors ?? {}), 'body harus memuat errors.stage').toContain('stage');

        // c. POST send-qr dengan include_sent non-boolean harus ditolak validasi dan tidak mengirim apa pun.
        const before = countQrLogsByKey();
        const xsrf = await readXsrfToken(page.context());

        const htmlPost = await page.request.post(`${otherPeriodPath}/send-qr`, {
            form: { include_sent: 'bukan-boolean' },
            headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'text/html' },
            maxRedirects: 0,
            failOnStatusCode: false,
        });
        expect(htmlPost.status(), 'include_sent non-boolean harus redirect validasi 302').toBe(302);
        expect(htmlPost.headers()['location'], 'redirect validasi harus menyertakan Location').toBeTruthy();

        // Limiter produksi tetap 1 request/menit per user+periode; HTML dan JSON memakai
        // periode fixture berbeda agar bucket terisolasi tanpa membersihkan cache global.
        const jsonPost = await page.request.post(`${periodPath}/send-qr`, {
            form: { include_sent: 'bukan-boolean' },
            headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            maxRedirects: 0,
            failOnStatusCode: false,
        });
        const jsonPostText = await jsonPost.text();
        expect(
            jsonPost.status(),
            `include_sent non-boolean harus 422 untuk request JSON; body: ${jsonPostText.slice(0, 300)}`
        ).toBe(422);
        const jsonPostBody = JSON.parse(jsonPostText) as IValidationErrorBody;
        expect(Object.keys(jsonPostBody.errors ?? {}), 'body harus memuat errors.include_sent').toContain(
            'include_sent'
        );

        expect(countQrLogsByKey(), 'validasi gagal tidak boleh membuat baris email_logs').toEqual(before);

        // d. ID periode asing/tidak ada harus 404, bukan 500.
        const missing = await page.request.get(`/admin/recruitment/periods/${MISSING_PERIOD_ID}`, {
            maxRedirects: 0,
            failOnStatusCode: false,
        });
        expect(missing.status(), 'periode tidak ada harus 404').toBe(404);

        expect(serverErrors, 'tidak boleh ada response >= 500').toEqual([]);
    });

    test('user non-recruitment diarahkan keluar dari halaman periode', async ({ browser }) => {
        test.setTimeout(120_000);

        const baseURL = test.info().project.use.baseURL;
        if (baseURL === undefined || baseURL === '') {
            throw new Error('baseURL project tidak tersedia untuk context member.');
        }

        const email = memberEmail;
        const password = `E2e-${randomUUID()}`;
        memberCreated = true;
        createMemberUser(email, password);

        // Context manual tidak mewarisi opsi `use` project; baseURL eksplisit dan
        // storageState dikosongkan agar tidak memakai sesi admin dari project.
        const context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });

        try {
            // Token XSRF bisa belum sinkron pada request pertama; ambil ulang token lalu coba lagi.
            let loginStatus = 0;

            for (let attempt = 0; attempt < 3; attempt += 1) {
                await context.request.get('/auth/login', { failOnStatusCode: false });
                const xsrf = await readXsrfToken(context);
                const login = await context.request.post('/auth/login', {
                    form: { email, password },
                    headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'text/html' },
                    maxRedirects: 0,
                    failOnStatusCode: false,
                });

                loginStatus = login.status();

                if (loginStatus === 200 || loginStatus === 302) {
                    break;
                }
            }

            expect([200, 302], 'login user member fixture harus berhasil').toContain(loginStatus);

            // Request langsung: middleware recruitment.access harus mengalihkan, bukan 200/5xx.
            const direct = await context.request.get(periodPath, { maxRedirects: 0, failOnStatusCode: false });
            expect(direct.status(), 'user non-recruitment tidak boleh mendapat 200 di halaman periode').toBe(302);

            const location = direct.headers()['location'] ?? '';
            expect(location, 'redirect harus menyertakan Location').not.toBe('');
            expect(location, 'redirect tidak boleh kembali ke halaman periode').not.toContain(periodPath);
            expect(location, 'redirect harus menuju dashboard').toContain('/dashboard');
        } finally {
            await context.close();
            removeMemberUser(email);
            memberCreated = false;
            memberEmail = '';
        }
    });

    test('waktu navigasi periode fixture tercatat', async ({ page }) => {
        test.setTimeout(240_000);

        interface IMeasurement {
            elapsedMs: number;
            status: number;
        }

        const measure = async (label: string, path: string, timeout: number): Promise<IMeasurement> => {
            const started = performance.now();

            try {
                const response = await page.goto(path, { waitUntil: 'load', timeout });
                const status = response?.status() ?? 0;

                if (status < 400) {
                    await expect(page.locator('[data-applicant-list]')).toBeVisible({ timeout: 30_000 });
                }

                const elapsedMs = Math.round(performance.now() - started);
                console.log(`[BE-P2-003] ${label}: ${elapsedMs} ms (HTTP ${status})`);

                return { elapsedMs, status };
            } catch (error: unknown) {
                const message = error instanceof Error ? error.message.split('\n')[0] : String(error);
                const elapsedMs = Math.round(performance.now() - started);
                console.log(`[BE-P2-003] ${label}: ${elapsedMs} ms (navigasi gagal: ${message})`);

                return { elapsedMs, status: 0 };
            }
        };

        // Pemanasan agar aset/cache tidak didominasi cold start.
        await openPeriod(page);

        const fixture = await measure(
            `periode fixture (${PERIOD_APPLICATION_COUNT} applicant)`,
            `${periodPath}?tab=peserta`,
            FIXTURE_NAVIGATION_BUDGET_MS
        );
        expect(fixture.status, 'periode fixture harus merender normal').toBeLessThan(400);
        expect(fixture.elapsedMs, 'navigasi periode fixture harus di bawah batas generik').toBeLessThan(
            FIXTURE_NAVIGATION_BUDGET_MS
        );

        if (stressPeriodId !== null) {
            // Angka periode stress hanya bukti, bukan gate: ukuran dataset berbeda jauh.
            await measure(
                'periode stress (bukti, bukan gate)',
                `/admin/recruitment/periods/${stressPeriodId}?tab=peserta`,
                STRESS_NAVIGATION_TIMEOUT_MS
            );
        } else {
            console.log('[BE-P2-003] periode stress tidak tersedia; pengukuran pembanding dilewati.');
        }
    });
});
