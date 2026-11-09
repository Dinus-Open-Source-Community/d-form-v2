import type { Page, Response } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import { readEnvironment } from './support/environment';

/**
 * E2E BE-P2-007: tab laporan periode recruitment memakai satu query agregat
 * untuk `interview_stats`.
 *
 * Fixture membuat satu periode dengan 66 application: 64 punya interview
 * (61 waiting, 1 in_progress, 1 completed, 1 cancelled) dan 2 tanpa interview.
 * Periode kedua dengan 1 interview waiting wajib tidak ikut terhitung. Payload
 * Inertia cold-load dan pengulangan navigasi harus memberi angka identik tanpa
 * response >= 500; input anomali tetap 404/422/302, bukan 5xx.
 */

const FIXTURE_MARKER = 'E2E BE-P2-007';
const PERIOD_NAME = `${FIXTURE_MARKER} report query`;
const PERIOD_SLUG = 'e2e-be-p2-007';
const OTHER_PERIOD_NAME = `${FIXTURE_MARKER} periode lain`;
const OTHER_PERIOD_SLUG = 'e2e-be-p2-007-other';
const REGISTRATION_PREFIX = 'OPREC-E2E-P2007-';
const DIVISION_CODE = 'E2E-P2007';

const SESSION_DATE = '2026-05-10';
const LOCATION = 'Gedung A';
const ROOM = 'R-201';

type TInterviewStatus = 'waiting' | 'in_progress' | 'completed' | 'cancelled';

interface IStatusFixture {
    suffix: string;
    status: TInterviewStatus;
}

interface IFixtureRow {
    registration_number: string;
    full_name: string;
    nim: string;
    status: TInterviewStatus | null;
    period: 'main' | 'other';
}

/** Satu application per status interview pada periode utama. */
const STATUS_APPLICATIONS: IStatusFixture[] = [
    { suffix: '01', status: 'waiting' },
    { suffix: '02', status: 'in_progress' },
    { suffix: '03', status: 'completed' },
    { suffix: '04', status: 'cancelled' },
];

/** Application tanpa interview: tidak boleh menambah total. */
const NO_INTERVIEW_SUFFIXES: string[] = ['05', '06'];

/** 60 application tambahan dengan interview waiting agar tab memakai data nyata. */
const BULK_SUFFIXES: string[] = Array.from({ length: 60 }, (_, index: number): string => String(101 + index));

/** Periode lain: satu interview waiting yang wajib dikecualikan dari periode utama. */
const OTHER_PERIOD_SUFFIX = '901';

interface IInterviewStats {
    waiting: number;
    in_progress: number;
    completed: number;
    cancelled: number;
    total: number;
}

const EXPECTED_MAIN_STATS: IInterviewStats = {
    waiting: 61,
    in_progress: 1,
    completed: 1,
    cancelled: 1,
    total: 64,
};

const EXPECTED_OTHER_STATS: IInterviewStats = {
    waiting: 1,
    in_progress: 0,
    completed: 0,
    cancelled: 0,
    total: 1,
};

const EXPECTED_MAIN_APPLICATIONS = STATUS_APPLICATIONS.length + NO_INTERVIEW_SUFFIXES.length + BULK_SUFFIXES.length;
const EXPECTED_MAIN_INTERVIEWS = STATUS_APPLICATIONS.length + BULK_SUFFIXES.length;

const APPLICANT_CSV_HEADER: string[] = [
    'registration_number',
    'full_name',
    'nim',
    'semester',
    'primary_division',
    'secondary_division',
    'stage',
    'result',
    'submitted_at',
];

const NONEXISTENT_PERIOD_ID = '00000000-0000-4000-8000-000000000000';
const MALFORMED_PERIOD_ID = 'not-a-uuid';

interface IInertiaPagePayload {
    component: string;
    version: string;
    props: {
        report?: { interview_stats?: IInterviewStats } | null;
    };
}

interface IValidationErrorBody {
    errors?: Record<string, string[]>;
}

