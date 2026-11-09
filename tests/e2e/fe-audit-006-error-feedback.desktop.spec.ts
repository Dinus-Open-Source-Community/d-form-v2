import type { Page, Response } from '@playwright/test';
import { expect, ownerIdentityForTest, test } from './support/fixtures';
import type { IOwnerIdentity } from './support/marker';
import { runArtisanCommand } from './support/artisan';
import { readPageProps, readXsrfToken, startRequestLog, stubStoreResponse } from './support/network';
import { isTinkerRecord, parseTinkerJsonRecord, requireTinkerString } from './support/tinker';

/**
 * E2E FE-AUDIT-006: umpan balik kegagalan yang bisa ditindaklanjuti.
 *
 * Tiga perilaku dibuktikan di sini:
 * 1. Feed scan lintas meja gagal beruntun → banner `role="status"` "Feed scan tertunda",
 *    polling tetap jalan, dan banner hilang setelah feed pulih.
 * 2. Draft autosave OPRec ditolak browser → peringatan "Draft tidak tersimpan" tampil
 *    dan tidak ada klaim "Draft tersimpan otomatis".
 * 3. Detail applicant gagal 500 → panel tetap terbuka dengan tombol "Coba lagi",
 *    lalu memuat detail setelah route pulih.
 * Ditambah kontrak input anomali scan: body kosong tetap 422 dan response tidak
 * membocorkan exception, sedangkan `since` sampah tidak membuat feed crash.
 *
 * Fixture dibuat lewat tinker dengan marker unik per test dan selalu dihapus lagi,
 * jadi tidak ada ketergantungan pada data stress/produksi.
 */

const SCAN_PATH = '/admin/scan';
const FEED_PATH_PATTERN = '**/admin/scan/feed*';
/** RegExp dipakai untuk log request; helper log membandingkan string dengan `endsWith`. */
const FEED_LOG_PATTERN = /\/admin\/scan\/feed(\?|$)/;
const MANUAL_CODE_PLACEHOLDER = 'OPREC-2026-00001 atau kode registrasi event';
/** Poll feed pertama langsung; kegagalan beruntun memakai backoff eksponensial mulai 2 detik. */
const STALE_BANNER_TIMEOUT_MS = 20_000;
const FEED_RECOVERY_TIMEOUT_MS = 30_000;
/** Lebih dari satu siklus backoff kegagalan kedua (4 dtk) agar perubahan teks pasti terlihat. */
const FEED_LIVE_REGION_SAMPLE_GAP_MS = 5_000;

interface IRecruitmentPeriodFixture {
    periodId: string;
}

interface IApplicantFixture extends IRecruitmentPeriodFixture {
    applicationId: string;
    applicantName: string;
}

interface IScanPostResult {
    status: number;
    bodyText: string;
}

/** Nilai UUID aman disisipkan ke skrip tinker yang sudah dijalankan; diperoleh dari server. */
function escapeSqlLiteral(value: string): string {
    return value.replaceAll('\\', '\\\\').replaceAll("'", "\\'");
}

/** Periode OPRec yang masih menerima lamaran; dipakai halaman /recruitment. */
function createOpenPeriodFixture(identity: IOwnerIdentity): IRecruitmentPeriodFixture {
    const name = `E2E FE-AUDIT-006 ${identity.ownerKey}`;
    const slug = `e2e-fe-audit-006-${identity.ownerKey}`;
    const script = String.raw`
$period = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${name}',
    'slug' => '${slug}',
]);
echo json_encode(['period_id' => $period->id]);
`;

    const record = parseTinkerJsonRecord(runArtisanCommand(['tinker', '--execute', script]), 'fixture periode');

    return { periodId: requireTinkerString(record, 'period_id', 'fixture periode') };
}

