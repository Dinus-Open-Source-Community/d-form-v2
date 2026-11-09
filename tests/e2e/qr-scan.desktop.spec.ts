import type { Locator, Page, Response, Route } from '@playwright/test';
import { cleanupOwnerFixture, countFixtureRows, expect, ownerIdentityForTest, test } from './support/fixtures';
import type { IOwnerIdentity } from './support/marker';
import { runArtisanJson } from './support/artisan';
import { startRequestLog } from './support/network';

/**
 * E2E AUDIT-P1-015: check-in QR manual pada halaman scanner global (`/admin/scan`).
 *
 * Fixture dibuat lewat satu pintu `e2e:fixture`: event published + form + submission
 * `review_status=accepted` dengan `registration_code` unik. Submission sengaja dibuat
 * tanpa attendance agar scan membuktikan alur aplikasi yang mencatat kehadiran.
 */

const SCAN_PATH = '/admin/scan';
const MANUAL_CODE_PLACEHOLDER = 'OPREC-2026-00001 atau kode registrasi event';
const HERO_HEADING = 'Hasil Scan Terakhir';

/** Label KPI di halaman scan; nilai diambil dari sibling `<p>` tepat setelah label. */
type TKpiLabel = 'Check-in berhasil' | 'Sudah scan' | 'Tidak valid';

interface IScanFixtureInput {
    code: string;
    attendeeName: string;
    attendeeEmail: string;
    eventTitle: string;
}

interface IScanFixtureIds {
    eventId: string;
    formId: string;
    answerId: string;
}

/** Satu form hasil `e2e:fixture` (bentuk kontrak command). */
interface IFixtureFormResult {
    formId: string;
    fieldIds: string[];
    submissionIds: string[];
}

/** Submission accepted hasil `e2e:fixture`; attendance hanya ada bila diminta. */
interface IAcceptedAnswerResult {
    answerId: string;
    registrationCode: string;
    formId: string;
    attendanceId: string | null;
}

interface IScanFixtureResult {
    eventId: string;
    forms: IFixtureFormResult[];
    acceptedAnswers: IAcceptedAnswerResult[];
}

function buildScanFixture(identity: IOwnerIdentity): IScanFixtureInput {
    return {
        code: `E2E-P1015-${identity.ownerKey.toUpperCase()}`,
        attendeeName: `E2E AUDIT-P1-015 attendee ${identity.ownerKey}`,
        attendeeEmail: `${identity.marker}@e2e.invalid`,
        eventTitle: `E2E AUDIT-P1-015 event ${identity.ownerKey}`,
    };
}

/** Buat event + form + submission accepted lewat `e2e:fixture`; attendance belum ada. */
function createScanFixture(identity: IOwnerIdentity, fixture: IScanFixtureInput): IScanFixtureIds {
    const payload = {
        marker: identity.marker,
        slug: identity.slug,
        title: fixture.eventTitle,
        forms: [
            {
                title: 'Form 1',
                fields: [{ label: 'Nama', name: 'name', input_type: 'input', required: true }],
                submissions: 0,
            },
        ],
        acceptedAnswers: [
            {
                formIndex: 0,
                registrationCode: fixture.code,
                answers: { name: fixture.attendeeName },
                invitedEmail: fixture.attendeeEmail,
            },
        ],
    };

    const result = runArtisanJson<IScanFixtureResult>(
        ['e2e:fixture', `--marker=${identity.marker}`],
        JSON.stringify(payload)
    );
    const form = result.forms[0];
    const accepted = result.acceptedAnswers[0];

    if (form === undefined || accepted === undefined) {
        throw new Error('e2e:fixture tidak mengembalikan form/acceptedAnswer untuk qr-scan.');
    }

    if (accepted.registrationCode !== fixture.code) {
        throw new Error('e2e:fixture mengembalikan registrationCode yang berbeda.');
    }

    return { eventId: result.eventId, formId: form.formId, answerId: accepted.answerId };
}

/** Hapus seluruh baris marker; sisa baris apa pun membuat test gagal. */
function removeScanFixture(identity: IOwnerIdentity): void {
    cleanupOwnerFixture(identity);

    const counts = countFixtureRows(identity.marker);

    if (counts.count !== 0) {
        throw new Error(`cleanup qr-scan menyisakan baris: ${JSON.stringify(counts.counts)}`);
    }
}

/** Jumlah attendance milik marker (bukti utama DB). */
function attendanceCount(marker: string): number {
    return countFixtureRows(marker).counts.event_attendances;
}

/** Nilai KPI diambil dari `<p>` nilai yang mengikuti `<p>` label. */
function kpiValue(page: Page, label: TKpiLabel): Locator {
    return page.locator(`xpath=//p[normalize-space()="${label}"]/following-sibling::p[1]`);
}

async function readKpi(page: Page, label: TKpiLabel): Promise<number> {
    const raw = await kpiValue(page, label).textContent();
    const value = Number((raw ?? '').trim());

    if (!Number.isFinite(value)) {
        throw new Error(`KPI "${label}" tidak numerik: ${raw ?? 'null'}`);
    }

    return value;
}