interface IFixtureProbe {
    main_period_id: string | null;
    other_period_id: string | null;
    main_applications: number;
    main_interviews: number;
    main_waiting: number;
    main_in_progress: number;
    main_completed: number;
    main_cancelled: number;
    other_applications: number;
    other_interviews: number;
    other_waiting: number;
}

let periodId = '';
let otherPeriodId = '';
let periodPath = '';
let otherPeriodPath = '';

/** Semua baris fixture, satu sumber kebenaran untuk create dan cleanup. */
const FIXTURE_ROWS: IFixtureRow[] = [
    ...STATUS_APPLICATIONS.map(
        (application: IStatusFixture): IFixtureRow => ({
            registration_number: `${REGISTRATION_PREFIX}${application.suffix}`,
            full_name: `${FIXTURE_MARKER} ${application.suffix}`,
            nim: `A11.2024.P2007${application.suffix}`,
            status: application.status,
            period: 'main',
        }),
    ),
    ...NO_INTERVIEW_SUFFIXES.map(
        (suffix: string): IFixtureRow => ({
            registration_number: `${REGISTRATION_PREFIX}${suffix}`,
            full_name: `${FIXTURE_MARKER} ${suffix}`,
            nim: `A11.2024.P2007${suffix}`,
            status: null,
            period: 'main',
        }),
    ),
    ...BULK_SUFFIXES.map(
        (suffix: string): IFixtureRow => ({
            registration_number: `${REGISTRATION_PREFIX}${suffix}`,
            full_name: `${FIXTURE_MARKER} ${suffix}`,
            nim: `A11.2024.P2007${suffix}`,
            status: 'waiting',
            period: 'main',
        }),
    ),
    {
        registration_number: `${REGISTRATION_PREFIX}${OTHER_PERIOD_SUFFIX}`,
        full_name: `${FIXTURE_MARKER} ${OTHER_PERIOD_SUFFIX}`,
        nim: `A11.2024.P2007${OTHER_PERIOD_SUFFIX}`,
        status: 'waiting',
        period: 'other',
    },
];

const REGISTRATION_NUMBERS: string[] = FIXTURE_ROWS.map((row: IFixtureRow): string => row.registration_number);

