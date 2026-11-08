import type { Page, Response } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';

/**
 * E2E BE-P2-001: daftar applicant pada halaman periode recruitment.
 *
 * Spec membuat satu periode fixture dengan 25 aplikasi deterministik, lalu
 * membuktikan list, pagination, filter (queue/stage/semester), pencarian
 * (LIKE substring pada full_name/nim/registration_number), dan perilaku input
 * anomali. Data stress (1 juta baris) tidak pernah dipakai untuk angka eksak;
 * periode stress hanya dipakai sebagai pembanding waktu navigasi.
 */

/** Penanda unik fixture agar tidak bentrok dengan data stress atau spec lain. */
const FIXTURE_MARKER = 'E2E BE-P2-001';
const PERIOD_NAME = `${FIXTURE_MARKER} applicant list`;
const PERIOD_SLUG = 'e2e-be-p2-001';

/** Jumlah applicant fixture: 25 = satu halaman penuh (20) + 5 baris halaman dua. */
const APPLICANT_COUNT = 25;
/** Baris 01-20 memakai nama "Zahra", baris 21-25 memakai "Wijaya". */
const ZAHRA_ROW_LIMIT = 20;
/** Baris dengan result Rejected (bukan bagian antrean screening). */
const REJECTED_ROW_INDEX = 9;
/** Baris dengan revision_required true (bukan bagian antrean screening). */
const REVISION_ROW_INDEX = 4;
/** Baris terakhir sengaja semester 5 agar filter semester menghasilkan tepat 1 baris. */
const SEMESTER_FIVE_ROW_INDEX = APPLICANT_COUNT;

/** Batas atas waktu navigasi; generik agar tidak flaky pada cache dingin (bukan gate performa). */
const NAVIGATION_BUDGET_MS = 10_000;
/**
 * Pencarian `LIKE` tidak bisa memakai index, dan paginator tetap menghitung COUNT
 * seluruh periode (20.000 baris pada dataset stress), jadi respons bisa jauh lebih
 * lama dari batas default Playwright pada cache dingin.
 */
const SEARCH_RESPONSE_TIMEOUT_MS = 30_000;

const STAGES = ['submitted', 'screening', 'interview', 'final_review', 'completed'] as const;

interface IApplicantFixture {
    index: number;
    name: string;
    nim: string;
    registrationNumber: string;
    stage: string;
    result: string;
    revisionRequired: boolean;
    semester: number;
    /** `submitted_at` = sekarang - N menit; baris 02 dan 03 berbagi menit yang sama. */
    minutesAgo: number;
}

interface IFixtureProbe {
    applications: number;
    periods: number;
    screening_queue: number;
    completed: number;
    semester_five: number;
    largest_period_id: string | null;
    largest_period_applications: number;
}

interface IInertiaPagePayload {
    component: string;
    version: string;
}

interface IValidationErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
}

interface IAnomalyInput {
    label: string;
    query: string;
    errorKey: string;
}

function pad(value: number, length: number): string {
    return String(value).padStart(length, '0');
}

/** Rencana fixture; satu sumber kebenaran untuk data yang dibuat dan angka yang di-assert. */
function buildApplicantFixtures(): IApplicantFixture[] {
    return Array.from({ length: APPLICANT_COUNT }, (_, offset: number): IApplicantFixture => {
        const index = offset + 1;
        const prefix = index <= ZAHRA_ROW_LIMIT ? 'Zahra' : 'Wijaya';
        const stage = STAGES[(index - 1) % STAGES.length] ?? 'submitted';

        return {
            index,
            name: `${prefix} ${FIXTURE_MARKER} ${pad(index, 2)}`,
            nim: `A11.2024.${20000 + index}`,
            registrationNumber: `OPREC-E2E-P2001-${pad(index, 2)}`,
            stage,
            result: index === REJECTED_ROW_INDEX ? 'rejected' : 'pending',
            revisionRequired: index === REVISION_ROW_INDEX,
            semester: index === SEMESTER_FIVE_ROW_INDEX ? 5 : 1,
            minutesAgo: index === 3 ? 2 : index,
        };
    });
}

const APPLICANTS: IApplicantFixture[] = buildApplicantFixtures();
const REGISTRATION_NUMBERS: string[] = APPLICANTS.map((applicant) => applicant.registrationNumber);

/** Baris yang dirender di halaman 1 (urutan `submitted_at DESC, id DESC`). */
const PAGE_ONE_NAMES: string[] = APPLICANTS.slice(0, 20).map((applicant) => applicant.name);
/** Baris yang dirender di halaman 2. */
const PAGE_TWO_NAMES: string[] = APPLICANTS.slice(20).map((applicant) => applicant.name);