function heroCard(page: Page): Locator {
    return page
        .locator('[data-slot="card"]')
        .filter({ has: page.getByRole('heading', { name: HERO_HEADING, exact: true }) });
}

function isScanStoreResponse(response: Response): boolean {
    return response.request().method() === 'POST' && new URL(response.url()).pathname === SCAN_PATH;
}

/** Isi input manual lalu klik `Proses check-in`; kembalikan response POST yang terjadi. */
async function submitManualScan(page: Page, code: string): Promise<Response> {
    await page.getByPlaceholder(MANUAL_CODE_PLACEHOLDER).fill(code);

    const responsePromise = page.waitForResponse(isScanStoreResponse);
    await page.getByRole('button', { name: 'Proses check-in' }).click();

    return responsePromise;
}

test.describe('AUDIT-P1-015 QR scan manual', () => {
    test.beforeEach(async ({ page }): Promise<void> => {
        await page.route('**/seeyu.doscom.org/**', (route: Route) => route.abort());
    });

    test('happy path: kode registrasi manual membuat tepat satu attendance', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = buildScanFixture(identity);
        const requestLog = startRequestLog(page);

        try {
            createScanFixture(identity, fixture);

            await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
            await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

            const beforeSuccess = await readKpi(page, 'Check-in berhasil');
            const response = await submitManualScan(page, fixture.code);

            expect(response.status(), 'check-in kode valid harus 200').toBe(200);

            // Envelope JSON tidak dibaca ulang: Chromium bisa membuang body response
            // setelah halaman re-render. Status + hero + baris DB adalah bukti flow.
            const hero = heroCard(page);
            await expect(hero.getByText(fixture.attendeeName, { exact: true })).toBeVisible();
            await expect(hero.getByText('Check-in berhasil', { exact: true })).toBeVisible();
            await expect(hero.getByText('Manual', { exact: true })).toBeVisible();
            // Feed lintas meja bisa menambah KPI lain; bukti eksak ada pada jumlah attendance.
            await expect
                .poll(() => readKpi(page, 'Check-in berhasil'), { timeout: 15_000 })
                .toBeGreaterThanOrEqual(beforeSuccess + 1);

            expect(attendanceCount(identity.marker), 'tepat satu attendance').toBe(1);
            expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
        } finally {
            try {
                expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
            } finally {
                requestLog.stop();
                removeScanFixture(identity);
            }
        }
    });

    test('failure duplicate: scan kode yang sama dua kali tetap satu attendance', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = buildScanFixture(identity);
        const requestLog = startRequestLog(page);

        try {
            createScanFixture(identity, fixture);

            await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
            await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

            const first = await submitManualScan(page, fixture.code);
            expect(first.status(), 'scan pertama harus 200').toBe(200);
            expect(attendanceCount(identity.marker)).toBe(1);

            const beforeDuplicate = await readKpi(page, 'Sudah scan');
            const second = await submitManualScan(page, fixture.code);

            expect(second.status(), 'scan kedua harus 409').toBe(409);

            await expect(
                page.locator('[data-sonner-toast]').filter({ hasText: 'Peserta sudah pernah scan.' })
            ).toBeVisible();

            const hero = heroCard(page);
            await expect(hero.getByText(fixture.attendeeName, { exact: true })).toBeVisible();
            await expect(hero.getByText('Sudah pernah scan', { exact: true })).toBeVisible();
            await expect.poll(() => readKpi(page, 'Sudah scan'), { timeout: 15_000 }).toBe(beforeDuplicate + 1);

            expect(attendanceCount(identity.marker), 'duplikat tidak menambah attendance').toBe(1);
            expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
        } finally {
            try {
                expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
            } finally {
                requestLog.stop();
                removeScanFixture(identity);
            }
        }
    });

    test('anomaly invalid: kode acak ditolak 422 tanpa attendance', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const fixture = buildScanFixture(identity);
        const invalidCode = `TIDAK-VALID-${identity.ownerKey.toUpperCase()}`;
        const requestLog = startRequestLog(page);

        try {
            createScanFixture(identity, fixture);

            await page.goto(SCAN_PATH, { waitUntil: 'domcontentloaded' });
            await expect(page.getByText('Input Manual', { exact: true })).toBeVisible();

            const beforeInvalid = await readKpi(page, 'Tidak valid');
            const response = await submitManualScan(page, invalidCode);

            expect(response.status(), 'kode acak harus 422').toBe(422);

            const hero = heroCard(page);
            await expect(hero.getByText('Tidak dapat diproses', { exact: true })).toBeVisible();
            await expect.poll(() => readKpi(page, 'Tidak valid'), { timeout: 15_000 }).toBe(beforeInvalid + 1);

            expect(attendanceCount(identity.marker), 'kode acak tidak boleh check-in submission lain').toBe(0);
            expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
        } finally {
            try {
                expect(requestLog.serverErrorStatuses(), 'tidak boleh ada response >= 500').toEqual([]);
            } finally {
                requestLog.stop();
                removeScanFixture(identity);
            }
        }
    });
});
