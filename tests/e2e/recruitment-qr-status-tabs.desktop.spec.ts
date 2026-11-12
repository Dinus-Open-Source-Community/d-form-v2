import type { Page, Response } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';

/**
 * E2E BE-P2-005: ringkasan QR hanya dihitung pada tab yang membutuhkannya.
 *
 * Fixture membuat satu periode dengan 4 applicant tahap interview: tiga punya
 * log QR terakhir `sent`/`failed`/`queued`, satu tanpa log. Cold-load tab
 * non-peserta tidak boleh merender chip progres QR, sedangkan partial reload
 * yang meminta `qr_status_summary` (refresh/polling header) tetap menghitungnya.
 */

const FIXTURE_MARKER = 'E2E BE-P2-005';
const PERIOD_NAME = `${FIXTURE_MARKER} qr status tabs`;
const PERIOD_SLUG = 'e2e-be-p2-005';
const REGISTRATION_PREFIX = 'OPREC-E2E-P2005-';

/** Label chip progres QR yang diharapkan dari fixture: 1 log queued, 1 sent, 1 failed. */
const EXPECTED_QR_LABEL = 'Progres kirim QR: 1 antrean, 1 terkirim, 1 gagal';

interface IApplicantFixture {
    registrationNumber: string;
    status: 'sent' | 'failed' | 'queued' | null;
}

const APPLICANTS: IApplicantFixture[] = [
    { registrationNumber: `${REGISTRATION_PREFIX}01`, status: 'sent' },
    { registrationNumber: `${REGISTRATION_PREFIX}02`, status: 'failed' },
    { registrationNumber: `${REGISTRATION_PREFIX}03`, status: 'queued' },
    { registrationNumber: `${REGISTRATION_PREFIX}04`, status: null },
];

const REGISTRATION_NUMBERS: string[] = APPLICANTS.map((applicant) => applicant.registrationNumber);

interface IFixtureProbe {
    period_id: string | null;
    applications: number;
    qr_logs: number;
}

interface IInertiaPagePayload {
    component: string;
    version: string;
}

interface IInertiaResponsePayload {
    props: { qr_status_summary?: { sent: number; failed: number; queued: number } };
}

interface IValidationErrorBody {
    errors?: Record<string, string[]>;
}

let periodId = '';
let periodPath = '';

