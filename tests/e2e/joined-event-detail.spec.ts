import { expect, test, type Page } from '@playwright/test';
import {
    cleanupJoinedEventDetailFixture,
    createJoinedEventDetailFixture,
    markJoinedEventDetailCheckedIn,
    signInJoinedEventDetailMember,
    type IJoinedEventDetailFixture,
    type IJoinedEventDetailStatusCase,
    type TJoinedEventDetailStatus,
} from './support/joinedEventDetail';

const KEYBOARD_TAB_LIMIT = 25;
const MOBILE_BREAKPOINTS: readonly number[] = [360, 390];

interface IFocusSnapshot {
    label: string;
    tagName: string;
    isCta: boolean;
    focusVisible: boolean;
    boxShadow: string;
}

function requireFixture(fixture: IJoinedEventDetailFixture | null): IJoinedEventDetailFixture {
    if (fixture === null) throw new Error('Joined event detail fixture is not initialized.');
    return fixture;
}

function requireStatusCase(
    fixture: IJoinedEventDetailFixture,
    expectedStatus: TJoinedEventDetailStatus
): IJoinedEventDetailStatusCase {
    const match = fixture.cases.find(
        (item: IJoinedEventDetailStatusCase): boolean => item.expectedStatus === expectedStatus
    );
    if (match === undefined) throw new Error(`Missing joined event detail fixture for status ${expectedStatus}.`);
    return match;
}

/** Login bisa gagal hydration saat iterasi cepat; pastikan halaman detail benar-benar terbuka. */
async function openJoinedDetail(
    page: Page,
    member: IJoinedEventDetailStatusCase
): Promise<void> {
    const target = `/joined/${member.eventSlug}`;

    for (let attempt = 0; attempt < 2; attempt += 1) {
        await signInJoinedEventDetailMember(page, member);
        await page.goto(target);

        if (!new URL(page.url()).pathname.startsWith('/auth/')) return;
    }

    throw new Error(`Gagal membuka ${target} setelah dua percobaan login.`);
}

/** What the document currently has focused, including the computed focus ring. */
async function readFocusSnapshot(page: Page): Promise<IFocusSnapshot> {
    return page.evaluate((): IFocusSnapshot => {
        const active = document.activeElement;

        if (!(active instanceof HTMLElement)) {
            return { label: '', tagName: '', isCta: false, focusVisible: false, boxShadow: 'none' };
        }

        const label = `${active.getAttribute('aria-label') ?? ''} ${active.textContent ?? ''}`.trim();

        return {
            label,
            tagName: active.tagName,
            isCta: /isi form/i.test(label),
            focusVisible: active.matches(':focus-visible'),
            boxShadow: getComputedStyle(active).boxShadow,
        };
    });
}

async function expectNoHorizontalOverflow(page: Page, width: number): Promise<void> {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll((): Promise<number> => page.evaluate((): number => window.innerWidth)).toBe(width);
    await page.waitForSelector('[data-testid="dashboard-content"]', { state: 'attached' });

    const metrics = await page.evaluate(() => {
        const content = document.querySelector('[data-testid="dashboard-content"]');

        return {
            document: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            content: content === null ? Number.NaN : content.scrollWidth - content.clientWidth,
        };
    });

    expect(metrics.document, `document overflow at ${width}px`).toBeLessThanOrEqual(0);
    expect(metrics.content, `content overflow at ${width}px`).toBeLessThanOrEqual(0);
}

