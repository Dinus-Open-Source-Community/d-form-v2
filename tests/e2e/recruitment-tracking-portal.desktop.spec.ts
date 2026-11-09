import type { Page, TestInfo } from '@playwright/test';
import { cleanupOwnerFixture, countFixtureRows, expect, ownerIdentityForTest, test } from './support/fixtures';
import type { IOwnerIdentity } from './support/marker';
import { runArtisanJson } from './support/artisan';
import { startRequestLog, readPageProps } from './support/network';

/**
 * AUDIT-P1-015: portal tracking OpRec happy login, kredensial salah, dan akses
 * dashboard tanpa sesi. Token hanya tersimpan sebagai hash, jadi fixture dibuat
 * lewat satu pintu `e2e:fixture` (section `recruitment`) dengan token yang diketahui.
 * Setiap test memakai identitas marker sendiri agar data tidak bertabrakan antar test/run.
 */

interface ITrackingFixture {
    periodName: string;
    registrationNumber: string;
    trackingToken: string;
    fullName: string;
}

interface ITrackingFixtureIds {
    applicationId: string;
    periodId: string;
}

/** Hasil section `recruitment` dari `e2e:fixture`. */
interface IRecruitmentFixtureResult {
    periodId: string;
    periodName: string;
    divisionId: string;
    applicationId: string;
    registrationNumber: string;
    trackingToken: string;
    documentId: string | null;
}

interface ITrackingFixtureResult {
    recruitment?: IRecruitmentFixtureResult;
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
        registrationNumber: 'OPREC-' + identity.ownerKey,
        trackingToken: 'e2e-' + identity.ownerKey + '-tracking-token-0123456789abcdef',
        fullName: 'E2E Tracking ' + identity.ownerKey,
    };
}

/** Buat periode closed + aplikasi bertoken diketahui + dokumen minimum lewat `e2e:fixture`. */
function createFixtures(identity: IOwnerIdentity, fixture: ITrackingFixture): ITrackingFixtureIds {
    const payload = {
        marker: identity.marker,
        slug: identity.slug,
        title: `E2E tracking ${identity.ownerKey}`,
        forms: [],
        recruitment: {
            // Tracking login tidak membutuhkan periode open; periode closed tidak
            // boleh mencuri findOpenPeriod() milik alur apply yang berjalan paralel.
            period: { name: fixture.periodName, status: 'closed' },
            application: {
                registrationNumber: fixture.registrationNumber,
                trackingToken: fixture.trackingToken,
                fullName: fixture.fullName,
                stage: 'screening',
                result: 'pending',
            },
        },
    };

    const result = runArtisanJson<ITrackingFixtureResult>(
        ['e2e:fixture', `--marker=${identity.marker}`],
        JSON.stringify(payload)
    );
    const recruitment = result.recruitment;

    if (recruitment === undefined) {
        throw new Error('e2e:fixture tidak mengembalikan section recruitment.');
    }

    if (recruitment.registrationNumber !== fixture.registrationNumber) {
        throw new Error('e2e:fixture mengembalikan registrationNumber yang berbeda.');
    }

    return { applicationId: recruitment.applicationId, periodId: recruitment.periodId };
}

/** Bukti baris fixture benar-benar ada sebelum UI diuji. */
function assertFixturesExist(identity: IOwnerIdentity): void {
    const counts = countFixtureRows(identity.marker);

    if (counts.counts.recruitment_periods !== 1 || counts.counts.recruitment_applications !== 1) {
        throw new Error(`Fixture tracking tidak lengkap: ${JSON.stringify(counts.counts)}`);
    }
}

/** Hapus baris fixture ber-marker; sisa baris apa pun membuat test gagal. */
function removeFixtures(identity: IOwnerIdentity): void {
    cleanupOwnerFixture(identity);

    const counts = countFixtureRows(identity.marker);

    if (counts.count !== 0) {
        throw new Error(`Cleanup tracking AUDIT-P1-015 menyisakan baris: ${JSON.stringify(counts.counts)}`);
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
        const identity = ownerIdentityForTest(testInfo);
        const fixture = buildTrackingFixture(identity);
        const requestLog = startRequestLog(page);

        try {
            createFixtures(identity, fixture);
            assertFixturesExist(identity);

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
            removeFixtures(identity);
        }
    });

    test('token salah tetap di login dan tidak membuat sesi', async ({ page }, testInfo: TestInfo) => {
        const identity = ownerIdentityForTest(testInfo);
        const fixture = buildTrackingFixture(identity);
        const requestLog = startRequestLog(page);

        try {
            createFixtures(identity, fixture);
            assertFixturesExist(identity);

            await loginTracking(page, fixture, 'token-yang-salah');
            await expect(page).toHaveURL(/\/recruitment\/track$/);
            await expect(page.locator('#app').getByText(INVALID_CREDENTIALS_MESSAGE, { exact: true })).toBeVisible();

            // Tanpa sesi valid, dashboard harus mengembalikan user ke login.
            await page.goto('/recruitment/track/dashboard');
            await expect(page).toHaveURL(/\/recruitment\/track$/);

            expect(requestLog.serverErrorStatuses(/\/recruitment\/track/)).toEqual([]);
        } finally {
            requestLog.stop();
            removeFixtures(identity);
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
