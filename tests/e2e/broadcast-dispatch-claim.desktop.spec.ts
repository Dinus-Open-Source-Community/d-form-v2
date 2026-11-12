import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';

/**
 * E2E untuk modul broadcast (BE-P1-004). Modul ini dorman secara default
 * (`FEATURE_BROADCAST=false`), sehingga spec hanya berjalan bila server
 * dinyalakan dengan flag tersebut dan `E2E_FEATURE_BROADCAST=true`.
 */
const featureEnabled = process.env.E2E_FEATURE_BROADCAST === 'true';

/** Format tanggal/jam Asia/Jakarta untuk input date/time HTML. */
function jakartaDateTime(offsetMinutes: number): { date: string; time: string } {
    const instant = new Date(Date.now() + offsetMinutes * 60_000);

    return {
        date: new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Jakarta',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(instant),
        time: new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(instant),
    };
}

/**
 * Matikan dataset `users` default. Dataset dev berisi 1 juta user dan resolver
 * menahan semua row di memori (AUDIT-P2-009), sehingga snapshot cukup manual rows.
 */
async function disableUsersDataset(page: import('@playwright/test').Page): Promise<void> {
    const usersCheckbox = page.getByRole('checkbox', { name: 'users' });

    if (await usersCheckbox.isChecked()) {
        await usersCheckbox.uncheck();
    }
}

/** Tutup dialog error Inertia (422 non-Inertia response) yang memblokir interaksi. */
async function dismissInertiaErrorDialog(page: import('@playwright/test').Page): Promise<void> {
    const dialog = page.locator('#inertia-error-dialog');

    await expect(dialog).toBeVisible();
    await page.evaluate(() => {
        const element = document.querySelector('#inertia-error-dialog');

        if (element instanceof HTMLDialogElement) {
            element.close();
        }
    });
    await expect(dialog).toHaveCount(0);
}

async function fillCreateForm(
    page: import('@playwright/test').Page,
    name: string,
    schedule: { date: string; time: string },
    delayMin: number,
    delayMax: number
): Promise<void> {
    await page.locator('#name').fill(name);
    await page.locator('#schedule_date').fill(schedule.date);
    await page.locator('#schedule_time').fill(schedule.time);
    await page.locator('#delay_min').fill(String(delayMin));
    await page.locator('#delay_max').fill(String(delayMax));
}

