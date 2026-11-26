import type { Page, Request, Response } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisan, runArtisanCommand } from './support/artisan';
import { createRunId } from './support/marker';

/**
 * Direktori pengguna `/admin/users` di desktop: daftar, panel inline, filter,
 * pagination, dan penghapusan. Semua akun uji memakai awalan
 * `e2e+<runId>+users` supaya cleanup hanya menghapus milik spec ini.
 */
const runId = createRunId();
const THROWAWAY_EMAIL_ROOT = `e2e+${runId}+users`;
const TABLE_ROW = 'tbody [data-testid="user-row"]';
const PANEL = '[data-testid="user-panel"]';
const SEARCH_INPUT = '[data-testid="user-search"]';
/** Debounce 350 ms plus round-trip Inertia. */
const SEARCH_SETTLE_MS = 1_500;
const UUID_PATTERN = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/;

function throwawayEmail(suffix: string): string {
    return `${THROWAWAY_EMAIL_ROOT}-${suffix}@example.test`;
}

/** String literal PHP di dalam kode tinker; argumen tidak melewati shell. */
function phpString(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

/**
 * Buat akun sekali pakai lewat tinker dan kembalikan id-nya.
 * Akun ini lebih baru dari seluruh seed sehingga muncul di halaman pertama.
 */
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

function renameManagedUser(email: string, name: string): void {
    runArtisan(
        `\\App\\Models\\User::query()->where('email', ${phpString(email)})->update(['name' => ${phpString(name)}]);`
    );
}

/** Hapus permanen setiap akun yang dibuat spec ini (termasuk yang sudah soft delete). */
function deleteThrowawayUsers(): void {
    runArtisan(
        `\\App\\Models\\User::withTrashed()->where('email', 'like', ${phpString(`${THROWAWAY_EMAIL_ROOT}%`)} )->forceDelete();`
    );
}

/** Baris tabel yang memuat email tertentu; tabel hanya tampil pada >= 1024px. */
function tableRowByEmail(page: Page, email: string) {
    return page.locator(TABLE_ROW).filter({ hasText: email });
}

/** Tunggu respons Inertia untuk path direktori; `search` opsional mempersempit visit. */
async function waitForDirectoryVisit(page: Page, search?: string): Promise<Response> {
    return page.waitForResponse((candidate) => {
        const url = new URL(candidate.url());

        return (
            candidate.request().method() === 'GET' &&
            candidate.request().headers()['x-inertia'] === 'true' &&
            url.pathname === '/admin/users' &&
            (search === undefined || url.searchParams.get('search') === search)
        );
    });
}

/** Isi pencarian lalu tunggu visit yang benar-benar membawa kata kunci itu. */
async function applySearch(page: Page, term: string): Promise<void> {
    const visit = waitForDirectoryVisit(page, term);

    await page.locator(SEARCH_INPUT).fill(term);
    await visit;

    await expect(page.locator('[data-testid="user-directory-skeleton"]')).toHaveCount(0);
}

/**
 * Hitung visit Inertia ke `/admin/users` (header `X-Inertia`), bukan dokumen
 * awal atau aset, sehingga hitungan ini mengukur debounce pencarian saja.
 */
function startDirectoryVisitCount(page: Page): () => number {
    let count = 0;
    const onRequest = (request: Request): void => {
        if (request.method() !== 'GET' || request.headers()['x-inertia'] !== 'true') return;
        if (new URL(request.url()).pathname !== '/admin/users') return;

        count += 1;
    };

    page.on('request', onRequest);

    return (): number => count;
}

test.describe('direktori pengguna (desktop)', () => {
    test.afterAll((): void => {
        deleteThrowawayUsers();
    });

    test('daftar render dan panel edit terbuka tanpa navigasi', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

        await expect(page.locator('[data-testid="user-result-count"]')).toContainText(/Menampilkan/);
        expect(await page.locator(TABLE_ROW).count()).toBeGreaterThan(0);

        const editableRow = page
            .locator(TABLE_ROW)
            .filter({ has: page.locator('[data-testid="user-row-edit"]') })
            .first();

        await expect(editableRow).toBeVisible();
        await editableRow.locator('[data-testid="user-row-edit"]').click();

        await expect(page.locator('[data-testid="user-panel-title"]')).toHaveText('Edit akun');
        await expect(page.locator(PANEL)).toBeVisible();

        const openedUrl = new URL(page.url());
        expect(openedUrl.pathname).toBe('/admin/users');
        expect(openedUrl.searchParams.get('edit')).toMatch(new RegExp(`^${UUID_PATTERN.source}$`));

        await page.locator('[data-testid="user-panel-cancel"]').click();
        await expect(page.locator(PANEL)).toBeHidden();
        expect(new URL(page.url()).searchParams.get('edit')).toBeNull();
    });

    test('menyimpan edit memperbarui baris dan mempertahankan filter pencarian', async ({ page }) => {
        const email = throwawayEmail('edit');
        const originalName = `E2E Ubah ${runId.slice(-6)}`;
        const updatedName = `${originalName} diperbarui`;

        createManagedUser({ name: originalName, email, role: 'member' });

        await page.goto(`/admin/users?search=${encodeURIComponent(email)}`, { waitUntil: 'domcontentloaded' });

        const row = tableRowByEmail(page, email);
        await expect(row).toBeVisible();
        await expect(row.locator('[data-testid="user-row-name"]')).toHaveText(originalName);

        await row.locator('[data-testid="user-row-edit"]').click();
        await expect(page.locator('[data-testid="user-panel-title"]')).toHaveText('Edit akun');

        await page.locator('[data-testid="user-field-name"]').fill(updatedName);
        await page.locator('[data-testid="user-panel-submit"]').click();

        await expect(page.locator(PANEL)).toBeHidden();
        await expect(page.locator('[data-sonner-toast]').filter({ hasText: 'Akun berhasil diperbarui.' })).toBeVisible();

        await expect
            .poll((): string | null => new URL(page.url()).searchParams.get('search'))
            .toBe(email);

        const savedUrl = new URL(page.url());
        expect(savedUrl.pathname).toBe('/admin/users');

        await expect(tableRowByEmail(page, email).locator('[data-testid="user-row-name"]')).toHaveText(updatedName);

        renameManagedUser(email, originalName);
    });

    test('membuat akun baru menampilkan toast dan baris hasil pencarian', async ({ page }) => {
        const email = `${THROWAWAY_EMAIL_ROOT}@example.test`;
        const name = `E2E Buat ${runId.slice(-6)}`;

        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await page.locator('[data-testid="user-create-trigger"]').click();

        await expect(page.locator('[data-testid="user-panel-title"]')).toHaveText('Buat akun baru');
        await page.locator('[data-testid="user-field-name"]').fill(name);
        await page.locator('[data-testid="user-field-email"]').fill(email);
        await page.locator('[data-testid="user-field-password"]').fill('rahasia123');
        await page.locator('[data-testid="user-field-password-confirmation"]').fill('rahasia123');
        await page.locator('[data-testid="user-role-option"][data-role="member"]').click();

        await page.locator('[data-testid="user-panel-submit"]').click();

        await expect(page.locator(PANEL)).toBeHidden();
        await expect(page.locator('[data-sonner-toast]').filter({ hasText: 'Akun berhasil dibuat.' })).toBeVisible();

        await applySearch(page, email);

        const createdRow = tableRowByEmail(page, email);
        await expect(createdRow).toBeVisible();
        await expect(createdRow.locator('[data-testid="user-row-name"]')).toHaveText(name);
        await expect(createdRow.locator('[data-testid="user-row-role"]')).toHaveText('Member');
    });

    test('submit create kosong diblokir validasi klien', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

        const postRequests: string[] = [];
        page.on('request', (request: Request): void => {
            if (request.method() === 'POST' && new URL(request.url()).pathname === '/admin/users') {
                postRequests.push(request.url());
            }
        });

        await page.locator('[data-testid="user-create-trigger"]').click();

        const panel = page.locator(PANEL);
        await expect(panel).toBeVisible();
        await expect(page.locator('[data-testid="user-panel-title"]')).toHaveText('Buat akun baru');
        await expect(panel).toContainText('Wajib diisi');

        await page.locator('[data-testid="user-panel-submit"]').click();

        const alerts = panel.locator('p[role="alert"]');
        await expect(alerts).toHaveCount(4);
        await expect(alerts.filter({ hasText: 'Nama wajib diisi.' })).toBeVisible();
        await expect(alerts.filter({ hasText: 'Email wajib diisi.' })).toBeVisible();
        await expect(alerts.filter({ hasText: /^Password wajib diisi\.$/ })).toBeVisible();
        await expect(alerts.filter({ hasText: 'Ulangi password wajib diisi.' })).toBeVisible();

        await expect(page.locator('[data-testid="user-field-name"]')).toBeFocused();

        // Validasi berhenti di klien: panel tetap terbuka dan tidak ada POST sama sekali.
        await page.waitForTimeout(500);
        expect(postRequests).toHaveLength(0);
        await expect(panel).toBeVisible();
    });

    test('mengetik cepat hanya mengirim satu request pencarian', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
        await expect(page.locator(TABLE_ROW).first()).toBeVisible();

        const visitCount = startDirectoryVisitCount(page);

        // Empat ketikan dalam ±160 ms: jauh di bawah debounce 350 ms.
        await page.locator(SEARCH_INPUT).pressSequentially('zzq1', { delay: 40 });
        await page.waitForTimeout(SEARCH_SETTLE_MS);

        expect(visitCount()).toBe(1);
        expect(new URL(page.url()).searchParams.get('search')).toBe('zzq1');
    });

    test('chip role memfilter daftar dan bisa dikembalikan ke semua role', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

        await page.locator('[data-testid="user-role-filter"][data-role="member"]').click();
        await expect
            .poll((): string | null => new URL(page.url()).searchParams.get('role'))
            .toBe('member');

        const roleCells = page.locator('tbody [data-testid="user-row-role"]');
        await expect(roleCells.first()).toBeVisible();

        const filteredRowCount = await roleCells.count();
        expect(filteredRowCount).toBeGreaterThan(0);

        for (let index = 0; index < filteredRowCount; index += 1) {
            expect((await roleCells.nth(index).innerText()).trim()).toBe('Member');
        }

        await page.locator('[data-testid="user-role-filter"][data-role=""]').click();
        await expect
            .poll((): string | null => new URL(page.url()).searchParams.get('role'))
            .toBeNull();
        await expect(page.locator('[data-testid="user-role-filter"][data-role=""]')).toHaveAttribute(
            'aria-pressed',
            'true'
        );
    });

    test('pagination pindah halaman dan mengganti isi baris', async ({ page }) => {
        await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });

        const pageTwoButton = page.getByRole('button', { name: 'Halaman 2' });
        await expect(pageTwoButton).toBeVisible();

        const firstRowEmailBefore = await page.locator('tbody [data-testid="user-row-email"]').first().innerText();

        const visit = page.waitForResponse((candidate) => {
            const url = new URL(candidate.url());

            return (
                candidate.request().method() === 'GET' &&
                candidate.request().headers()['x-inertia'] === 'true' &&
                url.pathname === '/admin/users' &&
                url.searchParams.get('page') === '2'
            );
        });

        await pageTwoButton.click();
        await visit;

        await expect.poll((): string | null => new URL(page.url()).searchParams.get('page')).toBe('2');

        const firstRowEmailAfter = await page.locator('tbody [data-testid="user-row-email"]').first().innerText();
        expect(firstRowEmailAfter).not.toBe(firstRowEmailBefore);
        await expect(page.getByRole('button', { name: 'Halaman 2' })).toHaveAttribute('aria-current', 'page');
    });

    test('menghapus akun menghilangkan baris dan menampilkan toast', async ({ page }) => {
        const email = throwawayEmail('hapus');

        const userId = createManagedUser({ name: `E2E Hapus ${runId.slice(-6)}`, email, role: 'member' });

        const deleteRequests: string[] = [];
        page.on('request', (request: Request): void => {
            if (request.method() === 'DELETE' && new URL(request.url()).pathname === `/admin/users/${userId}`) {
                deleteRequests.push(request.url());
            }
        });

        await page.goto(`/admin/users?search=${encodeURIComponent(email)}`, { waitUntil: 'domcontentloaded' });

        const row = tableRowByEmail(page, email);
        await expect(row).toBeVisible();

        await row.locator('[data-testid="user-row-delete"]').click();

        const dialog = page.getByRole('alertdialog');
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Hapus akun' }).click();

        // Konfirmasi harus mengirim DELETE; tanpa request ini baris tidak mungkin hilang.
        await page.waitForTimeout(500);
        expect(deleteRequests, 'konfirmasi hapus tidak mengirim request DELETE').toHaveLength(1);

        await expect(page.locator('[data-sonner-toast]').filter({ hasText: 'Akun berhasil dihapus.' })).toBeVisible();
        await expect(page.getByText(email)).toHaveCount(0);

        // Bukti tambahan: akun benar-benar hilang dari server, bukan hanya dari layar.
        await page.goto(`/admin/users?search=${encodeURIComponent(email)}`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('[data-testid="user-directory-empty"]')).toBeVisible();
    });

    test('halaman create dan edit lama menjawab 404', async ({ page }) => {
        const userId = createManagedUser({
            name: `E2E Rute ${runId.slice(-6)}`,
            email: throwawayEmail('rute'),
            role: 'member',
        });

        const createResponse = await page.request.get('/admin/users/create', { maxRedirects: 0 });
        expect(createResponse.status()).toBe(404);

        const editResponse = await page.request.get(`/admin/users/${userId}/edit`, { maxRedirects: 0 });
        expect(editResponse.status()).toBe(404);
    });
});
