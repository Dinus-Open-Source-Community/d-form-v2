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
import { readEnvironment } from './support/environment';

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
 * login). Fixture dibersihkan di `finally`; user admin tidak pernah dihapus.
 *
 * Precondition: test 403 mengasumsikan admin E2E BUKAN `super-admin`, karena
 * `EventPolicy` melewati cek kepemilikan untuk super-admin.
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
}

/** Semua nilai liar harus menghasilkan 200 + shell laporan, bukan error. */
const ANOMALY_CASES: IAnomalyCase[] = [
    { label: 'page=0', query: 'page=0', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'page=-5', query: 'page=-5', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'page=abc', query: 'page=abc', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'page=1 OR 1=1', query: 'page=1%20OR%201=1', expectedRows: PER_PAGE, expectsFirstPage: true },
    // BE-P3-019: paginator belum di-clamp; halaman di luar rentang menghasilkan empty state.
    { label: 'page=999999', query: 'page=999999', expectedRows: 0, expectsFirstPage: false },
    // BE-P3-018: controller mengabaikan `per_page`; kasus ini mengunci perilaku saat ini.
    { label: 'per_page=999999', query: 'per_page=999999', expectedRows: PER_PAGE, expectsFirstPage: true },
    { label: 'per_page=0', query: 'per_page=0', expectedRows: PER_PAGE, expectsFirstPage: true },
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

            // Identitas/urutan baris lintas halaman didefer ke BE-P2-025 (scanned_at identik).
            await page
                .getByRole('navigation', { name: 'pagination' })
                .getByRole('button', { name: 'Halaman berikutnya' })
                .click();

            await expect(page).toHaveURL(/[?&]page=2(?:&|$)/);
            await expect(attendanceRows(page)).toHaveCount(SECOND_PAGE_ROWS);
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
            }
        } finally {
            cleanupOwnerFixture(identity);
        }
    });

    test('admin bukan pemilik event mendapat 403 pada laporan', async ({ page, fixtureEvent }) => {
        test.setTimeout(120_000);

        const response = await page.goto(laporanPath(fixtureEvent.eventId), { waitUntil: 'domcontentloaded' });

        expect(response?.status()).toBe(403);
        await expect(page.getByRole('heading', { name: 'Attendance log' })).toHaveCount(0);
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
