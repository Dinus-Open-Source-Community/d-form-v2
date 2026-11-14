import type { Locator, Page, Response } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand, runArtisanJson } from './support/artisan';

/**
 * E2E BE-P2-008: badge jumlah antrean pada halaman periode recruitment.
 *
 * Spec membuat dua periode fixture. Periode utama berisi 7 aplikasi yang
 * sengaja dipilih agar setiap badge adalah angka berbeda dan tidak saling
 * tumpang tindih; periode kedua berisi 1 aplikasi untuk membuktikan
 * "Semua antrean" memakai scoping periode dan untuk melihat badge bernilai
 * nol tanpa angka dalam kurung.
 *
 * Aturan antrean otoritatif ada di `RecruitmentApplicationService::QUEUE_RULES`
 * (dipakai bersama oleh `queueCounts()` dan `applyQueueFilter()`).
 */

/** Penanda unik fixture agar tidak bentrok dengan data stress atau spec lain. */
const FIXTURE_MARKER = 'E2E BE-P2-008';
const PERIOD_NAME = `${FIXTURE_MARKER} queue counts`;
const PERIOD_SLUG = 'e2e-be-p2-008';
const SCOPING_PERIOD_NAME = `${FIXTURE_MARKER} scoping`;
const SCOPING_PERIOD_SLUG = 'e2e-be-p2-008-scoping';

type Stage = 'submitted' | 'screening' | 'interview' | 'final_review' | 'completed';
type Result = 'pending' | 'accepted' | 'rejected';

interface IApplicationFixture {
    registrationNumber: string;
    name: string;
    nim: string;
    stage: Stage;
    result: Result;
    revisionRequired: boolean;
}

interface IQueueCase {
    key: string;
    option: RegExp;
    rows: number;
}

interface IAnomalyInput {
    label: string;
    query: string;
    errorKey: string;
}

interface IValidationErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
}

/** Payload Inertia awal ada di `script[data-page="app"]` (Inertia v2). */
interface IInertiaPagePayload {
    component: string;
    version: string;
}

/**
 * Tujuh aplikasi periode utama. Angka yang diharapkan:
 * Semua antrean 7, Screening 2, Revisi 1, Interview 1, Final 1, Selesai 1.
 * Baris `screening + rejected` tidak masuk antrean mana pun.
 */
const MAIN_APPLICATIONS: IApplicationFixture[] = [
    {
        registrationNumber: 'OPREC-E2E-P2008-01',
        name: `${FIXTURE_MARKER} Satu`,
        nim: 'A11.2024.30001',
        stage: 'submitted',
        result: 'pending',
        revisionRequired: false,
    },
    {
        registrationNumber: 'OPREC-E2E-P2008-02',
        name: `${FIXTURE_MARKER} Dua`,
        nim: 'A11.2024.30002',
        stage: 'screening',
        result: 'pending',
        revisionRequired: false,
    },
    {
        registrationNumber: 'OPREC-E2E-P2008-03',
        name: `${FIXTURE_MARKER} Tiga`,
        nim: 'A11.2024.30003',
        stage: 'screening',
        result: 'pending',
        revisionRequired: true,
    },
    {
        registrationNumber: 'OPREC-E2E-P2008-04',
        name: `${FIXTURE_MARKER} Empat`,
        nim: 'A11.2024.30004',
        stage: 'screening',
        result: 'rejected',
        revisionRequired: false,
    },
    {
        registrationNumber: 'OPREC-E2E-P2008-05',
        name: `${FIXTURE_MARKER} Lima`,
        nim: 'A11.2024.30005',
        stage: 'interview',
        result: 'pending',
        revisionRequired: false,
    },
    {
        registrationNumber: 'OPREC-E2E-P2008-06',
        name: `${FIXTURE_MARKER} Enam`,
        nim: 'A11.2024.30006',
        stage: 'final_review',
        result: 'pending',
        revisionRequired: false,
    },
    {
        registrationNumber: 'OPREC-E2E-P2008-07',
        name: `${FIXTURE_MARKER} Tujuh`,
        nim: 'A11.2024.30007',
        stage: 'completed',
        result: 'accepted',
        revisionRequired: false,
    },
];

