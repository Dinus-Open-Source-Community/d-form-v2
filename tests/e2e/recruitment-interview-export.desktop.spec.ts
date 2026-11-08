import { readFileSync } from 'node:fs';
import type { Download, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import { readEnvironment } from './support/environment';

/**
 * E2E BE-P2-002: export CSV interview per periode recruitment.
 *
 * Spec membuat periode fixture kecil (5 aplikasi, 6 interview) dengan angka yang
 * bisa diprediksi, lalu membuktikan unduhan nyata dari browser: BOM, 28 label
 * kolom berurutan, urutan baris `(registration_number, interview_kind, id)`,
 * label enum Indonesia, subset kolom, scope evaluated/pending, input anomali,
 * otorisasi, dan dua request paralel. Data stress (1 juta baris) tidak dipakai
 * untuk angka eksak dan tidak diukur waktunya (host sangat contended).
 */

/** Penanda unik fixture agar tidak bentrok dengan data stress atau spec lain. */
const FIXTURE_MARKER = 'E2E BE-P2-002';
const PERIOD_NAME = `${FIXTURE_MARKER} interview export`;
const PERIOD_SLUG = 'e2e-be-p2-002';

/** Interviewer fixture sekaligus aktor uji otorisasi (tanpa izin schedule). */
const INTERVIEWER_EMAIL = 'e2e-be-p2-002-interviewer@example.test';
const INTERVIEWER_NAME = `${FIXTURE_MARKER} interviewer`;

/** Divisi dari `RecruitmentDivisionSeeder`; dipakai ulang bila sudah ada. */
const DIVISION_CODE = 'programming';

const SESSION_DATE = '2026-05-10';
const SESSION_TIME = '09:00-10:30';
const LOCATION = 'Gedung A';
const ROOM = 'R-201';
const EVALUATED_AT = '2026-05-10 10:00:00';
const CHECKED_IN_AT = '2026-05-10 09:05:00';

/** Nama berbahaya: sel CSV wajib dinetralkan dengan apostrof di depan. */
const FORMULA_FULL_NAME = '=CMD|calc.exe';
const FORMULA_CELL = `'${FORMULA_FULL_NAME}`;

/** Urutan kolom kontrak `ExportInterviewReportRequest::COLUMN_KEYS`. */
const COLUMN_KEYS = [
    'registration_number',
    'full_name',
    'nim',
    'semester',
    'phone',
    'personal_email',
    'primary_division',
    'secondary_division',
    'interview_kind',
    'session_date',
    'session_time',
    'location',
    'room',
    'session_division',
    'interview_status',
    'checked_in_at',
    'attendance_method',
    'interviewer_name',
    'speaking_score',
    'technical_score',
    'attitude_score',
    'recommendation',
    'save_count',
    'evaluated_at',
    'application_stage',
    'application_result',
    'final_division',
    'membership_type',
] as const;

type TColumnKey = (typeof COLUMN_KEYS)[number];

/** Label Indonesia per kolom sesuai controller; urutan diambil dari `COLUMN_KEYS`. */
const COLUMN_LABELS: Record<TColumnKey, string> = {
    registration_number: 'Nomor Pendaftaran',
    full_name: 'Nama Lengkap',
    nim: 'NIM',
    semester: 'Semester',
    phone: 'No. Telepon',
    personal_email: 'Email Pribadi',
    primary_division: 'Divisi Primer',
    secondary_division: 'Divisi Sekunder',
    interview_kind: 'Jenis Interview',
    session_date: 'Tanggal Sesi',
    session_time: 'Jam Sesi',
    location: 'Lokasi',
    room: 'Ruang',
    session_division: 'Divisi Sesi',
    interview_status: 'Status Interview',
    checked_in_at: 'Waktu Check-in',
    attendance_method: 'Metode Check-in',
    interviewer_name: 'Nama Interviewer',
    speaking_score: 'Nilai Speaking',
    technical_score: 'Nilai Teknis',
    attitude_score: 'Nilai Sikap',
    recommendation: 'Rekomendasi',
    save_count: 'Jumlah Simpan',
    evaluated_at: 'Waktu Dinilai',
    application_stage: 'Tahap Pendaftaran',
    application_result: 'Hasil Pendaftaran',
    final_division: 'Divisi Final',
    membership_type: 'Tipe Keanggotaan',
};

const EXPECTED_HEADER: string[] = COLUMN_KEYS.map((key: TColumnKey): string => COLUMN_LABELS[key]);

const KIND_LABELS: Record<'primary' | 'secondary', string> = {
    primary: 'Primer',
    secondary: 'Sekunder',
};

const STATUS_LABELS: Record<'waiting' | 'completed', string> = {
    waiting: 'Menunggu interview',
    completed: 'Selesai',
};

interface IPlanApplication {
    suffix: string;
    fullName: string;
    hasSecondaryDivision: boolean;
    hasAttendance: boolean;
    primary: { status: 'waiting' | 'completed'; evaluated: boolean };
    secondary: { status: 'waiting'; evaluated: boolean } | null;
}

interface IPlanInterview {
    registrationNumber: string;
    fullName: string;
    nim: string;
    phone: string;
    email: string;
    hasSecondaryDivision: boolean;
    hasAttendance: boolean;
    kind: 'primary' | 'secondary';
    status: 'waiting' | 'completed';
    evaluated: boolean;
}

/** Satu sumber kebenaran: fixture yang dibuat dan baris CSV yang di-assert. */
const APPLICATIONS: IPlanApplication[] = [
    {
        suffix: '01',
        fullName: `${FIXTURE_MARKER} Satu`,
        hasSecondaryDivision: false,
        hasAttendance: false,
        primary: { status: 'waiting', evaluated: true },
        secondary: null,
    },
    {
        suffix: '02',
        fullName: `${FIXTURE_MARKER} Dua`,
        hasSecondaryDivision: true,
        hasAttendance: false,
        primary: { status: 'waiting', evaluated: true },
        secondary: { status: 'waiting', evaluated: false },
    },
    {
        suffix: '03',
        fullName: `${FIXTURE_MARKER} Tiga`,
        hasSecondaryDivision: false,
        hasAttendance: false,
        primary: { status: 'waiting', evaluated: false },
        secondary: null,
    },
    {
        suffix: '04',
        fullName: `${FIXTURE_MARKER} Empat`,
        hasSecondaryDivision: false,
        hasAttendance: true,
        primary: { status: 'completed', evaluated: true },
        secondary: null,
    },
    {
        suffix: '05',
        fullName: FORMULA_FULL_NAME,
        hasSecondaryDivision: false,
        hasAttendance: false,
        primary: { status: 'waiting', evaluated: false },
        secondary: null,
    },
];

/** Urutan CSV = `registration_number`, lalu `interview_kind`, lalu `id`. */
const PLAN: IPlanInterview[] = APPLICATIONS.flatMap((application: IPlanApplication): IPlanInterview[] => {
    const base = {
        registrationNumber: `OPREC-E2E-P2002-${application.suffix}`,
        fullName: application.fullName,
        nim: `A11.2024.3200${application.suffix}`,
        phone: `08120000${application.suffix}`,
        email: `e2e-be-p2-002-${application.suffix}@example.test`,
        hasSecondaryDivision: application.hasSecondaryDivision,
        hasAttendance: application.hasAttendance,
    };

    const interviews: IPlanInterview[] = [
        { ...base, kind: 'primary', status: application.primary.status, evaluated: application.primary.evaluated },
    ];

    if (application.secondary !== null) {
        interviews.push({
            ...base,
            kind: 'secondary',
            status: application.secondary.status,
            evaluated: application.secondary.evaluated,
        });
    }

    return interviews;
});

const REGISTRATION_NUMBERS: string[] = APPLICATIONS.map(
    (application: IPlanApplication): string => `OPREC-E2E-P2002-${application.suffix}`,
);

interface IAnomalyInput {
    label: string;
    query: string;
    errorKey: string;
}

/** Setiap nilai liar wajib ditolak validasi, bukan menghasilkan CSV. */
const ANOMALY_INPUTS: IAnomalyInput[] = [
    { label: 'scope asing', query: 'scope=bogus', errorKey: 'scope' },
    { label: 'kolom password', query: 'columns[0]=password', errorKey: 'columns.0' },
    {
        label: 'kolom script',
        query: `columns[0]=${encodeURIComponent('<script>alert(1)</script>')}`,
        errorKey: 'columns.0',
    },
    { label: 'include_secondary bukan boolean', query: 'include_secondary=notabool', errorKey: 'include_secondary' },
];

/** ID periode dan nama divisi nyata, diisi `beforeAll` dari hasil probe fixture. */
let periodId = '';
let divisionName = '';

interface IExportQuery {
    scope?: 'all' | 'evaluated' | 'pending';
    includeSecondary?: boolean;
    columns?: string[];
}

interface IProbeResult {
    period_id: string;
    division_name: string;
    applications: number;
    interviews: number;
    evaluated: number;
    pending: number;
    secondary: number;
    interviewer_role: string;
}

interface ICsvDownload {
    filename: string;
    raw: string;
    rows: string[][];
}

/** Cermin aturan `CsvCellSanitizer` untuk satu sel yang diuji (formula injection). */
function csvCellValue(value: string): string {
    if (value === FORMULA_FULL_NAME) {
        return FORMULA_CELL;
    }

    return value;
}

/** Baris CSV yang diharapkan untuk satu interview, sesuai urutan `COLUMN_KEYS`. */
function expectedCells(interview: IPlanInterview, division: string): string[] {
    const kind = KIND_LABELS[interview.kind];
    const scores = interview.evaluated
        ? {
              speaking_score: '80',
              technical_score: '75',
              attitude_score: '90',
              recommendation: 'Direkomendasikan',
              save_count: '1',
              evaluated_at: EVALUATED_AT,
          }
        : {
              speaking_score: '',
              technical_score: '',
              attitude_score: '',
              recommendation: '',
              save_count: '',
              evaluated_at: '',
          };

    const values: Record<TColumnKey, string> = {
        registration_number: interview.registrationNumber,
        full_name: csvCellValue(interview.fullName),
        nim: interview.nim,
        semester: '3',
        phone: interview.phone,
        personal_email: interview.email,
        primary_division: division,
        secondary_division: interview.hasSecondaryDivision ? division : '',
        interview_kind: kind,
        session_date: SESSION_DATE,
        session_time: SESSION_TIME,
        location: LOCATION,
        room: ROOM,
        session_division: division,
        interview_status: STATUS_LABELS[interview.status],
        checked_in_at: interview.hasAttendance ? CHECKED_IN_AT : '',
        attendance_method: interview.hasAttendance ? 'QR Code' : '',
        interviewer_name: INTERVIEWER_NAME,
        ...scores,
        application_stage: 'Interview',
        application_result: 'Menunggu keputusan',
        final_division: '',
        membership_type: '',
    };

    return COLUMN_KEYS.map((key: TColumnKey): string => values[key]);
}

function expectedRows(predicate: (interview: IPlanInterview) => boolean = (): boolean => true): string[][] {
    return PLAN.filter(predicate).map((interview: IPlanInterview): string[] => expectedCells(interview, divisionName));
}

/** Periode + divisi + sesi + 5 aplikasi + interview/evaluasi/attendance + interviewer. */
function createFixtures(): void {
    const env = readEnvironment();
    // Password user fixture tidak pernah ditulis ke repo: diambil dari environment run
    // dalam bentuk base64 agar karakter kutip tidak merusak skrip tinker.
    const passwordBase64 = Buffer.from(env.adminPassword, 'utf8').toString('base64');

    const applications = APPLICATIONS.map((application: IPlanApplication) => ({
        registration_number: `OPREC-E2E-P2002-${application.suffix}`,
        full_name: application.fullName,
        nim: `A11.2024.3200${application.suffix}`,
        phone: `08120000${application.suffix}`,
        email: `e2e-be-p2-002-${application.suffix}@example.test`,
        secondary_division: application.hasSecondaryDivision,
        attendance: application.hasAttendance,
        interviews: [
            { kind: 'primary', status: application.primary.status, evaluated: application.primary.evaluated },
            ...(application.secondary === null
                ? []
                : [{ kind: 'secondary', status: application.secondary.status, evaluated: application.secondary.evaluated }]),
        ],
    }));

    const script = String.raw`
$marker = '${FIXTURE_MARKER}';
$role = Spatie\Permission\Models\Role::findOrCreate('recruitment-interviewer', 'web');
$interviewer = App\Models\User::withTrashed()->where('email', '${INTERVIEWER_EMAIL}')->first();
if ($interviewer !== null) {
    $interviewer->restore();
}
if ($interviewer === null) {
    $interviewer = App\Models\User::query()->create([
        'name' => '${INTERVIEWER_NAME}',
        'email' => '${INTERVIEWER_EMAIL}',
        'password' => Illuminate\Support\Facades\Hash::make(base64_decode('${passwordBase64}')),
        'email_verified_at' => now(),
    ]);
}
$interviewer->syncRoles([$role]);

$period = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${PERIOD_NAME}',
    'slug' => '${PERIOD_SLUG}',
]);
$division = App\Models\Recruitment\RecruitmentDivision::query()->where('code', '${DIVISION_CODE}')->first();
if ($division === null) {
    $division = App\Models\Recruitment\RecruitmentDivision::factory()->create([
        'code' => '${DIVISION_CODE}',
        'name' => 'Divisi '.$marker,
        'is_active' => true,
    ]);
}
$session = App\Models\Recruitment\RecruitmentInterviewSession::query()->create([
    'recruitment_period_id' => $period->id,
    'recruitment_division_id' => $division->id,
    'session_date' => '${SESSION_DATE}',
    'starts_at' => '09:00',
    'ends_at' => '10:30',
    'location' => '${LOCATION}',
    'room' => '${ROOM}',
    'is_active' => true,
]);

$applications = json_decode('${JSON.stringify(applications)}', true, 512, JSON_THROW_ON_ERROR);
foreach ($applications as $row) {
    $application = App\Models\Recruitment\RecruitmentApplication::factory()
        ->for($period, 'period')
        ->create([
            'registration_number' => $row['registration_number'],
            'full_name' => $row['full_name'],
            'nim' => $row['nim'],
            'semester' => 3,
            'phone' => $row['phone'],
            'personal_email' => $row['email'],
            'primary_division_id' => $division->id,
            'secondary_division_id' => $row['secondary_division'] ? $division->id : null,
            'stage' => 'interview',
            'result' => 'pending',
            'submitted_at' => now(),
        ]);

    foreach ($row['interviews'] as $interviewRow) {
        $interview = App\Models\Recruitment\RecruitmentInterview::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'interview_kind' => $interviewRow['kind'],
            'interviewer_id' => $interviewer->id,
            'scheduled_at' => '${SESSION_DATE} 09:00:00',
            'location' => '${LOCATION}',
            'room' => '${ROOM}',
            'status' => $interviewRow['status'],
        ]);

        if ($interviewRow['evaluated']) {
            App\Models\Recruitment\RecruitmentEvaluation::query()->create([
                'recruitment_application_id' => $application->id,
                'recruitment_interview_id' => $interview->id,
                'speaking_score' => 80,
                'technical_score' => 75,
                'attitude_score' => 90,
                'recommendation' => 'recommended',
                'save_count' => 1,
                'evaluated_by' => $interviewer->id,
                'evaluated_at' => '${EVALUATED_AT}',
            ]);
        }
    }

    if ($row['attendance']) {
        App\Models\Recruitment\RecruitmentAttendance::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_interview_session_id' => $session->id,
            'method' => 'qr',
            'checked_in_at' => '${CHECKED_IN_AT}',
            'checked_in_by' => $interviewer->id,
        ]);
    }
}

echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Tinker tidak memberi exit code non-zero saat exception; hasil create diverifikasi ulang. */
function probeFixtures(): void {
    const script = String.raw`
$period = App\Models\Recruitment\RecruitmentPeriod::query()->where('name', '${PERIOD_NAME}')->first();
if ($period === null) {
    echo json_encode(['applications' => 0]);
    exit(0);
}
$query = App\Models\Recruitment\RecruitmentInterview::query()
    ->whereHas('application', fn ($builder) => $builder->where('recruitment_period_id', $period->id));
$interviewer = App\Models\User::withTrashed()->where('email', '${INTERVIEWER_EMAIL}')->first();
echo json_encode([
    'period_id' => $period->id,
    'division_name' => App\Models\Recruitment\RecruitmentDivision::query()->where('code', '${DIVISION_CODE}')->value('name'),
    'applications' => App\Models\Recruitment\RecruitmentApplication::query()
        ->where('recruitment_period_id', $period->id)
        ->count(),
    'interviews' => (clone $query)->count(),
    'evaluated' => (clone $query)->whereHas('evaluation')->count(),
    'pending' => (clone $query)->whereDoesntHave('evaluation')->count(),
    'secondary' => (clone $query)->where('interview_kind', 'secondary')->count(),
    'interviewer_role' => $interviewer?->getRoleNames()->implode(',') ?? '',
]);
`;

    const output = runArtisanCommand(['tinker', '--execute', script]);
    const probe = JSON.parse(output) as Partial<IProbeResult>;

    const expectedEvaluated = PLAN.filter((interview: IPlanInterview): boolean => interview.evaluated).length;

    if (
        probe.applications !== APPLICATIONS.length ||
        probe.interviews !== PLAN.length ||
        probe.evaluated !== expectedEvaluated ||
        probe.pending !== PLAN.length - expectedEvaluated ||
        probe.secondary !== 1
    ) {
        throw new Error(`Fixture BE-P2-002 tidak sesuai rencana: ${output}`);
    }

    if (probe.period_id === undefined || probe.division_name === null || probe.division_name === undefined) {
        throw new Error(`Fixture BE-P2-002 tidak lengkap: ${output}`);
    }

    if (probe.interviewer_role !== 'recruitment-interviewer') {
        throw new Error(`User interviewer fixture tidak memegang role recruitment-interviewer: ${output}`);
    }

    periodId = probe.period_id;
    divisionName = probe.division_name;
}

/** Hapus seluruh baris fixture beserta user interviewer; idempoten. */
function removeFixtures(): void {
    const script = String.raw`
$ids = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', ${JSON.stringify(REGISTRATION_NUMBERS)})
    ->pluck('id');
App\Models\Recruitment\RecruitmentEvaluation::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentAttendance::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentInterview::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentApplication::query()->whereIn('id', $ids)->delete();
$periods = App\Models\Recruitment\RecruitmentPeriod::withTrashed()->where('slug', '${PERIOD_SLUG}')->get();
foreach ($periods as $period) {
    App\Models\Recruitment\RecruitmentInterviewSession::query()->where('recruitment_period_id', $period->id)->delete();
    App\Models\Recruitment\RecruitmentActivityLog::query()->where('recruitment_period_id', $period->id)->delete();
    $period->forceDelete();
}
$interviewer = App\Models\User::withTrashed()->where('email', '${INTERVIEWER_EMAIL}')->first();
if ($interviewer !== null) {
    $interviewer->syncRoles([]);
    $interviewer->forceDelete();
}
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

function exportPath(query: IExportQuery = {}): string {
    const path = `/admin/recruitment/periods/${periodId}/interviews/export.csv`;
    const params = new URLSearchParams();

    if (query.scope !== undefined) {
        params.set('scope', query.scope);
    }

    if (query.includeSecondary !== undefined) {
        params.set('include_secondary', query.includeSecondary ? '1' : '0');
    }

    for (const column of query.columns ?? []) {
        params.append('columns[]', column);
    }

    const search = params.toString();

    return search === '' ? path : `${path}?${search}`;
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

/** Unduh lewat browser (bukan API request) memakai anchor, tanpa meninggalkan halaman. */
async function downloadCsv(page: Page, path: string): Promise<ICsvDownload> {
    const downloadPromise = page.waitForEvent('download');

    await page.evaluate((target: string): void => {
        const anchor = document.createElement('a');
        anchor.href = target;
        anchor.rel = 'noopener';
        anchor.style.display = 'none';
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
    }, path);

    return collectDownload(await downloadPromise);
}

/** Buka tab interview periode fixture dan tunggu tombol export siap. */
async function openInterviewsTab(page: Page): Promise<void> {
    await page.goto(`/admin/recruitment/periods/${periodId}?tab=interview`, { waitUntil: 'load' });
    await expect(page.getByRole('button', { name: 'Export CSV' }).first()).toBeVisible({ timeout: 30_000 });
}

/** Klik tombol export di toolbar + dialog, lalu tangkap unduhan nyata browser. */
async function exportFromDialog(page: Page): Promise<ICsvDownload> {
    await page.getByRole('button', { name: 'Export CSV' }).first().click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    const downloadPromise = page.waitForEvent('download');
    await dialog.getByRole('button', { name: 'Export CSV' }).click();

    return collectDownload(await downloadPromise);
}

test.describe('BE-P2-002 export CSV interview periode recruitment', () => {
    test.beforeAll((): void => {
        removeFixtures();
        createFixtures();
        probeFixtures();
    });

    test.afterAll((): void => {
        removeFixtures();
    });

    test.beforeEach(async ({ page }): Promise<void> => {
        // Skrip analitik pihak ketiga menggantungkan event load dan tidak relevan bagi test.
        await page.route('**/seeyu.doscom.org/**', (route) => route.abort());
    });

    test('happy path: unduhan memuat 28 label kolom dan baris interview berurutan', async ({ page }) => {
        test.setTimeout(120_000);
        await openInterviewsTab(page);

        const csv = await exportFromDialog(page);
        expect(csv.filename).toMatch(new RegExp(`^interview-${PERIOD_SLUG}-\\d{8}\\.csv$`));
        // BOM UTF-8 wajib ada agar Excel membaca karakter Indonesia dengan benar.
        expect(csv.raw.charCodeAt(0)).toBe(0xfeff);

        expect(csv.rows[0]).toEqual(EXPECTED_HEADER);
        expect(csv.rows[0]).toHaveLength(28);

        const dataRows = csv.rows.slice(1);
        expect(dataRows).toHaveLength(PLAN.length);
        expect(dataRows).toEqual(expectedRows());

        const kindIndex = COLUMN_KEYS.indexOf('interview_kind');
        expect(dataRows.map((row: string[]): string => row[kindIndex] ?? '')).toEqual([
            'Primer',
            'Primer',
            'Sekunder',
            'Primer',
            'Primer',
            'Primer',
        ]);

        const fullNameIndex = COLUMN_KEYS.indexOf('full_name');
        const formulaRow = dataRows.find(
            (row: string[]): boolean => row[fullNameIndex] === FORMULA_CELL,
        );
        expect(formulaRow, 'nama berformula wajib ada dan diawali apostrof').toBeTruthy();
        expect(csv.raw).toContain(FORMULA_CELL);
        expect(csv.raw).not.toContain(`,${FORMULA_FULL_NAME},`);
    });

    test('subset columns[] dipertahankan sesuai urutan permintaan', async ({ page }) => {
        test.setTimeout(120_000);
        await openInterviewsTab(page);

        const requested: string[] = ['interview_kind', 'nim', 'full_name'];
        const csv = await downloadCsv(page, exportPath({ columns: requested }));

        expect(csv.rows[0]).toEqual(['Jenis Interview', 'NIM', 'Nama Lengkap']);

        const expected = expectedRows().map((row: string[]): string[] =>
            requested.map((key: string): string => {
                const index = COLUMN_KEYS.indexOf(key as TColumnKey);

                return index === -1 ? '' : (row[index] ?? '');
            }),
        );
        expect(csv.rows.slice(1)).toEqual(expected);
        expect(csv.rows.every((row: string[]): boolean => row.length === requested.length)).toBe(true);
    });

    test('scope evaluated dan pending memisahkan baris tanpa irisan', async ({ page }) => {
        test.setTimeout(120_000);
        await openInterviewsTab(page);

        const evaluated = await downloadCsv(page, exportPath({ scope: 'evaluated' }));
        const pending = await downloadCsv(page, exportPath({ scope: 'pending' }));

        const evaluatedRows = evaluated.rows.slice(1);
        const pendingRows = pending.rows.slice(1);

        expect(evaluatedRows).toEqual(expectedRows((interview: IPlanInterview): boolean => interview.evaluated));
        expect(pendingRows).toEqual(expectedRows((interview: IPlanInterview): boolean => !interview.evaluated));
        expect(evaluatedRows.length + pendingRows.length).toBe(PLAN.length);

        const rowKey = (row: string[]): string => `${row[0] ?? ''}|${row[COLUMN_KEYS.indexOf('interview_kind')] ?? ''}`;
        const evaluatedKeys = new Set<string>(evaluatedRows.map(rowKey));
        const duplicated = pendingRows.map(rowKey).filter((key: string): boolean => evaluatedKeys.has(key));
        expect(duplicated, 'scope evaluated dan pending tidak boleh beririsan').toEqual([]);
    });

    test('input anomali ditolak validasi tanpa response CSV', async ({ page }) => {
        test.setTimeout(120_000);
        await openInterviewsTab(page);

        for (const anomaly of ANOMALY_INPUTS) {
            const url = `${exportPath()}?${anomaly.query}`;

            // Navigasi penuh: validasi gagal = redirect balik, bukan unduhan.
            const plain = await page.request.get(url, { failOnStatusCode: false, maxRedirects: 0 });
            expect(plain.status(), `${anomaly.label}: harus redirect validasi 302`).toBe(302);
            expect(plain.headers()['content-disposition'], `${anomaly.label}: tidak boleh ada unduhan`).toBeUndefined();
            expect(stripBom(await plain.text()).startsWith('Nomor Pendaftaran')).toBe(false);

            // Jalur JSON: 422 + errors.<key> agar pesan validasi terbaca klien.
            const json = await page.request.get(url, {
                headers: { Accept: 'application/json' },
                failOnStatusCode: false,
                maxRedirects: 0,
            });
            expect(json.status(), `${anomaly.label}: request JSON harus 422`).toBe(422);

            const body = (await json.json()) as { errors?: Record<string, string[]> };
            expect(
                Object.keys(body.errors ?? {}),
                `${anomaly.label}: body harus memuat errors.${anomaly.errorKey}`,
            ).toContain(anomaly.errorKey);
        }

        // Nilai sah tetap menghasilkan CSV: anomali tidak merusak endpoint.
        const valid = await page.request.get(exportPath({ scope: 'all' }), { failOnStatusCode: false });
        expect(valid.status()).toBe(200);
        expect(valid.headers()['content-type']).toContain('text/csv');
    });

    test('pengguna tanpa izin recruitment.interviews.schedule mendapat 403', async ({ browser }): Promise<void> => {
        test.setTimeout(120_000);

        const environment = readEnvironment();
        const context = await browser.newContext({
            baseURL: environment.baseUrl,
            storageState: { cookies: [], origins: [] },
        });

        try {
            const page = await context.newPage();
            await page.goto('/auth/login');
            await page.locator('#login-email').fill(INTERVIEWER_EMAIL);
            await page.locator('#login-password').fill(environment.adminPassword);
            await page
                .locator('form')
                .filter({ has: page.locator('#login-email') })
                .getByRole('button', { name: /masuk/i })
                .click();
            await expect(page).not.toHaveURL(/\/auth\/login/);

            const response = await context.request.get(exportPath(), { failOnStatusCode: false });
            expect(response.status()).toBe(403);
            expect(response.headers()['content-disposition']).toBeUndefined();
        } finally {
            await context.close();
        }
    });

    test('dua export paralel menghasilkan jumlah baris identik', async ({ page }) => {
        test.setTimeout(120_000);
        await openInterviewsTab(page);

        const url = exportPath({ scope: 'all' });
        const [first, second] = await Promise.all([
            page.request.get(url, { failOnStatusCode: false }),
            page.request.get(url, { failOnStatusCode: false }),
        ]);

        expect(first.status()).toBe(200);
        expect(second.status()).toBe(200);

        const firstRows = parseCsvRows(stripBom(await first.text()));
        const secondRows = parseCsvRows(stripBom(await second.text()));

        expect(firstRows).toHaveLength(PLAN.length + 1);
        expect(secondRows).toHaveLength(firstRows.length);
        expect(secondRows).toEqual(firstRows);
    });

    test('unduhan default menyertakan interview secondary dan bisa dikecualikan', async ({ page }) => {
        test.setTimeout(120_000);
        await openInterviewsTab(page);

        const withSecondary = await downloadCsv(page, exportPath({ includeSecondary: true }));
        expect(withSecondary.rows.slice(1)).toEqual(expectedRows());
        expect(withSecondary.rows.slice(1)).toHaveLength(PLAN.length);

        const primaryOnly = await downloadCsv(page, exportPath({ includeSecondary: false }));
        expect(primaryOnly.rows.slice(1)).toEqual(
            expectedRows((interview: IPlanInterview): boolean => interview.kind === 'primary'),
        );
        expect(primaryOnly.rows.slice(1)).toHaveLength(APPLICATIONS.length);
    });
});