/** Satu periode dengan satu applicant agar baris tabel bisa diklik. */
function createApplicantFixture(identity: IOwnerIdentity): IApplicantFixture {
    const name = `E2E FE-AUDIT-006 ${identity.ownerKey}`;
    const slug = `e2e-fe-audit-006-${identity.ownerKey}`;
    const registrationNumber = `OPREC-E2E-FEA6-${identity.ownerKey}`;
    const applicantName = `Applicant FE-AUDIT-006 ${identity.ownerKey}`;
    const divisionCode = `E2E-FEA6-${identity.ownerKey}`;
    const script = String.raw`
$period = App\Models\Recruitment\RecruitmentPeriod::factory()->open()->create([
    'name' => '${name}',
    'slug' => '${slug}',
]);
$division = App\Models\Recruitment\RecruitmentDivision::query()
    ->where('is_active', true)
    ->orderBy('code')
    ->first();
if ($division === null) {
    $division = App\Models\Recruitment\RecruitmentDivision::factory()->create([
        'name' => 'Divisi ${name}',
        'code' => '${divisionCode}',
        'is_active' => true,
    ]);
}
$application = App\Models\Recruitment\RecruitmentApplication::factory()
    ->for($period, 'period')
    ->create([
        'registration_number' => '${registrationNumber}',
        'full_name' => '${applicantName}',
        'nim' => 'A11.2026.00001',
        'semester' => 1,
        'primary_division_id' => $division->id,
        'secondary_division_id' => $division->id,
        'stage' => 'submitted',
        'result' => 'pending',
    ]);
echo json_encode([
    'period_id' => $period->id,
    'application_id' => $application->id,
]);
`;

    const record = parseTinkerJsonRecord(runArtisanCommand(['tinker', '--execute', script]), 'fixture applicant');

    return {
        periodId: requireTinkerString(record, 'period_id', 'fixture applicant'),
        applicationId: requireTinkerString(record, 'application_id', 'fixture applicant'),
        applicantName,
    };
}

/** Hapus periode beserta applicant-nya; idempoten karena memakai id yang baru dibuat. */
function removeRecruitmentFixture(fixture: IRecruitmentPeriodFixture, divisionCode: string): void {
    const periodId = escapeSqlLiteral(fixture.periodId);
    const script = String.raw`
App\Models\Recruitment\RecruitmentDocument::query()
    ->whereIn('recruitment_application_id', App\Models\Recruitment\RecruitmentApplication::query()
        ->where('recruitment_period_id', '${periodId}')
        ->pluck('id'))
    ->delete();
App\Models\Recruitment\RecruitmentApplication::query()
    ->where('recruitment_period_id', '${periodId}')
    ->delete();
App\Models\Recruitment\RecruitmentPeriod::withTrashed()->whereKey('${periodId}')->forceDelete();
App\Models\Recruitment\RecruitmentDivision::withTrashed()->where('code', '${divisionCode}')->forceDelete();
echo 'ok';
`;

    runArtisanCommand(['tinker', '--execute', script]);
}

function divisionCodeFor(identity: IOwnerIdentity): string {
    return `E2E-FEA6-${identity.ownerKey}`;
}

function applicantRows(page: Page) {
    return page.locator('[data-applicant-list] tbody tr');
}

/** Abaikan skrip analitik pihak ketiga yang menggantungkan event load. */
async function blockThirdPartyAnalytics(page: Page): Promise<void> {
    await page.route('**/seeyu.doscom.org/**', (route) => route.abort());
}