test.describe('joined event detail', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    let fixture: IJoinedEventDetailFixture | null = null;

    test.beforeEach(() => {
        fixture = createJoinedEventDetailFixture();
    });

    test.afterEach(() => {
        if (fixture !== null) {
            cleanupJoinedEventDetailFixture(fixture);
            fixture = null;
        }
    });

    test('renders inline registration and removes legacy register URLs', async ({ page }) => {
        const currentFixture = requireFixture(fixture);
        const availableCase = requireStatusCase(currentFixture, 'allowed');

        await signInJoinedEventDetailMember(page, availableCase);
        await page.goto(`/joined/${availableCase.eventSlug}`);

        await expect(page.getByRole('heading', { name: availableCase.eventTitle })).toBeVisible();
        await expect(page.getByText(`${availableCase.eventTitle} form`, { exact: false }).first()).toBeVisible();

        const formLink = page.getByRole('link', { name: /isi form/i });
        await expect(formLink).toBeVisible();
        await formLink.focus();
        await expect(formLink).toBeFocused();

        const removedRoute = await page.request.get(`/joined/${availableCase.eventSlug}/register`, {
            maxRedirects: 0,
        });
        expect(removedRoute.status()).toBe(404);

        const oldRoute = await page.request.get(`/user/dashboard/events/${availableCase.eventSlug}/register`, {
            maxRedirects: 0,
        });
        expect(oldRoute.status()).toBe(404);
    });

    test('renders each FormAccessStatus with text and matching action state', async ({ page }) => {
        test.setTimeout(120_000);

        const currentFixture = requireFixture(fixture);

        const statusLabel: Record<TJoinedEventDetailStatus, string> = {
            allowed: 'Tersedia',
            not_visible: 'Tidak tersedia',
            form_closed: 'Form ditutup',
            registration_not_open: 'Pendaftaran belum buka',
            quota_full: 'Kuota penuh',
            unsupported_registration_mode: 'Belum didukung',
            pending_team_confirmation: 'Menunggu konfirmasi tim',
            prerequisite_not_met: 'Menunggu syarat',
        };

        for (const statusCase of currentFixture.cases) {
            await openJoinedDetail(page, statusCase);

            const formLink = page.getByRole('link', { name: /isi form/i });

            if (statusCase.expectedStatus === 'pending_team_confirmation') {
                // Undangan tim tetap punya prioritas: kartu undangan menggantikan aksi form.
                await expect(page.getByRole('link', { name: /lihat undangan/i })).toBeVisible();
                await expect(formLink).toHaveCount(0);
            } else if (statusCase.expectedStatus === 'allowed') {
                await expect(formLink).toHaveCount(1);
            } else {
                await expect(
                    page.getByText(statusLabel[statusCase.expectedStatus], { exact: true }).first()
                ).toBeVisible();
                await expect(formLink).toHaveCount(0);
            }

            await page.context().clearCookies();
        }
    });

    test('reaches the registration CTA by keyboard after the shell controls', async ({ page }) => {
        const currentFixture = requireFixture(fixture);
        const availableCase = requireStatusCase(currentFixture, 'allowed');

        await signInJoinedEventDetailMember(page, availableCase);
        await page.goto(`/joined/${availableCase.eventSlug}`);

        const formLink = page.getByRole('link', { name: /isi form/i });
        await expect(formLink).toBeVisible();

        const visited: IFocusSnapshot[] = [];
        let snapshot = await readFocusSnapshot(page);

        for (let index = 0; index < KEYBOARD_TAB_LIMIT; index += 1) {
            await page.keyboard.press('Tab');
            snapshot = await readFocusSnapshot(page);
            visited.push(snapshot);

            if (snapshot.isCta) break;
        }

        const visitedLabels = visited
            .map((item: IFocusSnapshot): string => `${item.tagName}:${item.label}`)
            .join(' | ');
        const ctaIndex = visited.findIndex((item: IFocusSnapshot): boolean => item.isCta);

        expect(ctaIndex, `CTA not reached within ${KEYBOARD_TAB_LIMIT} tabs: ${visitedLabels}`).toBeGreaterThan(-1);
        expect(ctaIndex, `CTA must not be the first tab stop: ${visitedLabels}`).toBeGreaterThan(0);
        expect(snapshot.focusVisible, 'CTA must match :focus-visible after keyboard navigation').toBe(true);
        expect(snapshot.boxShadow, 'CTA must render the app focus ring').not.toBe('none');
        await expect(formLink).toBeFocused();
    });

    test('renders the accepted check-in surface with manual code and registration link', async ({ page }) => {
        const currentFixture = requireFixture(fixture);
        const acceptedCase = currentFixture.acceptedCase;

        await signInJoinedEventDetailMember(page, acceptedCase);
        await page.goto(`/joined/${acceptedCase.eventSlug}`);

        await expect(page.getByRole('heading', { name: 'Check-in', exact: true })).toBeVisible();
        await expect(page.getByAltText('Kode QR check-in')).toBeVisible();
        await expect(page.getByText('Kode manual', { exact: true })).toBeVisible();
        await expect(page.getByText(currentFixture.acceptedRegistrationCode, { exact: true })).toBeVisible();

        const detailLink = page.getByRole('link', { name: 'Detail pendaftaran' });
        await expect(detailLink).toBeVisible();
        await expect(detailLink).toHaveAttribute(
            'href',
            `/joined/${acceptedCase.eventSlug}/registration`
        );
    });

    test('hides the QR and manual code after check-in while registration detail keeps the QR', async ({ page }) => {
        const currentFixture = requireFixture(fixture);
        const acceptedCase = currentFixture.acceptedCase;

        await signInJoinedEventDetailMember(page, acceptedCase);
        await page.goto(`/joined/${acceptedCase.eventSlug}`);
        await expect(page.getByAltText('Kode QR check-in')).toBeVisible();

        markJoinedEventDetailCheckedIn(currentFixture);
        await page.reload();

        await expect(page.getByText('Check-in berhasil', { exact: true })).toBeVisible();
        await expect(page.getByAltText('Kode QR check-in')).toHaveCount(0);
        await expect(page.getByText(currentFixture.acceptedRegistrationCode, { exact: true })).toHaveCount(0);
        await expect(page.getByText('Kode manual', { exact: true })).toHaveCount(0);

        await page.goto(`/joined/${acceptedCase.eventSlug}/registration`);
        await expect(page.getByAltText('Attendance QR code')).toBeVisible();
        await expect(page.getByText(currentFixture.acceptedRegistrationCode, { exact: true })).toBeVisible();
    });

    test('keeps the detail inside the viewport on mobile', async ({ page }) => {
        const currentFixture = requireFixture(fixture);
        const acceptedCase = currentFixture.acceptedCase;

        await signInJoinedEventDetailMember(page, acceptedCase);
        await page.goto(`/joined/${acceptedCase.eventSlug}`);

        for (const width of MOBILE_BREAKPOINTS) {
            await expectNoHorizontalOverflow(page, width);
        }
    });
});