/** Salinan aturan `queueCounts()/applyQueueFilter()` untuk antrean screening. */
const SCREENING_QUEUE_NAMES: string[] = APPLICANTS.filter(
    (applicant) =>
        (applicant.stage === 'submitted' || applicant.stage === 'screening') &&
        applicant.result === 'pending' &&
        !applicant.revisionRequired
).map((applicant) => applicant.name);

const COMPLETED_NAMES: string[] = APPLICANTS.filter((applicant) => applicant.stage === 'completed').map(
    (applicant) => applicant.name
);

const SEMESTER_FIVE_NAMES: string[] = APPLICANTS.filter((applicant) => applicant.semester === 5).map(
    (applicant) => applicant.name
);

/** Input anomali: setiap nilai liar harus ditolak validasi dengan error key yang cocok. */
const ANOMALY_INPUTS: IAnomalyInput[] = [
    { label: 'stage asing', query: 'stage=bogus-value', errorKey: 'stage' },
    { label: 'queue path traversal', query: `queue=${encodeURIComponent('../../etc/passwd')}`, errorKey: 'queue' },
    { label: 'semester di luar batas', query: 'semester=99', errorKey: 'semester' },
    { label: 'division_id bukan uuid', query: 'division_id=not-a-uuid', errorKey: 'division_id' },
    { label: 'page nol', query: 'page=0', errorKey: 'page' },
    { label: 'search 500 karakter', query: `search=${'x'.repeat(500)}`, errorKey: 'search' },
];

/** ID periode fixture dan periode stress terbesar, diisi `beforeAll`. */
let periodId = '';
let periodPath = '';
let largestStressPeriodId: string | null = null;
let largestStressApplications = 0;

/** Periode fixture: satu divisi aktif (dipakai ulang bila ada) + 25 aplikasi. */
function createFixtures(): void {
    const rows = [...APPLICANTS].reverse().map((applicant) => ({
        name: applicant.name,
        nim: applicant.nim,
        registration_number: applicant.registrationNumber,
        stage: applicant.stage,
        result: applicant.result,
        revision_required: applicant.revisionRequired,
        semester: applicant.semester,
        minutes_ago: applicant.minutesAgo,
    }));

    const script = String.raw`
$marker = '${FIXTURE_MARKER}';
$rows = json_decode('${JSON.stringify(rows)}', true, 512, JSON_THROW_ON_ERROR);
$period = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${PERIOD_NAME}',
    'slug' => '${PERIOD_SLUG}',
]);
$division = App\Models\Recruitment\RecruitmentDivision::query()
    ->where('is_active', true)
    ->orderBy('code')
    ->first();
if ($division === null) {
    $division = App\Models\Recruitment\RecruitmentDivision::factory()->create([
        'name' => 'Divisi '.$marker,
        'code' => 'E2E-P2001',
        'is_active' => true,
    ]);
}
$base = now()->startOfSecond();
foreach ($rows as $row) {
    App\Models\Recruitment\RecruitmentApplication::factory()
        ->for($period, 'period')
        ->create([
            'registration_number' => $row['registration_number'],
            'full_name' => $row['name'],
            'nim' => $row['nim'],
            'semester' => $row['semester'],
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
$period = App\Models\Recruitment\RecruitmentPeriod::query()->where('name', '${PERIOD_NAME}')->first();
if ($period === null) {
    echo json_encode(['applications' => 0, 'periods' => 0]);
    exit(0);
}
$largest = App\Models\Recruitment\RecruitmentPeriod::query()
    ->withCount('applications')
    ->whereKeyNot($period->id)
    ->orderByDesc('applications_count')
    ->first();
echo json_encode([
    'applications' => App\Models\Recruitment\RecruitmentApplication::query()
        ->whereIn('registration_number', ${JSON.stringify(REGISTRATION_NUMBERS)})
        ->count(),
    'periods' => 1,
    'screening_queue' => App\Models\Recruitment\RecruitmentApplication::query()
        ->where('recruitment_period_id', $period->id)
        ->whereIn('stage', ['submitted', 'screening'])
        ->where('result', 'pending')
        ->where('revision_required', false)
        ->count(),
    'completed' => App\Models\Recruitment\RecruitmentApplication::query()
        ->where('recruitment_period_id', $period->id)
        ->where('stage', 'completed')
        ->count(),
    'semester_five' => App\Models\Recruitment\RecruitmentApplication::query()
        ->where('recruitment_period_id', $period->id)
        ->where('semester', 5)
        ->count(),
    'period_id' => $period->id,
    'largest_period_id' => $largest?->id,
    'largest_period_applications' => $largest?->applications_count ?? 0,
]);
`;

    const output = runArtisanCommand(['tinker', '--execute', script]);
    const probe = JSON.parse(output) as IFixtureProbe & { period_id?: string };

    if (probe.periods !== 1 || probe.applications !== APPLICANT_COUNT) {
        throw new Error(
            `Fixture BE-P2-001 tidak lengkap: applications=${probe.applications}, periods=${probe.periods}`
        );
    }

    if (probe.screening_queue !== SCREENING_QUEUE_NAMES.length) {
        throw new Error(
            `Antrean screening fixture tidak sesuai: db=${probe.screening_queue}, rencana=${SCREENING_QUEUE_NAMES.length}`
        );
    }

    if (probe.completed !== COMPLETED_NAMES.length || probe.semester_five !== SEMESTER_FIVE_NAMES.length) {
        throw new Error(`Agregat fixture tidak sesuai: completed=${probe.completed}, semester5=${probe.semester_five}`);
    }

    if (probe.period_id === undefined || probe.period_id === '') {
        throw new Error('Fixture BE-P2-001 tidak mengembalikan period_id.');
    }

    periodId = probe.period_id;
    periodPath = `/admin/recruitment/periods/${periodId}`;
    largestStressPeriodId = probe.largest_period_id;
    largestStressApplications = probe.largest_period_applications;

    return probe;
}

