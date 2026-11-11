import type { Locator, Page } from '@playwright/test';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    defaultFixtureRequest,
    expect,
    ownerIdentityForTest,
    test,
    type IFixtureOwner,
} from './support/fixtures';
import type { IOwnerIdentity } from './support/marker';
import { E2eEnvironmentError, readEnvironment } from './support/environment';
import { assertBrowserContextAuthenticatedAs, loginE2eUser } from './support/authentication';

/**
 * E2E BE-P2-006: halaman laporan kehadiran event (`dashboard.events.laporan`).
 *
 * Fixture memakai 25 form dengan 1 submission per form sehingga laporan punya 25
 * attendance di event yang sama. Satu form dengan 25 submission akan melanggar
 * unique key `form_answers_active_user_form_unique` (satu answer aktif per user+form).
 *
 * Happy path membuktikan paginator 20/halaman lewat UI: 20 baris di halaman 1, 5 di
 * halaman 2, total 25. Input anomali `page`/`per_page` wajib tetap render laporan,
 * bukan 500. Otorisasi diuji pada event milik user lain (403) dan guest (redirect
 * login). Test 403 memakai admin seeded non-super-admin; fixture dibersihkan di
 * `finally`, sedangkan user admin seeded tidak pernah dihapus.
 *
 * `EventPolicy` melewati cek kepemilikan untuk super-admin, jadi aktor 403 harus
 * dibuktikan lewat sesi admin non-super-admin yang terisolasi dari state project.
 */

const ATTENDANCE_RECORDS = 25;
const PER_PAGE = 20;
const SECOND_PAGE_ROWS = ATTENDANCE_RECORDS - PER_PAGE;

interface IAnomalyCase {
    label: string;
    query: string;
    expectedRows: number;
    /** `true` berarti nilai tidak valid harus resolve ke halaman 1. */
    expectsFirstPage: boolean;
    /** `true` berarti halaman di luar rentang harus di-clamp ke halaman terakhir. */
    expectsLastPage?: boolean;
}

/** Semua nilai liar harus menghasilkan 200 + shell laporan, bukan error. */
const ANOMALY_CASES: IAnomalyCase[] = [
    { label: 'page=0', query: 'page=0', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'page=-5', query: 'page=-5', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'page=abc', query: 'page=abc', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'page=1 OR 1=1', query: 'page=1%20OR%201=1', expectedRows: PER_PAGE, expectsFirstPage: true },
    // BE-P3-019: halaman di luar rentang di-clamp ke halaman terakhir.
    {
        label: 'page=999999',
        query: 'page=999999',
        expectedRows: SECOND_PAGE_ROWS,
        expectsFirstPage: false,
        expectsLastPage: true,
    },
    // BE-P3-018: `per_page` di luar allowlist jatuh ke 20.
    { label: 'per_page=99999', query: 'per_page=99999', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'per_page=abc', query: 'per_page=abc', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'per_page=0', query: 'per_page=0', expectedRows: PER_PAGE, expectsFirstPage: true },
    // BE-P3-018: nilai allowlist benar-benar dipakai.
    { label: 'per_page=8', query: 'per_page=8', expectedRows: 8, expectsFirstPage: true },
    { label: 'per_page=100', query: 'per_page=100', expectedRows: ATTENDANCE_RECORDS, expectsFirstPage: false },
];

function laporanPath(eventId: string, query = ''): string {
    const path = `/admin/events/${eventId}/laporan`;

    return query === '' ? path : `${path}?${query}`;
}

/**
 * Buat event milik admin plus `formCount` form, masing-masing 1 field dan 1 submission.
 * 25 form × 1 attendance, bukan 1 form × 25 submission (unique key user+form).
 */
function createAdminOwnedAttendanceFixture(identity: IOwnerIdentity, formCount: number): IFixtureOwner {
    const ownerEmail = readEnvironment().adminEmail;
    const owner = createOwnerFixture(identity, ownerEmail);

    if (formCount > 0) {
        createFormsFixture(owner, {
            ...defaultFixtureRequest(identity, formCount, 1, 1),
            ownerEmail,
        });
    }

    return owner;
}

/** Baris tabel di-scope ke Card "Attendance log" agar tidak menangkap tabel lain. */
function attendanceRows(page: Page): Locator {
    return page
        .locator('[data-slot="card"]')
        .filter({ has: page.getByRole('heading', { name: 'Attendance log' }) })
        .locator('tbody tr');
}