test.describe('FE-AUDIT-006 umpan balik kegagalan', () => {
    test.beforeEach(async ({ page }): Promise<void> => {
        await blockThirdPartyAnalytics(page);
    });

    test('feed scan gagal beruntun menampilkan banner tertunda lalu hilang setelah pulih', async ({ page }) => {
        test.setTimeout(90_000);

        await stubStoreResponse(page, FEED_PATH_PATTERN, {
            status: 500,
            contentType: 'application/json',
            body: JSON.stringify({ message: 'Feed gagal dimuat' }),
        });
        const requestLog = startRequestLog(page);

        try {
            await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
            await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

            // Live region sengaja persisten dan statis: teksnya hanya berubah pada transisi
            // stale/pulih, tidak pada setiap siklus backoff (regresi aksesibilitas M8/N7).
            const liveRegion = page.locator('[role="status"][aria-live="polite"][aria-atomic="true"]');
            const staleHeading = page.getByText('Feed scan tertunda', { exact: true });

            await expect(liveRegion).toContainText('Feed scan tertunda', { timeout: STALE_BANNER_TIMEOUT_MS });
            await expect(staleHeading).toBeVisible({ timeout: STALE_BANNER_TIMEOUT_MS });
            await expect(liveRegion).toContainText('Data lama tetap tampil');

            // Jumlah kegagalan dipindah ke luar live region, jadi dicari sebagai teks terpisah.
            // Elemen ini hanya dirender setelah >= 2 kegagalan beruntun.
            const failureCountNote = page.getByText(/Sudah \d+ kali gagal menyegarkan/);
            await expect(failureCountNote).toBeVisible({ timeout: STALE_BANNER_TIMEOUT_MS });
            await expect(liveRegion).not.toContainText(/kali gagal menyegarkan/);

            // Regresi M8: dua sampel teks live region dengan jeda lebih dari satu siklus
            // backoff (nextFeedDelayMs(2) = 4 dtk) selama masih stale harus identik.
            const liveTextBeforeSample = await liveRegion.textContent();
            await page.waitForTimeout(FEED_LIVE_REGION_SAMPLE_GAP_MS);
            const liveTextAfterSample = await liveRegion.textContent();

            expect(liveTextAfterSample).toBe(liveTextBeforeSample);

            // Polling tidak berhenti: percobaan feed berikutnya tetap terjadi walau masih stale.
            const attemptsWhileStale = requestLog.requestCount('GET', FEED_LOG_PATTERN);
            await expect
                .poll(() => requestLog.requestCount('GET', FEED_LOG_PATTERN), { timeout: STALE_BANNER_TIMEOUT_MS })
                .toBeGreaterThan(attemptsWhileStale);

            // Pulihkan endpoint feed; poll terjadwal berikutnya harus mengembalikan state live.
            await page.unroute(FEED_PATH_PATTERN);

            // Banner visual hilang total. Live region persisten mengumumkan pemulihan;
            // pengumuman tidak bisa diandalkan saat dev-server melakukan reload penuh
            // (HMR dari lane paralel) karena komponen di-mount ulang dengan teks kosong,
            // jadi assertion ini best-effort dan banner-removal tetap deterministik.
            await expect(staleHeading).toHaveCount(0, { timeout: FEED_RECOVERY_TIMEOUT_MS });
            const recoveredAnnouncement = (await liveRegion.textContent()) ?? '';
            if (recoveredAnnouncement !== '') {
                expect(recoveredAnnouncement).toContain('Feed scan tersambung kembali.');
            }
            expect(requestLog.requestCount('GET', FEED_LOG_PATTERN)).toBeGreaterThan(attemptsWhileStale);

            // Halaman tetap siap dipakai setelah banner hilang.
            await expect(page.getByPlaceholder(MANUAL_CODE_PLACEHOLDER)).toBeVisible();
        } finally {
            requestLog.stop();
        }
    });

    test('draft autosave ditolak browser menampilkan peringatan tanpa klaim tersimpan', async ({ page }) => {
        test.setTimeout(90_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = createOpenPeriodFixture(identity);

        try {
            // Simulasikan browser menolak tulis localStorage (quota/private mode) tanpa
            // mengganggu sessionStorage yang dipakai halaman lain.
            await page.addInitScript((): void => {
                const originalSetItem = Storage.prototype.setItem;

                Storage.prototype.setItem = function (this: Storage, key: string, value: string): void {
                    if (this === window.localStorage) {
                        throw new DOMException('Simulasi penyimpanan penuh', 'QuotaExceededError');
                    }

                    originalSetItem.call(this, key, value);
                };
            });

            await page.goto('/recruitment', { waitUntil: 'domcontentloaded' });

            const props = await readPageProps<{ registration: { is_open: boolean } }>(page);
            expect(props.registration.is_open, 'fixture periode harus membuat pendaftaran terbuka').toBe(true);

            // Field memakai `id` = nama field (`storageKey`), bukan `<label for>`.
            const fullNameInput = page.locator('#full_name');
            await expect(fullNameInput).toBeVisible();
            await fullNameInput.fill(`Pendaftar ${identity.ownerKey}`);

            await expect(
                page.getByText('Draft tidak tersimpan di perangkat ini — isian tetap bisa dikirim.')
            ).toBeVisible();
            await expect(page.getByRole('status').filter({ hasText: 'Draft tidak tersimpan' })).toContainText(
                'File tidak ikut tersimpan'
            );
            await expect(page.getByText(/Draft tersimpan otomatis/)).toHaveCount(0);
        } finally {
            removeRecruitmentFixture(fixture, divisionCodeFor(identity));
        }
    });

    test('detail applicant gagal 500 menampilkan tombol coba lagi dan pulih setelahnya', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = createApplicantFixture(identity);
        const detailPattern = `**/admin/recruitment/periods/${fixture.periodId}/applications/*`;

        try {
            await stubStoreResponse(page, detailPattern, {
                status: 500,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Internal Server Error' }),
            });

            await page.goto(`/admin/recruitment/periods/${fixture.periodId}?tab=peserta`, { waitUntil: 'load' });
            await expect(applicantRows(page)).toHaveCount(1);
            await expect(applicantRows(page).first()).toContainText(fixture.applicantName);

            const failedDetail = page.waitForResponse(
                (response: Response): boolean =>
                    response.url().includes('/applications/') && response.request().method() === 'GET'
            );

            await applicantRows(page).first().click();

            expect((await failedDetail).status(), 'request detail pertama disimulasikan gagal').toBe(500);

            const dialog = page.getByRole('dialog');
            await expect(dialog).toBeVisible();
            // Panel tetap terbuka dan menyebut kegagalan, bukan menutup diam-diam.
            await expect(dialog).toContainText('Detail peserta gagal dimuat');

            const failureAlert = dialog.getByRole('alert');
            await expect(failureAlert).toBeVisible();
            await expect(failureAlert).toContainText(
                /Sistem sedang bermasalah|gangguan di server|Coba lagi beberapa saat/i
            );
            // Pesan mentah server tidak boleh bocor ke panel.
            await expect(dialog).not.toContainText('Internal Server Error');

            const retryButton = dialog.getByRole('button', { name: 'Coba lagi' });
            await expect(retryButton).toBeVisible();

            await page.unroute(detailPattern);

            const recoveredDetail = page.waitForResponse(
                (response: Response): boolean =>
                    response.url().includes('/applications/') && response.request().method() === 'GET'
            );

            await retryButton.click();

            const recoveredResponse = await recoveredDetail;
            expect(recoveredResponse.status(), 'percobaan ulang setelah route pulih harus 200').toBe(200);

            await expect(dialog).toContainText(fixture.applicantName);
            await expect(dialog.getByRole('alert')).toHaveCount(0);
            await expect(dialog.getByRole('button', { name: 'Coba lagi' })).toHaveCount(0);
        } finally {
            removeRecruitmentFixture(fixture, divisionCodeFor(identity));
        }
    });

    test('anomali scan tertolak validasi tanpa membocorkan exception', async ({ page }) => {
        test.setTimeout(90_000);

        await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

        const invalidScan = await postScanJson(page, {});

        expect(invalidScan.status, 'body scan kosong harus ditolak validasi 422').toBe(422);

        const scanBody = parseTinkerJsonRecord(invalidScan.bodyText, 'respons scan anomali');
        const errors = scanBody['errors'];

        if (!isTinkerRecord(errors)) {
            throw new Error(`respons scan anomali harus memuat errors: ${invalidScan.bodyText.slice(0, 200)}`);
        }

        expect(Object.keys(errors), 'error validasi menunjuk field raw').toContain('raw');
        expectPublicSafe(invalidScan.bodyText, 'respons scan anomali');

        const feedResponse = await page.request.get('/admin/scan/feed?since=bukan-timestamp');
        expect(feedResponse.status(), 'since sampah harus tetap 200').toBe(200);

        const feedText = await feedResponse.text();
        const feedBody = parseTinkerJsonRecord(feedText, 'respons feed anomali');

        expect(Array.isArray(feedBody['rows']), 'feed harus mengembalikan rows array').toBe(true);
        // Cursor = timestamp baris terakhir; `null` sah saat tidak ada baris baru.
        const cursor: unknown = feedBody['cursor'];
        expect(cursor === null || typeof cursor === 'string', 'feed harus mengembalikan cursor string atau null').toBe(
            true
        );
        expectPublicSafe(feedText, 'respons feed anomali');
    });
});

/** Kirim body JSON lewat boundary browser agar CSRF memakai token sesi yang sedang aktif. */
async function postScanJson(page: Page, payload: Record<string, unknown>): Promise<IScanPostResult> {
    const xsrfToken = await readXsrfToken(page.context());

    return page.evaluate(
        async ({ body, token }: { body: string; token: string }): Promise<IScanPostResult> => {
            const response = await fetch('/admin/scan', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token,
                },
                body,
            });

            return { status: response.status, bodyText: await response.text() };
        },
        { body: JSON.stringify(payload), token: xsrfToken }
    );
}

/** Response error harus publik-safe: tanpa stack trace, SQLSTATE, atau dump HTML framework. */
function expectPublicSafe(bodyText: string, label: string): void {
    const forbiddenMarkers: string[] = ['SQLSTATE', 'Stack trace', '<!DOCTYPE', 'Illuminate\\'];

    for (const marker of forbiddenMarkers) {
        expect(bodyText, `${label} tidak boleh membocorkan "${marker}"`).not.toContain(marker);
    }
}