/** Hapus aplikasi, dokumen, lalu periode (termasuk soft-deleted) agar slug tidak bentrok. */
function removeFixtures(): void {
    const script = String.raw`
$ids = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', ${JSON.stringify(REGISTRATION_NUMBERS)})
    ->pluck('id');
App\Models\Recruitment\RecruitmentDocument::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentApplication::query()->whereIn('registration_number', ${JSON.stringify(REGISTRATION_NUMBERS)})->delete();
App\Models\Recruitment\RecruitmentPeriod::withTrashed()->where('name', '${PERIOD_NAME}')->forceDelete();
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

function applicantRows(page: Page) {
    return page.locator('[data-applicant-list] tbody tr');
}

function applicantNames(page: Page) {
    return page.locator('[data-applicant-list] tbody tr td:nth-child(2)');
}

async function readRenderedNames(page: Page): Promise<string[]> {
    const names = await applicantNames(page).allInnerTexts();

    return names.map((name: string): string => name.trim());
}

/** Buka tab peserta tanpa filter; halaman baru selalu memakai state bersih. */
async function openApplicantList(page: Page): Promise<void> {
    await page.goto(`${periodPath}?tab=peserta`, { waitUntil: 'load' });
    await expect(page.locator('[data-applicant-list]')).toBeVisible();
}

/** Pilih satu opsi pada SimpleSelect shadcn berdasarkan aria-label trigger. */
async function selectFilter(page: Page, label: string, option: string | RegExp): Promise<void> {
    await page.getByRole('combobox', { name: label }).click();
    // Popover yang tertutup tetap ada di DOM, jadi opsi dicari hanya di popover terbuka.
    await page.locator('[data-state="open"]').getByRole('option', { name: option }).click();
}

interface IInertiaPagePayloadWithId extends IInertiaPagePayload {
    props?: { errors?: Record<string, string[]> };
}

/** Payload Inertia awal ada di `script[data-page="app"]` (Inertia v2), bukan atribut #app. */
async function readInertiaPage(page: Page): Promise<IInertiaPagePayloadWithId> {
    const raw = await page.locator('script[data-page="app"]').textContent();

    if (raw === null) {
        throw new Error('Payload Inertia tidak ditemukan pada script[data-page="app"].');
    }

    return JSON.parse(raw) as IInertiaPagePayloadWithId;
}

test.describe('BE-P2-001 daftar applicant periode recruitment', () => {
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

    test('list merender 20 baris halaman 1 dengan applicant terbaru di atas', async ({ page }) => {
        await openApplicantList(page);

        await expect(applicantRows(page)).toHaveCount(20);
        expect(await readRenderedNames(page)).toEqual(PAGE_ONE_NAMES);
    });

    test('pagination halaman 2 memuat 5 baris dan tidak menduplikasi halaman 1', async ({ page }) => {
        await openApplicantList(page);
        const firstPageNames = await readRenderedNames(page);

        await page.getByRole('button', { name: 'Halaman 2' }).click();
        await expect(page).toHaveURL(/page=2/);
        await expect(applicantRows(page)).toHaveCount(5);

        const secondPageNames = await readRenderedNames(page);
        expect(secondPageNames).toEqual(PAGE_TWO_NAMES);

        const firstPageSet = new Set<string>(firstPageNames);
        const duplicated = secondPageNames.filter((name: string): boolean => firstPageSet.has(name));
        expect(duplicated, 'nama antar halaman tidak boleh beririsan').toEqual([]);
    });

    test('filter antrean screening menyaring sesuai aturan queueCounts', async ({ page }) => {
        await openApplicantList(page);

        await selectFilter(page, 'Filter antrean', /^Screening/);
        await expect(page).toHaveURL(/queue=screening/);

        await expect(applicantRows(page)).toHaveCount(SCREENING_QUEUE_NAMES.length);
        expect((await readRenderedNames(page)).sort()).toEqual([...SCREENING_QUEUE_NAMES].sort());
    });

    test('filter tahap completed hanya menampilkan baris completed', async ({ page }) => {
        await openApplicantList(page);

        await selectFilter(page, 'Filter antrean', 'Semua antrean');
        await selectFilter(page, 'Filter tahap', 'Selesai');
        await expect(page).toHaveURL(/stage=completed/);

        await expect(applicantRows(page)).toHaveCount(COMPLETED_NAMES.length);
        expect((await readRenderedNames(page)).sort()).toEqual([...COMPLETED_NAMES].sort());
    });

    test('filter semester 5 menyisakan tepat satu baris', async ({ page }) => {
        await openApplicantList(page);

        await selectFilter(page, 'Filter semester', 'Semester 5');
        await expect(page).toHaveURL(/semester=5/);

        await expect(applicantRows(page)).toHaveCount(1);
        expect(await readRenderedNames(page)).toEqual(SEMESTER_FIVE_NAMES);
    });

    test('pencarian memakai LIKE substring untuk nama dan nomor pendaftaran', async ({ page }) => {
        // COUNT paginator tetap memindai satu periode penuh, jadi beri ruang lebih dari default.
        test.setTimeout(120_000);
        await openApplicantList(page);
        const search = page.getByPlaceholder('Cari nama, NIM, nomor pendaftaran...');

        // Pencarian adalah LIKE %kata% pada full_name/nim/registration_number.
        // Angka total tidak di-assert karena biaya COUNT berbeda antar dataset.
        await search.fill('Zahra');
        await expect(page).toHaveURL(/search=Zahra/, { timeout: SEARCH_RESPONSE_TIMEOUT_MS });

        const zahraNames = await readRenderedNames(page);
        expect(zahraNames.length).toBeGreaterThan(0);
        expect(
            zahraNames.every((name: string): boolean => name.includes('Zahra')),
            `semua baris harus memuat kata Zahra, dapat: ${zahraNames.slice(0, 3).join(' | ')}`
        ).toBe(true);
        expect(
            zahraNames.every((name: string): boolean => name.includes(FIXTURE_MARKER)),
            'tidak boleh ada baris dari periode lain'
        ).toBe(true);

        const target = APPLICANTS[0];
        if (target === undefined) {
            throw new Error('Fixture applicant pertama tidak ada.');
        }

        await search.fill(target.registrationNumber);
        await expect(page).toHaveURL(new RegExp(`search=${target.registrationNumber}`), {
            timeout: SEARCH_RESPONSE_TIMEOUT_MS,
        });

        // Nomor pendaftaran lengkap hanya dimiliki satu baris fixture, sehingga
        // kolom nomor pada hasil pencarian harus tepat satu.
        await expect(applicantRows(page).filter({ hasText: target.registrationNumber })).toHaveCount(1, {
            timeout: SEARCH_RESPONSE_TIMEOUT_MS,
        });
        expect(await readRenderedNames(page)).toContain(target.name);
    });

    test('input anomali ditolak validasi tanpa response 5xx', async ({ page }) => {
        test.setTimeout(120_000);

        // c. Kumpulkan seluruh response >= 500 sepanjang skenario anomali.
        const serverErrors: number[] = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        // URL bersih dulu agar `url()->previous()` (target redirect validasi) adalah halaman periode.
        await openApplicantList(page);

        const inertiaPage = await readInertiaPage(page);
        expect(inertiaPage.component).not.toBe('Error');
        // X-Inertia-Version wajib: tanpa header ini Inertia menjawab 409 version mismatch.
        const version = inertiaPage.version;

        // a. Navigasi penuh di browser: tidak 5xx, parameter liar dibuang, halaman normal dirender.
        const navigation = await page.goto(`${periodPath}?tab=peserta&stage=bogus-value`, { waitUntil: 'load' });
        expect(navigation?.status() ?? 0).toBeLessThan(500);
        expect(page.url()).not.toContain('stage=bogus-value');
        await expect(page.locator('[data-applicant-list]')).toBeVisible();
        expect((await readInertiaPage(page)).component).not.toBe('Error');

        for (const anomaly of ANOMALY_INPUTS) {
            const url = `${periodPath}?tab=peserta&${anomaly.query}`;

            // Plain request (tanpa X-Inertia) = jalur navigasi penuh: redirect balik 302
            // ke halaman periode, bukan halaman error dan bukan 5xx. `redirect()->back()`
            // memakai URL request terakhir sebagai target, jadi yang dijamin adalah
            // parameter yang sedang diuji tidak ikut terbawa.
            const plain = await page.request.get(url, { failOnStatusCode: false, maxRedirects: 0 });
            expect(plain.status(), `${anomaly.label}: navigasi penuh harus redirect 302`).toBe(302);

            const location = plain.headers()['location'];
            expect(location, `${anomaly.label}: redirect harus menyertakan Location`).toBeTruthy();

            const redirected = new URL(location ?? url);
            expect(redirected.pathname, `${anomaly.label}: redirect harus ke halaman periode`).toBe(periodPath);
            expect(
                redirected.searchParams.has(anomaly.errorKey),
                `${anomaly.label}: parameter tidak valid harus dibuang dari target redirect`
            ).toBe(false);

            // Jalur XHR Inertia dengan Accept html: redirect + error diflash ke sesi.
            const htmlAccept = await page.request.get(url, {
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Version': version,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html, application/xhtml+xml',
                },
                failOnStatusCode: false,
                maxRedirects: 0,
            });
            expect(
                htmlAccept.status(),
                `${anomaly.label}: Inertia html request harus redirect validasi, dapat ${htmlAccept.status()}`
            ).toBe(302);

            // Jalur XHR JSON: 422 + errors.<key>.
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
        }

        // Error hasil flash tetap terbaca sebagai props `errors` pada halaman bersih.
        const clean = await page.request.get(`${periodPath}?tab=peserta`, {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': version,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            failOnStatusCode: false,
            maxRedirects: 0,
        });
        expect(clean.status()).toBe(200);

        expect(serverErrors, 'tidak boleh ada response >= 500').toEqual([]);
    });

    test('waktu navigasi daftar applicant tercatat', async ({ page }) => {
        test.setTimeout(240_000);

        interface IMeasurement {
            elapsedMs: number;
            status: number;
        }

        const measure = async (label: string, path: string): Promise<IMeasurement> => {
            const started = performance.now();

            try {
                const response = await page.goto(path, { waitUntil: 'load', timeout: 90_000 });
                const status = response?.status() ?? 0;

                // Halaman 5xx tidak akan pernah memuat list; catat statusnya saja.
                if (status < 400) {
                    await expect(page.locator('[data-applicant-list]')).toBeVisible({ timeout: 30_000 });
                }

                const elapsedMs = Math.round(performance.now() - started);
                console.log(`[BE-P2-001] ${label}: ${elapsedMs} ms (HTTP ${status})`);

                return { elapsedMs, status };
            } catch (error: unknown) {
                const message = error instanceof Error ? error.message.split('\n')[0] : String(error);
                const elapsedMs = Math.round(performance.now() - started);
                console.log(`[BE-P2-001] ${label}: ${elapsedMs} ms (navigasi gagal: ${message})`);

                return { elapsedMs, status: 0 };
            }
        };

        // Pemanasan: modul Vite/aset dan cache query di-load dulu agar angka tidak didominasi cold start.
        await openApplicantList(page);

        const fixture = await measure(`periode fixture (${APPLICANT_COUNT} applicant)`, `${periodPath}?tab=peserta`);
        expect(fixture.status, 'periode fixture harus merender normal').toBeLessThan(400);
        expect(fixture.elapsedMs, 'navigasi periode fixture harus di bawah batas generik').toBeLessThan(
            NAVIGATION_BUDGET_MS
        );

        if (largestStressPeriodId !== null) {
            // Angka periode stress hanya bukti, bukan gate: biaya per periode ditentukan
            // agregat controller (queue counts + ringkasan QR) di luar tiket index ini.
            await measure(
                `periode stress terbesar (${largestStressApplications} applicant, bukti bukan gate)`,
                `/admin/recruitment/periods/${largestStressPeriodId}?tab=peserta`
            );
        } else {
            console.log('[BE-P2-001] periode stress tidak tersedia; pengukuran pembanding dilewati.');
        }
    });
});