/** Shell laporan wajib terlihat dan tidak ada halaman error di mana pun. */
async function expectReportShell(page: Page): Promise<void> {
    await expect(page.getByRole('heading', { name: 'Attendance log' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'pagination' })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Whoops');
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
}

test.describe('BE-P2-006 laporan kehadiran event', () => {
    // Satu test memakai akun admin non-super-admin; `global.setup.ts` sengaja tidak
    // mewajibkan `E2E_NON_ADMIN_*` untuk suite yang hanya butuh akun admin, jadi
    // spec ini yang gagal cepat dengan pesan variabel yang jelas.
    test.beforeAll((): void => {
        const environment = readEnvironment();

        if (environment.nonAdminEmail === '' || environment.nonAdminPassword === '') {
            throw new E2eEnvironmentError(
                'E2E_NON_ADMIN_EMAIL and E2E_NON_ADMIN_PASSWORD',
                'must both be set to the seeded non-super-admin (role `admin`) credentials for this spec.'
            );
        }
    });

    test('happy path: halaman 1 berisi 20 baris, halaman 2 berisi 5 baris, total 25', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());

        try {
            const owner = createAdminOwnedAttendanceFixture(identity, ATTENDANCE_RECORDS);
            const response = await page.goto(laporanPath(owner.eventId), { waitUntil: 'domcontentloaded' });

            expect(response?.status()).toBe(200);
            await expectReportShell(page);

            await expect(attendanceRows(page)).toHaveCount(PER_PAGE);
            await expect(page.getByText(/1[–-]20 dari 25/)).toBeVisible();

            const csvLink = page.getByRole('link', { name: /Attendance CSV/ });
            await expect(csvLink).toBeVisible();
            await expect(csvLink).toHaveAttribute(
                'href',
                new RegExp(`/admin/events/${owner.eventId}/exports/attendance\\.csv$`)
            );

            // BE-P2-025: tie-breaker `id` membuat dua halaman tidak berbagi baris.
            const firstPageSubmissions = await attendanceRows(page).locator('td:nth-child(3)').allInnerTexts();

            await page
                .getByRole('navigation', { name: 'pagination' })
                .getByRole('button', { name: 'Halaman berikutnya' })
                .click();

            await expect(page).toHaveURL(/[?&]page=2(?:&|$)/);
            await expect(attendanceRows(page)).toHaveCount(SECOND_PAGE_ROWS);

            const secondPageSubmissions = await attendanceRows(page).locator('td:nth-child(3)').allInnerTexts();

            expect(firstPageSubmissions).toHaveLength(PER_PAGE);
            expect(secondPageSubmissions).toHaveLength(SECOND_PAGE_ROWS);
            expect(
                secondPageSubmissions.filter((submissionId: string): boolean =>
                    firstPageSubmissions.includes(submissionId)
                )
            ).toEqual([]);
        } finally {
            cleanupOwnerFixture(identity);
        }
    });

    test('input anomali page dan per_page tetap merender laporan tanpa error', async ({ page }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());

        try {
            const owner = createAdminOwnedAttendanceFixture(identity, ATTENDANCE_RECORDS);

            for (const anomaly of ANOMALY_CASES) {
                const response = await page.goto(laporanPath(owner.eventId, anomaly.query), {
                    waitUntil: 'domcontentloaded',
                });

                expect(response?.status(), `${anomaly.label}: status`).toBe(200);
                await expectReportShell(page);

                if (anomaly.expectedRows === 0) {
                    await expect(page.getByText('No attendance records yet.')).toBeVisible();
                    continue;
                }

                await expect(attendanceRows(page), `${anomaly.label}: jumlah baris`).toHaveCount(anomaly.expectedRows);

                if (anomaly.expectsFirstPage) {
                    await expect(
                        page.getByRole('button', { name: 'Halaman 1', exact: true }),
                        `${anomaly.label}: harus resolve ke halaman 1`
                    ).toHaveAttribute('aria-current', 'page');
                }

                if (anomaly.expectsLastPage === true) {
                    await expect(
                        page.getByRole('button', { name: 'Halaman 2', exact: true }),
                        `${anomaly.label}: harus resolve ke halaman terakhir`
                    ).toHaveAttribute('aria-current', 'page');
                }
            }
        } finally {
            cleanupOwnerFixture(identity);
        }
    });

    test('admin non-super-admin bukan pemilik event mendapat 403 pada laporan', async ({ browser, fixtureEvent }) => {
        test.setTimeout(120_000);

        const environment = readEnvironment();
        const state = await loginE2eUser(environment.nonAdminEmail, environment.nonAdminPassword);
        const context = await browser.newContext({ baseURL: environment.baseUrl, storageState: state });

        try {
            await assertBrowserContextAuthenticatedAs(context, environment.nonAdminEmail);
            const page = await context.newPage();
            const response = await page.goto(laporanPath(fixtureEvent.eventId), { waitUntil: 'domcontentloaded' });

            expect(response?.status()).toBe(403);
            await expect(page.getByRole('heading', { name: 'Attendance log' })).toHaveCount(0);
        } finally {
            await context.close();
        }
    });

    test('guest tanpa sesi diarahkan ke halaman login', async ({ browser }) => {
        test.setTimeout(120_000);

        const identity = ownerIdentityForTest(test.info());
        const environment = readEnvironment();

        try {
            const owner = createAdminOwnedAttendanceFixture(identity, 0);
            const context = await browser.newContext({
                baseURL: environment.baseUrl,
                storageState: { cookies: [], origins: [] },
            });

            try {
                const page = await context.newPage();
                await page.goto(laporanPath(owner.eventId), { waitUntil: 'domcontentloaded' });

                await expect(page).toHaveURL(/\/auth\/login/);
                await expect(page.locator('#login-email')).toBeVisible();
            } finally {
                await context.close();
            }
        } finally {
            cleanupOwnerFixture(identity);
        }
    });
});