/** Satu aplikasi di periode berbeda; harus tetap dihitung `Screening` di periodenya sendiri. */
const SCOPING_APPLICATION: IApplicationFixture = {
    registrationNumber: 'OPREC-E2E-P2008-S1',
    name: `${FIXTURE_MARKER} Scoping`,
    nim: 'A11.2024.30999',
    stage: 'submitted',
    result: 'pending',
    revisionRequired: false,
};

const ALL_REGISTRATION_NUMBERS: string[] = [
    ...MAIN_APPLICATIONS.map((application: IApplicationFixture): string => application.registrationNumber),
    SCOPING_APPLICATION.registrationNumber,
];

/** Label badge periode utama, urut sesuai `QUEUE_OPTIONS` di PeriodApplicantSection.vue. */
const MAIN_BADGE_LABELS: string[] = [
    'Semua antrean (7)',
    'Screening (2)',
    'Revisi (1)',
    'Interview (1)',
    'Final (1)',
    'Selesai (1)',
];

/** Periode scoping: hanya all + screening bernilai 1, sisanya nol tanpa kurung. */
const SCOPING_BADGE_LABELS: string[] = [
    'Semua antrean (1)',
    'Screening (1)',
    'Revisi',
    'Interview',
    'Final',
    'Selesai',
];

/** Setiap badge nonzero harus sama dengan jumlah baris setelah filter yang sama dipilih. */
const MAIN_QUEUE_CASES: IQueueCase[] = [
    { key: 'screening', option: /^Screening \(2\)$/, rows: 2 },
    { key: 'revision', option: /^Revisi \(1\)$/, rows: 1 },
    { key: 'interview', option: /^Interview \(1\)$/, rows: 1 },
    { key: 'final', option: /^Final \(1\)$/, rows: 1 },
    { key: 'done', option: /^Selesai \(1\)$/, rows: 1 },
];

/**
 * Input anomali terhadap URL halaman periode. Semua kunci ada di
 * `ShowRecruitmentPeriodApplicationsRequest::rules()`; validasi gagal harus
 * menjadi redirect 302 (navigasi penuh) atau 422 (XHR JSON), bukan 5xx.
 */
const ANOMALY_INPUTS: IAnomalyInput[] = [
    { label: 'queue asing', query: 'queue=bogus-value', errorKey: 'queue' },
    { label: 'queue path traversal', query: `queue=${encodeURIComponent('../../etc/passwd')}`, errorKey: 'queue' },
    { label: 'stage asing', query: 'stage=not-a-stage', errorKey: 'stage' },
    { label: 'semester di luar batas', query: 'semester=99', errorKey: 'semester' },
    { label: 'division_id bukan uuid', query: 'division_id=not-a-uuid', errorKey: 'division_id' },
    { label: 'page nol', query: 'page=0', errorKey: 'page' },
    { label: 'search 500 karakter', query: `search=${'x'.repeat(500)}`, errorKey: 'search' },
];

interface IFixtureProbe {
    periods: number;
    main_applications: number;
    scoping_applications: number;
    all: number;
    screening: number;
    revision: number;
    interview: number;
    final: number;
    done: number;
    main_period_id: string;
    scoping_period_id: string;
}

type TQueueCountKey = 'all' | 'screening' | 'revision' | 'interview' | 'final' | 'done';

const QUEUE_COUNT_KEYS: TQueueCountKey[] = ['all', 'screening', 'revision', 'interview', 'final', 'done'];

let mainPeriodId = '';
let mainPeriodPath = '';
let scopingPeriodPath = '';

interface IFixtureRow extends IApplicationFixture {
    period: 'main' | 'scoping';
    minutesAgo: number;
}

function buildFixtureRows(): IFixtureRow[] {
    const mainRows: IFixtureRow[] = MAIN_APPLICATIONS.map(
        (application: IApplicationFixture, offset: number): IFixtureRow => ({
            ...application,
            period: 'main',
            minutesAgo: offset + 1,
        })
    );

    return [...mainRows, { ...SCOPING_APPLICATION, period: 'scoping', minutesAgo: 1 }];
}

