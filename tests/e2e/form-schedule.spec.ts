import { runArtisanCommand } from './support/artisan';
import type { Page } from '@playwright/test';
import { readEnvironment } from './support/environment';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';

/**
 * Buka DatePicker jadwal lalu pilih tanggal hari ini dari kalender.
 * Diambil dari popover yang sedang terbuka agar tidak menyentuh kalender picker lain.
 */
async function pickToday(page: Page): Promise<void> {
    await page.locator('#session-date-input').click();
    const popover = page.locator('[data-slot="popover-content"]:visible');
    await expect(popover).toHaveCount(1);
    await popover.locator('[data-today]').click();
    await expect(page.locator('#session-date-input')).not.toContainText('Pilih tanggal');
}

/** Label tanggal hari ini di zona Jakarta, sama dengan format panel jadwal. */
function todayLabel(): string {
    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());
}

test('D-Kelas schedule supports add, persistence, duplicate rejection, and removal', async ({ page }, testInfo) => {
    test.setTimeout(60_000);
    const identity = ownerIdentityForTest(testInfo);
    const owner = createOwnerFixture(identity, readEnvironment().adminEmail);
    const [form] = createFormsFixture(owner, {
        marker: identity.marker,
        slug: identity.slug,
        title: 'D-Kelas schedule fixture',
        forms: [{ title: 'D-Kelas form', fields: [], submissions: 0, purpose: 'd_kelas' }],
    });

    if (form === undefined) {
        throw new Error('e2e:fixture returned no form.');
    }

    const settingsUrl = `/admin/events/${owner.eventId}/forms/${form.formId}?tab=settings`;
    try {
        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'));
        await expect(page.getByRole('heading', { name: 'Jadwal pertemuan' })).toBeVisible();
        // Kartu referral juga tersedia untuk D-Kelas, bukan hanya pendaftaran biasa.
        await expect(page.getByRole('heading', { name: 'Kode referral' })).toBeVisible();
        await expect(page.locator('#session-date-input')).toBeVisible();
        await expect(page.locator('#session-date-input')).toContainText('Pilih tanggal');
        await expect(page.getByText('Belum ada tanggal pertemuan.')).toBeVisible();

        await pickToday(page);
        const addResponsePromise = page.waitForResponse(
            (response) => response.request().method() === 'POST' && response.url().includes('/sessions')
        );
        await page.getByTestId('session-add-button').click();
        const addResponse = await addResponsePromise;
        expect(addResponse.status()).toBeLessThan(400);
        await expect(page.getByText(todayLabel())).toBeVisible();
        await expect(page.getByTestId('session-row')).toHaveCount(1);
        await expect(page.locator('#session-date-input')).toContainText('Pilih tanggal');

        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await expect(page.getByTestId('session-row').filter({ hasText: todayLabel() })).toBeVisible();

        await pickToday(page);
        await page.getByTestId('session-add-button').click();
        await expect(page.getByText('Tanggal ini sudah ada di jadwal.')).toBeVisible();

        const removeResponsePromise = page.waitForResponse(
            (response) => response.request().method() === 'DELETE' && response.url().includes('/sessions/')
        );
        await page.getByTestId('session-remove-button').click();
        const removeResponse = await removeResponsePromise;
        expect(removeResponse.status()).toBeLessThan(400);
        await expect(page.getByText('Belum ada tanggal pertemuan.')).toBeVisible();
        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('Belum ada tanggal pertemuan.')).toBeVisible();
        await expect(page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).resolves.toBe(
            true
        );
    } finally {
        cleanupOwnerFixture(identity);
    }
});

test('registration forms support meeting schedules too', async ({ page }, testInfo) => {
    test.setTimeout(60_000);
    const identity = ownerIdentityForTest(testInfo);
    const owner = createOwnerFixture(identity, readEnvironment().adminEmail);
    const [form] = createFormsFixture(owner, {
        marker: identity.marker,
        slug: identity.slug,
        title: 'Registration schedule fixture',
        forms: [{ title: 'Registration form', fields: [], submissions: 0, purpose: 'registration' }],
    });

    if (form === undefined) {
        throw new Error('e2e:fixture returned no form.');
    }

    const settingsUrl = `/admin/events/${owner.eventId}/forms/${form.formId}?tab=settings`;

    // Fixture membuat form tanpa tanggal tutup; save diblokir validasi header.
    runArtisanCommand([
        'tinker',
        '--execute',
        `Illuminate\\Support\\Facades\\DB::table('forms')->where('id', '${form.formId}')->update(['closed_at' => now()->addDays(30), 'updated_at' => now()]);`,
    ]);

    try {
        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'));

        await expect(page.getByRole('heading', { name: 'Jadwal pertemuan' })).toBeVisible();
        const toggle = page.getByRole('switch', { name: 'Check-in per pertemuan' });
        await expect(toggle).toHaveAttribute('aria-checked', 'false');
        await expect(toggle).toBeEnabled();
        await expect(page.locator('#session-date-input')).toHaveCount(0);

        // Header Save All hanya ada di desktop; di mobile perubahan disimpan autosave.
        const saved = page.waitForResponse(
            (response) =>
                ['PUT', 'POST'].includes(response.request().method()) &&
                new URL(response.url()).pathname === `/admin/events/${owner.eventId}/forms/${form.formId}`
        );

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-checked', 'true');
        await expect(page.locator('#session-date-input')).toBeVisible();

        await pickToday(page);
        await page.getByTestId('session-add-button').click();
        await expect(page.getByTestId('session-row')).toHaveCount(1);
        await expect(page.getByText(todayLabel())).toBeVisible();

        const saveButton = page
            .getByRole('button', { name: /^(Simpan|Save All|Save Form)$/ })
            .filter({ visible: true })
            .first();
        if ((await saveButton.count()) > 0) {
            await saveButton.click();
        }
        expect((await saved).status()).toBeLessThan(400);

        await page.goto(settingsUrl, { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'));
        await expect(page.getByRole('switch', { name: 'Check-in per pertemuan' })).toHaveAttribute(
            'aria-checked',
            'true'
        );
        await expect(page.getByTestId('session-row').filter({ hasText: todayLabel() })).toBeVisible();
    } finally {
        cleanupOwnerFixture(identity);
    }
});