/** Buat kedua periode, divisi aktif, sesi, application, dan interview deterministik. */
function createFixtures(): void {
    const script = String.raw`
$rows = json_decode('${JSON.stringify(FIXTURE_ROWS)}', true, 512, JSON_THROW_ON_ERROR);
$division = App\Models\Recruitment\RecruitmentDivision::query()
    ->where('is_active', true)
    ->orderBy('code')
    ->first();
if ($division === null) {
    $division = App\Models\Recruitment\RecruitmentDivision::factory()->create([
        'name' => 'Divisi ${FIXTURE_MARKER}',
        'code' => '${DIVISION_CODE}',
        'is_active' => true,
    ]);
}
$periods = [
    'main' => App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
        'name' => '${PERIOD_NAME}',
        'slug' => '${PERIOD_SLUG}',
    ]),
    'other' => App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
        'name' => '${OTHER_PERIOD_NAME}',
        'slug' => '${OTHER_PERIOD_SLUG}',
    ]),
];
$sessions = [];
foreach ($periods as $key => $period) {
    $sessions[$key] = App\Models\Recruitment\RecruitmentInterviewSession::query()->create([
        'recruitment_period_id' => $period->id,
        'recruitment_division_id' => $division->id,
        'session_date' => '${SESSION_DATE}',
        'starts_at' => '09:00',
        'ends_at' => '10:30',
        'location' => '${LOCATION}',
        'room' => '${ROOM}',
        'is_active' => true,
    ]);
}
foreach ($rows as $row) {
    $application = App\Models\Recruitment\RecruitmentApplication::factory()->create([
        'recruitment_period_id' => $periods[$row['period']]->id,
        'registration_number' => $row['registration_number'],
        'full_name' => $row['full_name'],
        'nim' => $row['nim'],
        'primary_division_id' => $division->id,
        'stage' => 'interview',
        'result' => 'pending',
        'submitted_at' => now(),
    ]);
    if ($row['status'] === null) {
        continue;
    }
    App\Models\Recruitment\RecruitmentInterview::query()->create([
        'recruitment_application_id' => $application->id,
        'recruitment_interview_session_id' => $sessions[$row['period']]->id,
        'interview_kind' => App\Models\Recruitment\RecruitmentInterview::KIND_PRIMARY,
        'scheduled_at' => '${SESSION_DATE} 09:00:00',
        'location' => '${LOCATION}',
        'room' => '${ROOM}',
        'status' => $row['status'],
    ]);
}
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Tinker tidak memberi exit code non-zero saat exception; hasil create diverifikasi ulang. */
function assertFixturesExist(): void {
    const script = String.raw`
$main = App\Models\Recruitment\RecruitmentPeriod::query()->where('slug', '${PERIOD_SLUG}')->first();
$other = App\Models\Recruitment\RecruitmentPeriod::query()->where('slug', '${OTHER_PERIOD_SLUG}')->first();
if ($main === null || $other === null) {
    echo json_encode(['main_period_id' => null, 'other_period_id' => null]);
    exit(0);
}
$mainIds = App\Models\Recruitment\RecruitmentApplication::query()
    ->where('recruitment_period_id', $main->id)
    ->pluck('id');
$otherIds = App\Models\Recruitment\RecruitmentApplication::query()
    ->where('recruitment_period_id', $other->id)
    ->pluck('id');
$mainCounts = App\Models\Recruitment\RecruitmentInterview::query()
    ->whereIn('recruitment_application_id', $mainIds)
    ->selectRaw('status, COUNT(*) as total')
    ->groupBy('status')
    ->pluck('total', 'status');
$otherCounts = App\Models\Recruitment\RecruitmentInterview::query()
    ->whereIn('recruitment_application_id', $otherIds)
    ->selectRaw('status, COUNT(*) as total')
    ->groupBy('status')
    ->pluck('total', 'status');
echo json_encode([
    'main_period_id' => $main->id,
    'other_period_id' => $other->id,
    'main_applications' => $mainIds->count(),
    'main_interviews' => (int) $mainCounts->sum(),
    'main_waiting' => (int) ($mainCounts['waiting'] ?? 0),
    'main_in_progress' => (int) ($mainCounts['in_progress'] ?? 0),
    'main_completed' => (int) ($mainCounts['completed'] ?? 0),
    'main_cancelled' => (int) ($mainCounts['cancelled'] ?? 0),
    'other_applications' => $otherIds->count(),
    'other_interviews' => (int) $otherCounts->sum(),
    'other_waiting' => (int) ($otherCounts['waiting'] ?? 0),
]);
`;

    const probe = JSON.parse(runArtisanCommand(['tinker', '--execute', script])) as IFixtureProbe;

    const mismatches: string[] = [];

    if (probe.main_period_id === null || probe.other_period_id === null) {
        throw new Error('Fixture BE-P2-007 tidak mengembalikan period_id kedua periode.');
    }

    if (probe.main_applications !== EXPECTED_MAIN_APPLICATIONS) {
        mismatches.push(`main_applications=${probe.main_applications}, rencana=${EXPECTED_MAIN_APPLICATIONS}`);
    }

    if (probe.main_interviews !== EXPECTED_MAIN_INTERVIEWS) {
        mismatches.push(`main_interviews=${probe.main_interviews}, rencana=${EXPECTED_MAIN_INTERVIEWS}`);
    }

    if (
        probe.main_waiting !== EXPECTED_MAIN_STATS.waiting ||
        probe.main_in_progress !== EXPECTED_MAIN_STATS.in_progress ||
        probe.main_completed !== EXPECTED_MAIN_STATS.completed ||
        probe.main_cancelled !== EXPECTED_MAIN_STATS.cancelled
    ) {
        mismatches.push(
            `main status=${probe.main_waiting}/${probe.main_in_progress}/${probe.main_completed}/${probe.main_cancelled}`,
        );
    }

    if (probe.other_applications !== 1 || probe.other_interviews !== 1 || probe.other_waiting !== 1) {
        mismatches.push(
            `other=${probe.other_applications}/${probe.other_interviews}/${probe.other_waiting}`,
        );
    }

    if (mismatches.length > 0) {
        throw new Error(`Fixture BE-P2-007 tidak sesuai rencana: ${mismatches.join('; ')}`);
    }

    periodId = probe.main_period_id;
    otherPeriodId = probe.other_period_id;
    periodPath = `/admin/recruitment/periods/${periodId}`;
    otherPeriodPath = `/admin/recruitment/periods/${otherPeriodId}`;
}

/** Hapus interview, application, sesi, dan periode fixture; idempoten. */
function removeFixtures(): void {
    const script = String.raw`
$slugs = ['${PERIOD_SLUG}', '${OTHER_PERIOD_SLUG}'];
$periods = App\Models\Recruitment\RecruitmentPeriod::withTrashed()->whereIn('slug', $slugs)->get();
$periodIds = $periods->pluck('id')->all();
$applicationIds = App\Models\Recruitment\RecruitmentApplication::query()
    ->where(function ($query) use ($periodIds): void {
        $query->whereIn('recruitment_period_id', $periodIds)
            ->orWhere('registration_number', 'like', '${REGISTRATION_PREFIX}%');
    })
    ->pluck('id')
    ->all();
App\Models\Recruitment\RecruitmentEvaluation::query()->whereIn('recruitment_application_id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentAttendance::query()->whereIn('recruitment_application_id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentInterview::query()->whereIn('recruitment_application_id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentApplication::query()->whereIn('id', $applicationIds)->delete();
App\Models\Recruitment\RecruitmentInterviewSession::query()->whereIn('recruitment_period_id', $periodIds)->delete();
App\Models\Recruitment\RecruitmentActivityLog::query()->whereIn('recruitment_period_id', $periodIds)->delete();
foreach ($periods as $period) {
    $period->forceDelete();
}
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Payload Inertia awal ada di `script[data-page="app"]` (Inertia v2). */
async function readInertiaPage(page: Page): Promise<IInertiaPagePayload> {
    const raw = await page.locator('script[data-page="app"]').textContent();

    if (raw === null) {
        throw new Error('Payload Inertia tidak ditemukan pada script[data-page="app"].');
    }

    return JSON.parse(raw) as IInertiaPagePayload;
}

async function readInterviewStats(page: Page): Promise<IInterviewStats> {
    const payload = await readInertiaPage(page);
    const stats = payload.props.report?.interview_stats;

    if (stats === undefined) {
        throw new Error('Payload Inertia tab laporan tidak memuat report.interview_stats.');
    }

    return stats;
}

/** Parser CSV minimum; cukup untuk baris header tanpa sel ber-koma. */
function csvRows(content: string): string[][] {
    const withoutBom = content.startsWith('\uFEFF') ? content.slice(1) : content;

    return withoutBom
        .split(/\r?\n/)
        .filter((line: string): boolean => line !== '')
        .map((line: string): string[] => line.split(','));
}

test.describe('BE-P2-007 query agregat laporan recruitment', () => {
    test.beforeAll((): void => {
        removeFixtures();
        createFixtures();
        assertFixturesExist();
    });

    test.afterAll((): void => {
        removeFixtures();
    });

    test.beforeEach(async ({ page }): Promise<void> => {
        // Skrip analitik pihak ketiga menggantungkan event load dan tidak relevan bagi test.
        await page.route('**/seeyu.doscom.org/**', (route) => route.abort());
    });

    test('cold-load tab laporan memuat interview_stats tepat tanpa 5xx', async ({ page }) => {
        const serverErrors: number[] = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        const navigation = await page.goto(`${periodPath}?tab=laporan`, { waitUntil: 'load' });

        expect(navigation?.status() ?? 0).toBe(200);
        await expect(page.getByText('Laporan periode ini')).toBeVisible();
        await expect(page.getByText('Funnel recruitment')).toBeVisible();

        expect(await readInterviewStats(page)).toEqual(EXPECTED_MAIN_STATS);
        expect(serverErrors, 'tab laporan tidak boleh menghasilkan response >= 500').toEqual([]);
    });

    test('payload periode lain hanya menghitung interview periode itu', async ({ page }) => {
        const navigation = await page.goto(`${otherPeriodPath}?tab=laporan`, { waitUntil: 'load' });

        expect(navigation?.status() ?? 0).toBe(200);
        expect(await readInterviewStats(page)).toEqual(EXPECTED_OTHER_STATS);
    });

    test('input anomali tab dan export tetap aman, tanpa 5xx', async ({ page, browser }) => {
        test.setTimeout(60_000);

        const serverErrors: number[] = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        // Periode non-UUID: route model binding harus 404, bukan 500.
        const malformedPeriod = await page.goto('/admin/recruitment/periods/not-a-uuid?tab=laporan', {
            waitUntil: 'load',
        });
        expect(malformedPeriod?.status() ?? 0).toBe(404);

        // Export dengan period_id non-UUID ditolak validasi 422 lewat jalur JSON.
        for (const endpoint of ['funnel.csv', 'applicants.csv']) {
            const response = await page.request.get(
                `/admin/recruitment/reports/export/${endpoint}?period_id=${MALFORMED_PERIOD_ID}`,
                {
                    headers: { Accept: 'application/json' },
                    failOnStatusCode: false,
                    maxRedirects: 0,
                },
            );

            expect(response.status(), `export ${endpoint} period_id non-UUID harus 422`).toBe(422);

            const body = (await response.json()) as IValidationErrorBody;
            expect(Object.keys(body.errors ?? {}), `export ${endpoint} harus memuat errors.period_id`).toContain(
                'period_id',
            );
        }

        // UUID valid tetapi tidak ada: CSV header saja, tanpa 500 dan tanpa baris data.
        const missingPeriod = await page.request.get(
            `/admin/recruitment/reports/export/applicants.csv?period_id=${NONEXISTENT_PERIOD_ID}`,
            { failOnStatusCode: false, maxRedirects: 0 },
        );

        expect(missingPeriod.status()).toBe(200);
        expect(missingPeriod.headers()['content-type'] ?? '').toContain('text/csv');

        const missingRows = csvRows(await missingPeriod.text());
        expect(missingRows).toEqual([APPLICANT_CSV_HEADER]);

        // Tanpa sesi: middleware recruitment.access mengalihkan ke login, bukan 500.
        const environment = readEnvironment();
        const anonymousContext = await browser.newContext({
            baseURL: environment.baseUrl,
            storageState: { cookies: [], origins: [] },
        });

        try {
            const anonymous = await anonymousContext.request.get(
                '/admin/recruitment/reports/export/applicants.csv',
                { failOnStatusCode: false, maxRedirects: 0 },
            );

            expect(anonymous.status()).toBe(302);
            expect(anonymous.headers()['location'] ?? '').toContain('login');
        } finally {
            await anonymousContext.close();
        }

        expect(serverErrors, 'input anomali tidak boleh menghasilkan response >= 500').toEqual([]);
    });

    test('navigasi tab laporan berulang memberi interview_stats identik', async ({ page }) => {
        const serverErrors: number[] = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        const samples: IInterviewStats[] = [];

        for (let attempt = 0; attempt < 3; attempt += 1) {
            const navigation = await page.goto(`${periodPath}?tab=laporan`, { waitUntil: 'load' });

            expect(navigation?.status() ?? 0).toBe(200);
            samples.push(await readInterviewStats(page));
        }

        for (const sample of samples) {
            expect(sample).toEqual(EXPECTED_MAIN_STATS);
        }

        expect(serverErrors, 'navigasi berulang tidak boleh menghasilkan response >= 500').toEqual([]);
    });
});
