import type { Page, TestInfo } from '@playwright/test';
import { expect, ownerIdentityForTest, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import type { IOwnerIdentity } from './support/marker';
import { startRequestLog, readPageProps } from './support/network';
import { parseTinkerJsonRecord, requireTinkerNumber } from './support/tinker';

/**
 * AUDIT-P1-015: portal tracking OpRec happy login, kredensial salah, dan akses
 * dashboard tanpa sesi. Token hanya tersimpan sebagai hash, jadi fixture dibuat
 * lewat factory `withTrackingToken()` dengan token yang diketahui. Setiap test
 * memakai identitas marker sendiri agar data tidak bertabrakan antar test/run.
 */

interface ITrackingFixture {
    periodName: string;
    periodSlug: string;
    registrationNumber: string;
    trackingToken: string;
    fullName: string;
}

interface ITrackingApplicationProps {
    registration_number: string;
    full_name: string;
    stage_label: string;
    result_label: string;
}

interface ITrackingPageProps {
    tracking: { application: ITrackingApplicationProps };
    errors?: Record<string, string>;
}

const INVALID_CREDENTIALS_MESSAGE = 'Nomor pendaftaran atau token tidak valid.';
const TRACKING_EXPIRED_MESSAGE = 'Sesi tracking habis. Masukkan kembali nomor pendaftaran dan token.';

/** Turunkan seluruh nama kolom unik fixture dari identitas test, bukan konstanta global. */
function buildTrackingFixture(identity: IOwnerIdentity): ITrackingFixture {
    return {
        periodName: 'E2E AUDIT-P1-015 ' + identity.ownerKey,
        periodSlug: 'e2e-audit-p1-015-' + identity.ownerKey,
        registrationNumber: 'OPREC-' + identity.ownerKey,
        trackingToken: 'e2e-' + identity.ownerKey,
        fullName: 'E2E Tracking ' + identity.ownerKey,
    };
}

/** Buat periode open + divisi aktif + aplikasi bertoken diketahui + dokumen minimum. */
function createFixtures(fixture: ITrackingFixture): void {
    const script = String.raw`
$period = App\Models\Recruitment\RecruitmentPeriod::factory()->create([
    'name' => '${fixture.periodName}',
    'slug' => '${fixture.periodSlug}',
    // Tracking login tidak membutuhkan periode open; periode closed tidak
    // boleh mencuri findOpenPeriod() milik alur apply yang berjalan paralel.
    'status' => App\Enums\Recruitment\RecruitmentPeriodStatus::Closed,
]);
$division = App\Models\Recruitment\RecruitmentDivision::query()->where('is_active', true)->orderBy('code')->firstOrFail();
$application = App\Models\Recruitment\RecruitmentApplication::factory()
    ->for($period, 'period')
    ->withTrackingToken('${fixture.trackingToken}')
    ->create([
        'registration_number' => '${fixture.registrationNumber}',
        'full_name' => '${fixture.fullName}',
        'primary_division_id' => $division->id,
        'stage' => App\Enums\Recruitment\ApplicationStage::Screening,
        'result' => App\Enums\Recruitment\ApplicationResult::Pending,
        'is_verified' => false,
        'revision_required' => false,
    ]);

App\Models\Recruitment\RecruitmentDocument::query()->create([
    'recruitment_application_id' => $application->id,
    'cv_path' => 'recruitment/e2e/'.$application->id.'/cv.pdf',
    'cv_original_name' => 'cv.pdf',
    'cv_mime' => 'application/pdf',
    'cv_size_bytes' => 1000,
    'portfolio_type' => 'url',
    'portfolio_url' => 'https://portfolio.example.com/e2e',
    'instagram_follow_path' => 'recruitment/e2e/'.$application->id.'/follow.jpg',
    'instagram_follow_original_name' => 'follow.jpg',
    'instagram_follow_mime' => 'image/jpeg',
    'instagram_follow_size_bytes' => 2048,
    'twibbon_url' => 'https://instagram.com/p/e2e-twibbon',
]);
echo json_encode(['application_id' => $application->id, 'period_id' => $period->id]);
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Hapus baris fixture (termasuk periode soft-deleted) dan direktori storage aplikasi. */
function removeFixtures(fixture: ITrackingFixture): void {
    const script = String.raw`
$registrations = ['${fixture.registrationNumber}'];
$ids = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', $registrations)
    ->pluck('id');
foreach (App\Models\Recruitment\RecruitmentApplication::query()->whereIn('registration_number', $registrations)->get() as $application) {
    \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('recruitment/'.$application->recruitment_period_id.'/'.$application->id);
    \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('recruitment/e2e/'.$application->id);
}
App\Models\Recruitment\RecruitmentDocument::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentCorrectionRequest::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentFeedback::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentApplication::query()->whereIn('registration_number', $registrations)->delete();
App\Models\Recruitment\RecruitmentPeriod::withTrashed()->where('name', '${fixture.periodName}')->forceDelete();
echo json_encode([
    'applications' => App\Models\Recruitment\RecruitmentApplication::query()
        ->where('registration_number', '${fixture.registrationNumber}')
        ->count(),
    'periods' => App\Models\Recruitment\RecruitmentPeriod::withTrashed()
        ->where('name', '${fixture.periodName}')
        ->count(),
]);
`;

    const label = `Cleanup tracking AUDIT-P1-015 (${fixture.registrationNumber})`;
    const record = parseTinkerJsonRecord(runArtisanCommand(['tinker', '--execute', script]), label);
    const applications = requireTinkerNumber(record, 'applications', label);
    const periods = requireTinkerNumber(record, 'periods', label);

    if (applications !== 0 || periods !== 0) {
        throw new Error(`Cleanup tracking AUDIT-P1-015 gagal: applications=${applications}, periods=${periods}`);
    }
}

/** Tinker tidak memberi exit code non-zero saat exception, jadi hasil create diverifikasi ulang. */
function assertFixturesExist(fixture: ITrackingFixture): void {
    const script = String.raw`
echo json_encode([
    'applications' => App\Models\Recruitment\RecruitmentApplication::query()
        ->where('registration_number', '${fixture.registrationNumber}')
        ->count(),
    'period' => App\Models\Recruitment\RecruitmentPeriod::query()->where('name', '${fixture.periodName}')->count(),
]);
`;

    const label = `Fixture tracking AUDIT-P1-015 (${fixture.registrationNumber})`;
    const record = parseTinkerJsonRecord(runArtisanCommand(['tinker', '--execute', script]), label);
    const applications = requireTinkerNumber(record, 'applications', label);
    const period = requireTinkerNumber(record, 'period', label);

    if (applications !== 1 || period !== 1) {
        throw new Error(`Fixture tracking AUDIT-P1-015 tidak lengkap: applications=${applications}, period=${period}`);
    }
}

/** Login portal tracking lewat UI dengan token yang diberikan. */
async function loginTracking(page: Page, fixture: ITrackingFixture, token: string): Promise<void> {
    await page.goto('/recruitment/track');
    await page.locator('#registration_number').fill(fixture.registrationNumber);
    await page.locator('#tracking_token').fill(token);
    await page.getByRole('button', { name: 'Masuk portal' }).click();
}

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('AUDIT-P1-015 portal tracking OpRec', () => {
    test('login token valid membuka dashboard pelamar tanpa 5xx', async ({ page }, testInfo: TestInfo) => {
        const fixture = buildTrackingFixture(ownerIdentityForTest(testInfo));
        const requestLog = startRequestLog(page);

        try {
            createFixtures(fixture);
            assertFixturesExist(fixture);

            await loginTracking(page, fixture, fixture.trackingToken);
            await expect(page).toHaveURL(/\/recruitment\/track\/dashboard/);
            await expect(page.getByRole('heading', { level: 1, name: fixture.fullName })).toBeVisible();
            await expect(page.locator('#app').getByText(fixture.registrationNumber, { exact: true })).toBeVisible();

            const props = await readPageProps<ITrackingPageProps>(page);

            expect(props.tracking.application.registration_number).toBe(fixture.registrationNumber);
            expect(props.tracking.application.full_name).toBe(fixture.fullName);
            expect(props.tracking.application.stage_label.length).toBeGreaterThan(0);
            expect(props.tracking.application.result_label.length).toBeGreaterThan(0);
            expect(requestLog.serverErrorStatuses(/\/recruitment\/track/)).toEqual([]);
        } finally {
            requestLog.stop();
            removeFixtures(fixture);
        }
    });

    test('token salah tetap di login dan tidak membuat sesi', async ({ page }, testInfo: TestInfo) => {
        const fixture = buildTrackingFixture(ownerIdentityForTest(testInfo));
        const requestLog = startRequestLog(page);

        try {
            createFixtures(fixture);
            assertFixturesExist(fixture);

            await loginTracking(page, fixture, 'token-yang-salah');
            await expect(page).toHaveURL(/\/recruitment\/track$/);
            await expect(page.locator('#app').getByText(INVALID_CREDENTIALS_MESSAGE, { exact: true })).toBeVisible();

            // Tanpa sesi valid, dashboard harus mengembalikan user ke login.
            await page.goto('/recruitment/track/dashboard');
            await expect(page).toHaveURL(/\/recruitment\/track$/);

            expect(requestLog.serverErrorStatuses(/\/recruitment\/track/)).toEqual([]);
        } finally {
            requestLog.stop();
            removeFixtures(fixture);
        }
    });

    test('akses dashboard tanpa sesi diarahkan ke login dengan error tracking', async ({ page }) => {
        const response = await page.goto('/recruitment/track/dashboard');

        if (response === null) {
            throw new Error('Navigasi dashboard tanpa sesi tidak menghasilkan response.');
        }

        expect(response.status()).toBeLessThan(500);
        await expect(page).toHaveURL(/\/recruitment\/track$/);
        await expect(page.locator('#app').getByText(TRACKING_EXPIRED_MESSAGE, { exact: true })).toBeVisible();

        const props = await readPageProps<ITrackingPageProps>(page);

        expect(props.errors?.tracking).toBe(TRACKING_EXPIRED_MESSAGE);
    });
});