test.describe('BE-P1-004 broadcast dispatch claim', () => {
    test.skip(
        !featureEnabled,
        'Aktifkan FEATURE_BROADCAST=true dan E2E_FEATURE_BROADCAST=true untuk menjalankan spec ini.'
    );

    test('schedule lalu dispatch hanya sekali dan menandai semua recipient sent', async ({ page }) => {
        test.setTimeout(120_000);
        const marker = `BE-P1-004 ${Date.now()}`;
        const due = jakartaDateTime(-10);

        await page.goto('/admin/broadcasts', { waitUntil: 'domcontentloaded' });
        await expect(
            page.getByTestId('dashboard-content').getByRole('heading', { name: 'Email Broadcasting' })
        ).toBeVisible();

        await page.getByRole('link', { name: /Buat Broadcast/ }).click();
        await expect(page).toHaveURL(/\/admin\/broadcasts\/create$/);

        await fillCreateForm(page, marker, due, 0, 0);
        await page.getByRole('button', { name: /Buat & lanjut ke dataset/ }).click();

        await expect(page).toHaveURL(/\/admin\/broadcasts\/[0-9a-f-]{36}$/);
        await expect(page.getByTestId('dashboard-content').getByRole('heading', { name: marker })).toBeVisible();
        await expect(page.getByText('draft', { exact: true })).toBeVisible();

        // Snapshot 2 recipient lewat UI.
        await disableUsersDataset(page);
        await page.locator('textarea').fill('Andi,andia@example.test\nBudi,budib@example.test');
        await page.getByRole('button', { name: 'Generate Snapshot' }).click();
        await expect(page.getByText('Recipients (2)')).toBeVisible();

        // Isi email lalu simpan.
        const subject = `Halo {{name}} — ${marker}`;
        await page.getByPlaceholder('Pengumuman Hasil Seleksi').fill(subject);
        await page.getByText('Konten', { exact: true }).scrollIntoViewIfNeeded();
        const editor = page.locator('.ProseMirror[contenteditable="true"]').first();
        await expect(editor).toBeVisible();
        await editor.fill('Halo {{name}}, ini pesan uji BE-P1-004.');
        await page.getByRole('button', { name: 'Simpan email' }).click();
        await expect(page.getByPlaceholder('Pengumuman Hasil Seleksi')).toHaveValue(subject);

        // Schedule: claim hanya boleh terjadi saat scheduler berjalan.
        const [scheduleResponse] = await Promise.all([
            page.waitForResponse(
                (response) => response.url().includes('/schedule') && response.request().method() === 'POST'
            ),
            page.getByRole('button', { name: 'Schedule Broadcast' }).click(),
        ]);
        expect(scheduleResponse.status()).toBeLessThan(400);

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByText('scheduled', { exact: true })).toBeVisible();

        // Scheduler klaim broadcast, lalu job per-recipient diproses dari queue `broadcasts`.
        const dispatchOutput = runArtisanCommand(['broadcast:dispatch-scheduled']);
        expect(dispatchOutput).toContain('Dispatched ');

        runArtisanCommand([
            'queue:work',
            'redis',
            '--queue=broadcasts',
            '--stop-when-empty',
            '--tries=1',
            '--max-time=60',
        ]);

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByText('completed', { exact: true })).toBeVisible();

        const sentCard = page.locator('div.rounded-xl.border').filter({ has: page.getByText('Sent', { exact: true }) });
        const failedCard = page
            .locator('div.rounded-xl.border')
            .filter({ has: page.getByText('Failed', { exact: true }) });
        await expect(sentCard).toContainText('2');
        await expect(failedCard).toContainText('0');

        // Bukti tidak ada double-send: total_sent tidak melebihi jumlah recipient.
        const stats = runArtisanCommand([
            'tinker',
            '--execute',
            `$b=\\App\\Models\\EmailBroadcast::where('name', '${marker}')->first(); echo $b->total_sent.'/'.$b->total_failed.'/'.$b->recipients()->count();`,
        ]);
        expect(stats).toBe('2/0/2');

        // Bersihkan data uji lewat UI.
        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Hapus', exact: true }).click();
        await expect(page).toHaveURL(/\/admin\/broadcasts$/);
    });

    test('anomali input ditolak tanpa membuat state setengah jadi', async ({ page }) => {
        test.setTimeout(120_000);
        const marker = `BE-P1-004 anomaly ${Date.now()}`;
        const due = jakartaDateTime(-5);

        await page.goto('/admin/broadcasts/create', { waitUntil: 'domcontentloaded' });

        // 1. Submit kosong: error validasi per field, tetap di halaman create.
        await page.getByRole('button', { name: /Buat & lanjut ke dataset/ }).click();
        await expect(page.getByTestId('dashboard-content').getByText('The name field is required.')).toBeVisible();
        await expect(page).toHaveURL(/\/admin\/broadcasts\/create$/);

        // 2. delay_max < delay_min ditolak.
        await fillCreateForm(page, marker, due, 10, 5);
        await page.getByRole('button', { name: /Buat & lanjut ke dataset/ }).click();
        await expect(
            page.getByTestId('dashboard-content').getByText(/delay max field must be greater than or equal to 10/i)
        ).toBeVisible();
        await expect(page).toHaveURL(/\/admin\/broadcasts\/create$/);

        // 3. Data valid: dibuat sebagai draft.
        await page.locator('#delay_min').fill('0');
        await page.locator('#delay_max').fill('0');
        await page.getByRole('button', { name: /Buat & lanjut ke dataset/ }).click();
        await expect(page).toHaveURL(/\/admin\/broadcasts\/[0-9a-f-]{36}$/);

        // 4. Schedule tanpa recipient/subject/content ditolak 422 dan status tetap draft.
        const [scheduleResponse] = await Promise.all([
            page.waitForResponse(
                (response) => response.url().includes('/schedule') && response.request().method() === 'POST'
            ),
            page.getByRole('button', { name: 'Schedule Broadcast' }).click(),
        ]);
        expect(scheduleResponse.status()).toBe(422);
        await dismissInertiaErrorDialog(page);
        await expect(page.getByText('draft', { exact: true })).toBeVisible();

        // 5. Email invalid pada snapshot ditolak dan tidak membuat recipient.
        await disableUsersDataset(page);
        await page.locator('textarea').fill('Nama Tanpa Email\nbukan-email');
        await page.getByRole('button', { name: 'Generate Snapshot' }).click();
        await expect(page.getByText('Gagal generate snapshot.')).toBeVisible();
        await expect(page.getByText('Recipients (0)')).toBeVisible();

        // 6. Duplikat tidak dihapus diam-diam: keduanya tetap tersimpan sebagai recipient.
        await page.locator('textarea').fill('Andi,andia@example.test\nAndi Lagi,andia@example.test');
        await page.getByRole('button', { name: 'Generate Snapshot' }).click();
        await expect(page.getByText('Recipients (2)')).toBeVisible();

        const duplicateRows = runArtisanCommand([
            'tinker',
            '--execute',
            `$b=\\App\\Models\\EmailBroadcast::where('name', '${marker}')->first(); echo $b->recipients()->where('email', 'andia@example.test')->count();`,
        ]);
        expect(duplicateRows).toBe('2');

        runArtisanCommand([
            'tinker',
            '--execute',
            `\\App\\Models\\EmailBroadcast::where('name', '${marker}')->delete();`,
        ]);
    });
});