/** Buat periode + 4 applicant + log QR deterministik; idempoten karena fixtures dibersihkan dulu. */
function createFixtures(): void {
    const rows = APPLICANTS.map((applicant) => ({
        registration_number: applicant.registrationNumber,
        status: applicant.status,
    }));

    const script = String.raw`
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
        'name' => 'Divisi ${FIXTURE_MARKER}',
        'code' => 'E2E-P2005',
        'is_active' => true,
    ]);
}
foreach ($rows as $row) {
    $application = App\Models\Recruitment\RecruitmentApplication::factory()
        ->for($period, 'period')
        ->create([
            'registration_number' => $row['registration_number'],
            'full_name' => '${FIXTURE_MARKER} '.$row['registration_number'],
            'stage' => 'interview',
            'primary_division_id' => $division->id,
        ]);
    if ($row['status'] === null) {
        continue;
    }
    App\Models\EmailLog::query()->create([
        'recruitment_application_id' => $application->id,
        'event_id' => null,
        'user_id' => null,
        'recipient_email' => $application->personal_email,
        'status' => App\Enums\EmailLogStatus::from($row['status']),
        'notification_type' => App\Enums\EmailNotificationType::RecruitmentInterviewScheduled,
        'error_message' => $row['status'] === 'failed' ? 'SMTP timeout' : null,
        'sent_at' => $row['status'] === 'sent' ? now() : null,
    ]);
}
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Tinker tidak memberi exit code non-zero saat exception; hasil create diverifikasi ulang. */
function assertFixturesExist(): void {
    const script = String.raw`
$period = App\Models\Recruitment\RecruitmentPeriod::query()->where('slug', '${PERIOD_SLUG}')->first();
if ($period === null) {
    echo json_encode(['period_id' => null, 'applications' => 0, 'qr_logs' => 0]);
    exit(0);
}
$ids = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', ${JSON.stringify(REGISTRATION_NUMBERS)})
    ->pluck('id');
echo json_encode([
    'period_id' => $period->id,
    'applications' => $ids->count(),
    'qr_logs' => App\Models\EmailLog::query()
        ->whereIn('recruitment_application_id', $ids)
        ->where('notification_type', App\Enums\EmailNotificationType::RecruitmentInterviewScheduled->value)
        ->count(),
]);
`;

    const probe = JSON.parse(runArtisanCommand(['tinker', '--execute', script])) as IFixtureProbe;

    if (probe.period_id === null || probe.period_id === '') {
        throw new Error('Fixture BE-P2-005 tidak mengembalikan period_id.');
    }

    if (probe.applications !== APPLICANTS.length) {
        throw new Error(`Fixture BE-P2-005 tidak lengkap: applications=${probe.applications}`);
    }

    const expectedLogs = APPLICANTS.filter((applicant) => applicant.status !== null).length;
    if (probe.qr_logs !== expectedLogs) {
        throw new Error(`Fixture BE-P2-005 log QR tidak sesuai: db=${probe.qr_logs}, rencana=${expectedLogs}`);
    }

    periodId = probe.period_id;
    periodPath = `/admin/recruitment/periods/${periodId}`;
}

/** Hapus log, aplikasi, lalu periode fixture (termasuk soft-deleted) agar slug tidak bentrok. */
function removeFixtures(): void {
    const script = String.raw`
$ids = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', ${JSON.stringify(REGISTRATION_NUMBERS)})
    ->pluck('id');
App\Models\EmailLog::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentApplication::query()->whereIn('registration_number', ${JSON.stringify(REGISTRATION_NUMBERS)})->delete();
App\Models\Recruitment\RecruitmentPeriod::withTrashed()->where('slug', '${PERIOD_SLUG}')->forceDelete();
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

function qrSummaryChip(page: Page) {
    return page.locator('[role="status"][aria-label^="Progres kirim QR"]');
}

function sendQrButton(page: Page) {
    return page.getByRole('button', { name: /Kirim QR/ });
}

/** Payload Inertia awal ada di `script[data-page="app"]` (Inertia v2). */
async function readInertiaPage(page: Page): Promise<IInertiaPagePayload> {
    const raw = await page.locator('script[data-page="app"]').textContent();

    if (raw === null) {
        throw new Error('Payload Inertia tidak ditemukan pada script[data-page="app"].');
    }

    return JSON.parse(raw) as IInertiaPagePayload;
}

test.describe('BE-P2-005 QR status per tab', () => {
    test.beforeAll((): void => {
        removeFixtures();
        createFixtures();
        assertFixturesExist();
    });

    test.afterAll((): void => {
        removeFixtures();
    });

    test.beforeEach(async ({ page }): Promise<void> => {
        await page.route('**/seeyu.doscom.org/**', (route) => route.abort());
    });

    test('cold-load tab peserta merender chip progres dan tombol Kirim QR', async ({ page }) => {
        await page.goto(`${periodPath}?tab=peserta`, { waitUntil: 'load' });

        await expect(qrSummaryChip(page)).toHaveAttribute('aria-label', EXPECTED_QR_LABEL);
        await expect(qrSummaryChip(page)).toBeVisible();
        await expect(sendQrButton(page)).toBeVisible();
        await expect(sendQrButton(page)).toBeEnabled();
    });

    test('cold-load tab non-peserta tidak merender chip progres tanpa 5xx', async ({ page }) => {
        const serverErrors: number[] = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        await page.goto(`${periodPath}?tab=interview`, { waitUntil: 'load' });

        await expect(qrSummaryChip(page)).toHaveCount(0);
        await expect(sendQrButton(page)).toBeVisible();
        await expect(page.locator('[data-page="app"]')).toHaveCount(1);
        expect(serverErrors, 'tab interview tidak boleh menghasilkan response >= 500').toEqual([]);
    });

    test('kembali ke tab peserta menampilkan chip yang sama tanpa state bocor', async ({ page }) => {
        await page.goto(`${periodPath}?tab=interview`, { waitUntil: 'load' });
        await expect(qrSummaryChip(page)).toHaveCount(0);

        await page.goto(`${periodPath}?tab=peserta`, { waitUntil: 'load' });

        await expect(qrSummaryChip(page)).toHaveAttribute('aria-label', EXPECTED_QR_LABEL);
        await expect(qrSummaryChip(page)).toBeVisible();
    });

    test('partial reload pada tab interview yang meminta qr_status_summary tetap menghitung', async ({ page }) => {
        await page.goto(`${periodPath}?tab=interview`, { waitUntil: 'load' });
        const { version } = await readInertiaPage(page);

        const response = await page.request.get(`${periodPath}?tab=interview`, {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': version,
                'X-Inertia-Partial-Component': 'Dashboard/Recruitment/Periods/Show',
                'X-Inertia-Partial-Data': 'qr_status_summary',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html, application/xhtml+xml',
            },
            failOnStatusCode: false,
            maxRedirects: 0,
        });

        expect(response.status()).toBe(200);

        const payload = (await response.json()) as IInertiaResponsePayload;
        expect(payload.props.qr_status_summary).toEqual({ sent: 1, failed: 1, queued: 1 });
    });

    test('input anomali tab tetap aman: fallback peserta dan validasi 422', async ({ page }) => {
        test.setTimeout(60_000);

        const serverErrors: number[] = [];
        page.on('response', (response: Response): void => {
            if (response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        // Tab asing dinormalkan controller ke peserta: halaman normal + chip terlihat.
        const navigation = await page.goto(`${periodPath}?tab=hacker-value`, { waitUntil: 'load' });
        expect(navigation?.status() ?? 0).toBeLessThan(500);
        await expect(qrSummaryChip(page)).toHaveAttribute('aria-label', EXPECTED_QR_LABEL);
        expect((await readInertiaPage(page)).component).not.toBe('Error');

        // Tab 21 karakter gagal validasi dan dilaporkan sebagai 422 + errors.tab.
        const { version } = await readInertiaPage(page);
        const invalidTab = 'x'.repeat(21);
        const validation = await page.request.get(`${periodPath}?tab=${invalidTab}`, {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': version,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            failOnStatusCode: false,
            maxRedirects: 0,
        });

        expect(validation.status()).toBe(422);
        const body = (await validation.json()) as IValidationErrorBody;
        expect(Object.keys(body.errors ?? {})).toContain('tab');
        expect(serverErrors, 'tidak boleh ada response >= 500').toEqual([]);
    });
});
