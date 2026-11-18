import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisan, runArtisanCommand } from './support/artisan';
import { readPageProps } from './support/network';
import { createRunId } from './support/marker';

/**
 * Halaman detail pengguna: empat kartu statistik dari server, aksi edit yang
 * membuka panel direktori, dan aksi hapus yang hanya muncul saat server
 * mengizinkannya.
 */
const runId = createRunId();
const THROWAWAY_EMAIL_ROOT = `e2e+${runId}+users`;
const TABLE_ROW = 'tbody [data-testid="user-row"]';
const VIEWPORT_TOLERANCE_PX = 1;
const UUID_PATTERN = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/;

interface IDetailPageProps {
    user: { id: string };
    stats: {
        events_joined: number;
        registrations_accepted: number;
        recruitment_applications: number;
        scans_recorded: number;
    };
    permissions: { can_edit: boolean; can_delete: boolean };
}

/** Kunci `data-stat` kartu beserta kunci `stats` server yang ditampilkannya. */
const STAT_CARD_KEYS: Record<string, keyof IDetailPageProps['stats']> = {
    'events-joined': 'events_joined',
    'registrations-accepted': 'registrations_accepted',
    'recruitment-applications': 'recruitment_applications',
    'scans-recorded': 'scans_recorded',
};

function phpString(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

/** Buat akun sekali pakai lewat tinker dan kembalikan id-nya. */
function createManagedUser(input: { name: string; email: string; role: string }): string {
    const code = [
        `$user = \\App\\Models\\User::query()->create(['name' => ${phpString(input.name)}, 'email' => ${phpString(input.email)}, 'password' => 'password']);`,
        `$user->syncRoles([${phpString(input.role)}]);`,
        'echo $user->id;',
    ].join(' ');

    const output = runArtisanCommand(['tinker', '--execute', code]);
    const match = output.match(UUID_PATTERN);

    if (match === null) {
        throw new Error(`tinker tidak mengembalikan id user: ${output}`);
    }

    return match[0];
}

function deleteThrowawayUsers(): void {
    runArtisan(
        `\\App\\Models\\User::withTrashed()->where('email', 'like', ${phpString(`${THROWAWAY_EMAIL_ROOT}%`)} )->forceDelete();`
    );
}

/** Selisih `scrollWidth - clientWidth` sebuah elemen; `-1` bila elemen tidak ada. */
async function overflowPixels(page: Page, selector: string): Promise<number> {
    return page.evaluate((target: string): number => {
        const element = target === 'document' ? document.scrollingElement : document.querySelector(target);

        if (element === null) return -1;

        return element.scrollWidth - element.clientWidth;
    }, selector);
}

let memberId: string = '';
let memberEmail: string = '';
let superAdminId: string = '';
let superAdminEmail: string = '';

test.describe('detail pengguna (desktop)', () => {
    test.beforeAll((): void => {
        memberEmail = `${THROWAWAY_EMAIL_ROOT}-detail@example.test`;
        superAdminEmail = `${THROWAWAY_EMAIL_ROOT}-super@example.test`;

        memberId = createManagedUser({
            name: `E2E Detail ${runId.slice(-6)}`,
            email: memberEmail,
            role: 'member',
        });
        superAdminId = createManagedUser({
            name: `E2E Super ${runId.slice(-6)}`,
            email: superAdminEmail,
            role: 'super-admin',
        });
    });

    test.afterAll((): void => {
        deleteThrowawayUsers();
    });

    test('menampilkan empat kartu statistik dengan angka dari server', async ({ page }) => {
        await page.goto(`/admin/users/${memberId}`, { waitUntil: 'domcontentloaded' });

        await expect(page.locator('[data-testid="user-detail"]')).toBeVisible();

        const stats = page.locator('[data-testid="user-detail-stat"]');
        await expect(stats).toHaveCount(4);

        for (let index = 0; index < 4; index += 1) {
            await expect(stats.nth(index)).toBeVisible();
        }

        // Tombol kembali dihapus oleh desain; navigasi memakai sidebar.
        await expect(page.locator('[data-testid="user-detail-back"]')).toHaveCount(0);

        const props = await readPageProps<IDetailPageProps>(page);

        for (const [statKey, statsKey] of Object.entries(STAT_CARD_KEYS)) {
            const card = page.locator(`[data-testid="user-detail-stat"][data-stat="${statKey}"]`);

            await expect(card).toBeVisible();
            expect((await card.locator('dd').first().innerText()).trim()).toBe(String(props.stats[statsKey]));
        }
    });

    test('aksi edit membuka panel direktori tanpa memuat form terpisah', async ({ page }) => {
        await page.goto(`/admin/users?search=${encodeURIComponent(memberEmail)}`, {
            waitUntil: 'domcontentloaded',
        });

        const detailHref = await page
            .locator(TABLE_ROW)
            .filter({ hasText: memberEmail })
            .locator('[data-testid="user-row-detail"]')
            .getAttribute('href');

        if (detailHref === null) {
            throw new Error('Baris direktori tidak punya tautan detail.');
        }

        expect(detailHref).toBe(`/admin/users/${memberId}`);

        await page.goto(detailHref, { waitUntil: 'domcontentloaded' });

        const editLink = page.locator('[data-testid="user-detail-edit"]');
        await expect(editLink).toBeVisible();
        await expect(editLink).toHaveAttribute('href', `/admin/users?edit=${memberId}`);

        await editLink.click();

        await expect(page).toHaveURL(new RegExp(`/admin/users\\?edit=${memberId}$`));
        await expect(page.locator('[data-testid="user-panel-title"]')).toHaveText('Edit akun');
        await expect(page.locator('[data-testid="user-field-email"]')).toHaveValue(memberEmail);
    });

    test('aksi hapus hanya ada ketika baris mengizinkan penghapusan', async ({ page }) => {
        await page.goto(`/admin/users?search=${encodeURIComponent(memberEmail)}`, {
            waitUntil: 'domcontentloaded',
        });
        await expect(
            page.locator(TABLE_ROW).filter({ hasText: memberEmail }).locator('[data-testid="user-row-delete"]')
        ).toBeVisible();

        await page.goto(`/admin/users/${memberId}`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('[data-testid="user-detail-delete"]')).toBeVisible();

        // Super-admin tidak boleh diubah atau dihapus, termasuk oleh super-admin lain.
        await page.goto(`/admin/users?search=${encodeURIComponent(superAdminEmail)}`, {
            waitUntil: 'domcontentloaded',
        });

        const superAdminRow = page.locator(TABLE_ROW).filter({ hasText: superAdminEmail });
        await expect(superAdminRow).toBeVisible();
        await expect(superAdminRow.locator('[data-testid="user-row-delete"]')).toHaveCount(0);
        await expect(superAdminRow.locator('[data-testid="user-row-edit"]')).toHaveCount(0);

        await page.goto(`/admin/users/${superAdminId}`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('[data-testid="user-detail-delete"]')).toHaveCount(0);
        await expect(page.locator('[data-testid="user-detail-edit"]')).toHaveCount(0);
    });

    test('tidak ada overflow horizontal pada 360px dan 1440px', async ({ page }) => {
        for (const width of [360, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(`/admin/users/${memberId}`, { waitUntil: 'domcontentloaded' });

            await expect(page.locator('[data-testid="user-detail"]')).toBeVisible();

            expect(await overflowPixels(page, 'document'), `document overflow pada ${width}px`).toBeLessThanOrEqual(
                VIEWPORT_TOLERANCE_PX
            );
            expect(
                await overflowPixels(page, '[data-testid="dashboard-content"]'),
                `dashboard-content overflow pada ${width}px`
            ).toBeLessThanOrEqual(VIEWPORT_TOLERANCE_PX);
        }
    });
});