const FIXTURE_ROWS: IFixtureRow[] = buildFixtureRows();

/** Periode + aplikasi fixture; idempoten karena `removeFixtures()` dijalankan lebih dulu. */
function createFixtures(): void {
    const phpRows = FIXTURE_ROWS.map((row: IFixtureRow) => ({
        period: row.period,
        registration_number: row.registrationNumber,
        full_name: row.name,
        nim: row.nim,
        stage: row.stage,
        result: row.result,
        revision_required: row.revisionRequired,
        minutes_ago: row.minutesAgo,
    }));

    const script = String.raw`
$marker = '${FIXTURE_MARKER}';
$rows = json_decode('${JSON.stringify(phpRows)}', true, 512, JSON_THROW_ON_ERROR);
$main = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${PERIOD_NAME}',
    'slug' => '${PERIOD_SLUG}',
]);
$scoping = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${SCOPING_PERIOD_NAME}',
    'slug' => '${SCOPING_PERIOD_SLUG}',
]);
$division = App\Models\Recruitment\RecruitmentDivision::query()
    ->where('is_active', true)
    ->orderBy('code')
    ->first();
if ($division === null) {
    $division = App\Models\Recruitment\RecruitmentDivision::factory()->create([
        'name' => 'Divisi '.$marker,
        'code' => 'E2E-P2008',
        'is_active' => true,
    ]);
}
$base = now()->startOfSecond();
foreach ($rows as $row) {
    $period = $row['period'] === 'scoping' ? $scoping : $main;
    App\Models\Recruitment\RecruitmentApplication::factory()
        ->for($period, 'period')
        ->create([
            'registration_number' => $row['registration_number'],
            'full_name' => $row['full_name'],
            'nim' => $row['nim'],
            'primary_division_id' => $division->id,
            'secondary_division_id' => $division->id,
            'stage' => $row['stage'],
            'result' => $row['result'],
            'revision_required' => $row['revision_required'],
            'submitted_at' => $base->copy()->subMinutes($row['minutes_ago']),
        ]);
}
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Tinker tidak memberi exit code non-zero saat exception; hasil create diverifikasi ulang. */
function assertFixturesExist(): IFixtureProbe {
    const script = String.raw`
$main = App\Models\Recruitment\RecruitmentPeriod::query()->where('name', '${PERIOD_NAME}')->first();
$scoping = App\Models\Recruitment\RecruitmentPeriod::query()->where('name', '${SCOPING_PERIOD_NAME}')->first();
if ($main === null || $scoping === null) {
    echo json_encode(['periods' => 0]);
    exit(0);
}
$mainId = $main->id;
$scopingId = $scoping->id;
$mainQuery = fn () => App\Models\Recruitment\RecruitmentApplication::query()
    ->where('recruitment_period_id', $mainId);
echo json_encode([
    'periods' => 2,
    'main_applications' => $mainQuery()->count(),
    'scoping_applications' => App\Models\Recruitment\RecruitmentApplication::query()
        ->where('recruitment_period_id', $scopingId)
        ->count(),
    'all' => $mainQuery()->count(),
    'screening' => $mainQuery()
        ->whereIn('stage', ['submitted', 'screening'])
        ->where('result', 'pending')
        ->where('revision_required', false)
        ->count(),
    'revision' => $mainQuery()->where('revision_required', true)->count(),
    'interview' => $mainQuery()->where('stage', 'interview')->count(),
    'final' => $mainQuery()->where('stage', 'final_review')->count(),
    'done' => $mainQuery()->where('stage', 'completed')->count(),
    'main_period_id' => $mainId,
    'scoping_period_id' => $scopingId,
]);
`;

    const probe = runArtisanJson<IFixtureProbe>(['tinker', '--execute', script]);

    if (
        probe.periods !== 2 ||
        probe.main_applications !== MAIN_APPLICATIONS.length ||
        probe.scoping_applications !== 1
    ) {
        throw new Error(
            `Fixture BE-P2-008 tidak lengkap: periods=${probe.periods}, main=${probe.main_applications}, scoping=${probe.scoping_applications}`
        );
    }

    for (const key of QUEUE_COUNT_KEYS) {
        if (typeof probe[key] !== 'number') {
            throw new Error(`Probe fixture tidak memuat angka ${key}.`);
        }
    }

    const expected: Array<[TQueueCountKey, number]> = [
        ['all', 7],
        ['screening', 2],
        ['revision', 1],
        ['interview', 1],
        ['final', 1],
        ['done', 1],
    ];

    for (const [key, value] of expected) {
        const actual = probe[key];

        if (actual !== value) {
            throw new Error(`Angka fixture ${key} tidak sesuai: db=${String(actual)}, rencana=${value}`);
        }
    }

    if (
        typeof probe.main_period_id !== 'string' ||
        probe.main_period_id === '' ||
        typeof probe.scoping_period_id !== 'string' ||
        probe.scoping_period_id === ''
    ) {
        throw new Error('Fixture BE-P2-008 tidak mengembalikan period id.');
    }

    mainPeriodId = probe.main_period_id;
    mainPeriodPath = `/admin/recruitment/periods/${mainPeriodId}`;
    scopingPeriodPath = `/admin/recruitment/periods/${probe.scoping_period_id}`;

    return probe;
}

/** Hapus aplikasi lalu periode (termasuk soft-deleted) agar slug tidak bentrok. */
function removeFixtures(): void {
    const script = String.raw`
$ids = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', ${JSON.stringify(ALL_REGISTRATION_NUMBERS)})
    ->pluck('id');
App\Models\Recruitment\RecruitmentDocument::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', ${JSON.stringify(ALL_REGISTRATION_NUMBERS)})
    ->delete();
App\Models\Recruitment\RecruitmentPeriod::withTrashed()
    ->whereIn('name', ['${PERIOD_NAME}', '${SCOPING_PERIOD_NAME}'])
    ->forceDelete();
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

function applicantRows(page: Page): Locator {
    return page.locator('[data-applicant-list] tbody tr');
}

async function openApplicantList(page: Page, periodPath: string): Promise<void> {
    await page.goto(`${periodPath}?tab=peserta`, { waitUntil: 'load' });
    await expect(page.locator('[data-applicant-list]')).toBeVisible();
}

/**
 * Pilih satu opsi pada SimpleSelect shadcn berdasarkan aria-label trigger.
 *
 * Race: memilih filter memicu partial reload Inertia yang mengganti opsi di DOM,
 * jadi klik opsi bisa gagal "detached" bila navigasi sebelumnya belum selesai.
 * Helper ini menunggu daftar stabil dulu, lalu mengklik opsi bersamaan dengan
 * menunggu response GET periode + daftar ter-render kembali.
 */
async function selectFilter(page: Page, label: string, option: string | RegExp): Promise<void> {
    await expect(page.locator('[data-applicant-list]')).toBeVisible();
    await page.getByRole('combobox', { name: label }).click();
    // Popover yang tertutup tetap ada di DOM, jadi opsi dicari hanya di popover terbuka.
    const optionLocator = page.locator('[data-state="open"]').getByRole('option', { name: option });
    await expect(optionLocator).toBeVisible();

    await Promise.all([
        page.waitForResponse((response) => {
            if (response.request().method() !== 'GET') {
                return false;
            }

            const url = new URL(response.url());

            return (
                /^\/admin\/recruitment\/periods\/[0-9a-f-]+$/.test(url.pathname) &&
                response.request().headers()['x-inertia'] === 'true' &&
                response.status() < 400
            );
        }),
        optionLocator.click(),
    ]);

    await expect(page.locator('[data-applicant-list]')).toBeVisible();
}

/**
 * Baca label seluruh opsi "Filter antrean" apa adanya. Label adalah
 * `${label} (${count})` hanya bila count > 0; count nol merender label polos.
 */
async function readQueueBadgeLabels(page: Page): Promise<string[]> {
    await page.getByRole('combobox', { name: 'Filter antrean' }).click();
    const options = page.locator('[data-state="open"] [role="option"]');
    await expect(options.first()).toBeVisible();

    const labels = (await options.allInnerTexts()).map((label: string): string => label.trim());

    await page.keyboard.press('Escape');
    await expect(page.locator('[data-state="open"]')).toHaveCount(0);

    return labels;
}

/** Payload Inertia awal ada di `script[data-page="app"]` (Inertia v2), bukan atribut #app. */
async function readInertiaPage(page: Page): Promise<IInertiaPagePayload> {
    const raw = await page.locator('script[data-page="app"]').textContent();

    if (raw === null) {
        throw new Error('Payload Inertia tidak ditemukan pada script[data-page="app"].');
    }

    return JSON.parse(raw) as IInertiaPagePayload;
}

test.describe('BE-P2-008 badge jumlah antrean periode recruitment', () => {
    test.beforeAll((): void => {
        removeFixtures();
        createFixtures();
        assertFixturesExist();
    });

    test.afterAll((): void => {
        removeFixtures();
    });

    test.beforeEach(async ({ page }): Promise<void> => {
        // Rate limiter `recruitment-application-search` (60/menit) memakai cache file server
        // E2E; bersihkan agar spec ini tidak 429 saat dijalankan bersama spec lain.
        runArtisanCommand(['cache:clear']);
        // Skrip analitik pihak ketiga menggantungkan event load dan tidak relevan bagi test.
        await page.route('**/seeyu.doscom.org/**', (route) => route.abort());
    });

    test('badge merender angka tepat dan antrean kosong tanpa angka dalam kurung', async ({ page }) => {
        await openApplicantList(page, mainPeriodPath);
        expect(await readQueueBadgeLabels(page)).toEqual(MAIN_BADGE_LABELS);

        // Periode scoping: all & screening = 1; empat antrean lain nol.
        await openApplicantList(page, scopingPeriodPath);
        expect(await readQueueBadgeLabels(page)).toEqual(SCOPING_BADGE_LABELS);
    });

    test('setiap badge nonzero sama dengan jumlah baris filter dan URL queue', async ({ page }) => {
        for (const queueCase of MAIN_QUEUE_CASES) {
            await openApplicantList(page, mainPeriodPath);
            await selectFilter(page, 'Filter antrean', queueCase.option);

            await expect(page).toHaveURL(new RegExp(`[?&]queue=${queueCase.key}`));
            await expect(applicantRows(page)).toHaveCount(queueCase.rows);
        }
    });

    test('input anomali ditolak validasi tanpa response 5xx', async ({ page }) => {
        test.setTimeout(120_000);

        const serverErrors: number[] = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        // URL bersih dulu agar `redirect()->back()` (target redirect validasi) adalah halaman periode.
        await openApplicantList(page, mainPeriodPath);

        const inertiaPage = await readInertiaPage(page);
        expect(inertiaPage.component).not.toBe('Error');
        // X-Inertia-Version wajib: tanpa header ini Inertia menjawab 409 version mismatch.
        const version = inertiaPage.version;

        // a. Navigasi penuh di browser: tidak 5xx, parameter liar dibuang, halaman normal dirender.
        const navigation = await page.goto(`${mainPeriodPath}?tab=peserta&queue=bogus-value`, { waitUntil: 'load' });
        expect(navigation?.status() ?? 0).toBeLessThan(500);
        expect(page.url()).not.toContain('queue=bogus-value');
        await expect(page.locator('[data-applicant-list]')).toBeVisible();
        expect((await readInertiaPage(page)).component).not.toBe('Error');

        // b. Setiap nilai liar: navigasi penuh -> 302 ke halaman periode.
        // Laravel mengarahkan balik ke `url()->previous()` apa adanya, sehingga
        // parameter liar ikut di Location; yang dijanjikan hanya "ditolak, bukan
        // 5xx". Halaman hasil redirect diuji terpisah di langkah (e).
        for (const anomaly of ANOMALY_INPUTS) {
            const url = `${mainPeriodPath}?tab=peserta&${anomaly.query}`;

            const plain = await page.request.get(url, { failOnStatusCode: false, maxRedirects: 0 });
            expect(plain.status(), `${anomaly.label}: navigasi penuh harus redirect 302`).toBe(302);

            const location = plain.headers()['location'];
            expect(location, `${anomaly.label}: redirect harus menyertakan Location`).toBeTruthy();

            const redirected = new URL(location ?? url);
            expect(redirected.pathname, `${anomaly.label}: redirect harus ke halaman periode`).toBe(mainPeriodPath);

            // c. Jalur XHR JSON: 422 + errors.<key>.
            const jsonAccept = await page.request.get(url, {
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Version': version,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                failOnStatusCode: false,
                maxRedirects: 0,
            });
            expect(jsonAccept.status(), `${anomaly.label}: request JSON harus 422`).toBe(422);

            const body = (await jsonAccept.json()) as IValidationErrorBody;
            expect(
                Object.keys(body.errors ?? {}),
                `${anomaly.label}: body harus memuat errors.${anomaly.errorKey}`
            ).toContain(anomaly.errorKey);

            // e. Rantai redirect tidak boleh 5xx. Verifikasi lewat request HTTP agar
            // tidak bertabrakan dengan navigasi Inertia yang sedang berjalan di page.
            const followed = await page.request.get(location ?? url, {
                failOnStatusCode: false,
                maxRedirects: 10,
            });
            expect(followed.status(), `${anomaly.label}: rantai redirect tidak boleh 5xx`).toBeLessThan(500);
        }

        // f. Satu navigasi browser dengan parameter liar tetap merender daftar penuh,
        // lalu halaman bersih tetap normal setelah seluruh probe.
        await page.goto(`${mainPeriodPath}?tab=peserta&queue=bogus-value`, { waitUntil: 'load' });
        await expect(page.locator('[data-applicant-list]')).toBeVisible();
        await expect(applicantRows(page)).toHaveCount(MAIN_APPLICATIONS.length);

        await openApplicantList(page, mainPeriodPath);
        await expect(applicantRows(page)).toHaveCount(MAIN_APPLICATIONS.length);

        // d. Periode tidak ada: route model binding menjawab 404, bukan 500.
        const missingPeriod = await page.request.get(
            '/admin/recruitment/periods/00000000-0000-4000-8000-000000000000?tab=peserta',
            { failOnStatusCode: false, maxRedirects: 0 }
        );
        expect(missingPeriod.status(), 'periode uuid tidak ada harus 404').toBe(404);

        const malformedPeriod = await page.request.get('/admin/recruitment/periods/not-a-uuid?tab=peserta', {
            failOnStatusCode: false,
            maxRedirects: 0,
        });
        expect(malformedPeriod.status(), 'periode id tidak valid harus 404').toBe(404);

        expect(serverErrors, 'tidak boleh ada response >= 500').toEqual([]);
    });

    test('filter valid diterapkan lalu dibersihkan tanpa badge ganda atau basi', async ({ page }) => {
        await openApplicantList(page, mainPeriodPath);

        // Selesai: badge 1, list 1 baris.
        await selectFilter(page, 'Filter antrean', /^Selesai \(1\)$/);
        await expect(page).toHaveURL(/[?&]queue=done/);
        await expect(applicantRows(page)).toHaveCount(1);

        // Bersihkan lewat opsi yang sama; label tetap memakai hitungan periode penuh.
        await selectFilter(page, 'Filter antrean', /^Semua antrean \(7\)$/);
        await expect(page).not.toHaveURL(/[?&]queue=/);
        await expect(applicantRows(page)).toHaveCount(MAIN_APPLICATIONS.length);

        // Badge tidak berubah/dobel setelah filter dihapus.
        expect(await readQueueBadgeLabels(page)).toEqual(MAIN_BADGE_LABELS);
    });
});
