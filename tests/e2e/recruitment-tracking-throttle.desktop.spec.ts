import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import { readEnvironment } from './support/environment';

/**
 * E2E BE-P1-006: mutation portal tracking OpRec (update, correction, feedback)
 * dibatasi per sesi tracking. Spec membuat dua aplikasi uji lewat artisan,
 * membuktikan flood dari satu sesi berakhir 429 di browser, dan memastikan
 * sesi lain pada IP yang sama tetap bisa menyimpan.
 */
interface ITrackingFixture {
    registrationNumber: string;
    trackingToken: string;
}

const FIXTURE_MARKER = 'E2E BE-P1-006';
const FIRST: ITrackingFixture = {
    registrationNumber: 'OPREC-E2E-THROTTLE-1',
    trackingToken: 'e2e-throttle-token-first-111',
};
const SECOND: ITrackingFixture = {
    registrationNumber: 'OPREC-E2E-THROTTLE-2',
    trackingToken: 'e2e-throttle-token-second-222',
};

/** Buat periode + dua aplikasi tracking berbekal dokumen agar halaman edit tidak menuntut unggahan. */
function createFixtures(): void {
    const script = String.raw`
$period = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${FIXTURE_MARKER}',
    'slug' => 'e2e-be-p1-006',
]);
$division = App\Models\Recruitment\RecruitmentDivision::query()->where('is_active', true)->orderBy('code')->firstOrFail();
$make = function (string $registrationNumber, string $token) use ($period, $division): void {
    $application = App\Models\Recruitment\RecruitmentApplication::factory()
        ->for($period, 'period')
        ->withTrackingToken($token)
        ->create([
            'registration_number' => $registrationNumber,
            'primary_division_id' => $division->id,
            'stage' => App\Enums\Recruitment\ApplicationStage::Screening,
            'result' => App\Enums\Recruitment\ApplicationResult::Pending,
            'is_verified' => false,
            'revision_required' => true,
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
};
$make('${FIRST.registrationNumber}', '${FIRST.trackingToken}');
$make('${SECOND.registrationNumber}', '${SECOND.trackingToken}');
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/** Hapus seluruh baris fixture (termasuk periode soft-deleted) agar slug tidak bentrok. */
function removeFixtures(): void {
    const script = String.raw`
$registrations = ['${FIRST.registrationNumber}', '${SECOND.registrationNumber}'];
$ids = App\Models\Recruitment\RecruitmentApplication::query()
    ->whereIn('registration_number', $registrations)
    ->pluck('id');
App\Models\Recruitment\RecruitmentDocument::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentCorrectionRequest::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentFeedback::query()->whereIn('recruitment_application_id', $ids)->delete();
App\Models\Recruitment\RecruitmentApplication::query()->whereIn('registration_number', $registrations)->delete();
App\Models\Recruitment\RecruitmentPeriod::withTrashed()->where('name', '${FIXTURE_MARKER}')->forceDelete();
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

/**
 * Tinker tidak memberi exit code non-zero saat exception, sehingga hasil create
 * diverifikasi ulang: dua aplikasi harus benar-benar ada sebelum test berjalan.
 */
function assertFixturesExist(): void {
    const script = String.raw`
echo json_encode([
    'applications' => App\Models\Recruitment\RecruitmentApplication::query()
        ->whereIn('registration_number', ['${FIRST.registrationNumber}', '${SECOND.registrationNumber}'])
        ->count(),
    'period' => App\Models\Recruitment\RecruitmentPeriod::query()->where('name', '${FIXTURE_MARKER}')->count(),
]);
`;

    const result = JSON.parse(runArtisanCommand(['tinker', '--execute', script])) as {
        applications: number;
        period: number;
    };

    if (result.applications !== 2 || result.period !== 1) {
        throw new Error(
            `Fixture tracking BE-P1-006 tidak lengkap: applications=${result.applications}, period=${result.period}`
        );
    }
}

/** Batas update dibaca dari config runtime agar spec tidak menyalin angka. */
function readUpdateLimit(): number {
    const value = runArtisanCommand([
        'tinker',
        '--execute',
        "echo (int) config('recruitment.tracking_mutation_throttle.update');",
    ]);

    return Number.parseInt(value, 10);
}

/** Login portal tracking lewat UI, bukan dengan menanam cookie sesi. */
async function loginTracking(page: Page, fixture: ITrackingFixture): Promise<void> {
    await page.goto('/recruitment/track');
    await page.locator('#registration_number').fill(fixture.registrationNumber);
    await page.locator('#tracking_token').fill(fixture.trackingToken);
    await page.getByRole('button', { name: 'Masuk portal' }).click();
    await expect(page).toHaveURL(/\/recruitment\/track\/dashboard/);
}

/** Token CSRF dari cookie agar request API tetap melewati middleware web. */
async function readXsrfToken(page: Page): Promise<string> {
    const raw = await page.evaluate((): string => {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

        return match?.[1] ?? '';
    });

    return decodeURIComponent(raw);
}

/** Kirim satu mutation update tanpa X-Inertia supaya status HTTP-nya terbaca langsung. */
async function floodUpdate(page: Page, xsrfToken: string): Promise<number> {
    const response = await page.request.put('/recruitment/track', {
        headers: {
            'X-XSRF-TOKEN': xsrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        form: {},
    });

    return response.status();
}

test.describe('BE-P1-006 tracking mutation throttle', () => {
    test.beforeAll((): void => {
        removeFixtures();
        createFixtures();
        assertFixturesExist();
    });

    test.afterAll((): void => {
        removeFixtures();
    });

    test('flood mutation ditolak 429, validasi tetap jalan, sesi lain tidak terblokir', async ({ page, browser }) => {
        test.setTimeout(180_000);

        const updateLimit = readUpdateLimit();
        expect(updateLimit).toBeGreaterThan(0);

        await loginTracking(page, FIRST);
        await page.goto('/recruitment/track/edit');
        // Anomali 1: input sengaja tidak valid harus ditolak validasi, bukan 500.
        await page.locator('#phone').fill('bukan-nomor');
        await page.locator('#nim').fill('NIM DENGAN SPASI');
        await page.getByRole('button', { name: 'Simpan perubahan' }).click();

        await expect(page).toHaveURL(/\/recruitment\/track\/edit/);
        await expect(page.locator('#phone').locator('xpath=following-sibling::p[1]')).toBeVisible();
        await expect(page.locator('#nim').locator('xpath=following-sibling::p[1]')).toBeVisible();

        // Anomali 2: flood mutation melebihi kuota sesi ini.
        const xsrfToken = await readXsrfToken(page);
        let lastStatus = 0;
        let attempts = 0;

        while (lastStatus !== 429 && attempts <= updateLimit + 2) {
            lastStatus = await floodUpdate(page, xsrfToken);
            attempts += 1;
        }

        expect(lastStatus, 'flood mutation harus berhenti di 429').toBe(429);

        const throttled = await page.request.put('/recruitment/track', {
            headers: {
                'X-XSRF-TOKEN': xsrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            form: {},
        });
        expect(throttled.status()).toBe(429);
        expect(throttled.headers()['retry-after']).toBeTruthy();

        // Anomali 3: submit lewat UI saat kuota habis harus memberi feedback 429, bukan diam.
        await page.locator('#phone').fill('081234567890');
        await page.locator('#nim').fill('A11.2024.09999');
        await page.getByRole('button', { name: 'Simpan perubahan' }).click();

        await expect(page.locator('[data-sonner-toast]').first()).toContainText(/Terlalu banyak/i);

        // Sesi pendaftar lain pada IP yang sama tetap bisa menyimpan.
        const otherContext = await browser.newContext({ baseURL: readEnvironment().baseUrl });
        const otherPage = await otherContext.newPage();

        try {
            await loginTracking(otherPage, SECOND);
            await otherPage.goto('/recruitment/track/edit');
            await otherPage.locator('#phone').fill('081234567891');
            await otherPage.getByRole('button', { name: 'Simpan perubahan' }).click();
            await expect(otherPage).toHaveURL(/\/recruitment\/track\/dashboard/);
        } finally {
            await otherContext.close();
        }
    });
});
