import type { Page, TestInfo } from '@playwright/test';
import { readEnvironment } from './support/environment';
import type { IOwnerIdentity } from './support/marker';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    defaultFixtureRequest,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';

interface IAdminOwnedForm {
    identity: IOwnerIdentity;
    eventId: string;
    formId: string;
}

/** Form milik admin dengan banyak field agar batas kolom tabel ikut teruji. */
function createAdminOwnedForm(testInfo: TestInfo, fieldCount: number, submissions = 1): IAdminOwnedForm {
    const identity = ownerIdentityForTest(testInfo);
    const ownerEmail = readEnvironment().adminEmail;
    const owner = createOwnerFixture(identity, ownerEmail);
    const forms = createFormsFixture(owner, {
        ...defaultFixtureRequest(identity, 1, fieldCount, submissions),
        ownerEmail,
    });
    const primary = forms[0];

    if (primary === undefined) {
        cleanupOwnerFixture(identity);
        throw new Error('e2e:fixture returned no form.');
    }

    return { identity, eventId: owner.eventId, formId: primary.formId };
}

async function openAnswersTab(page: Page, form: IAdminOwnedForm): Promise<void> {
    await page.goto(`/admin/events/${form.eventId}/forms/${form.formId}?tab=jawaban`, {
        waitUntil: 'domcontentloaded',
    });
    await expect(page.getByRole('table').first()).toBeVisible({ timeout: 20_000 });
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 20_000 }
    );
}

test('tabel tetap dirender saat belum ada jawaban dan empty state berada di dalam tbody', async ({
    page,
}, testInfo) => {
    test.setTimeout(60_000);

    const form = createAdminOwnedForm(testInfo, 1, 0);

    try {
        await page.goto(`/admin/events/${form.eventId}/forms/${form.formId}?tab=jawaban`, {
            waitUntil: 'domcontentloaded',
        });

        const card = page.getByTestId('form-answers-card');
        await expect(card).toBeVisible({ timeout: 20_000 });

        // Header tabel tetap statis seperti saat ada jawaban.
        await expect(card.getByRole('table')).toBeVisible();
        await expect(card.getByRole('columnheader').first()).toBeVisible();
        await expect(card.locator('tbody').getByText('Belum ada jawaban')).toBeVisible();
    } finally {
        cleanupOwnerFixture(form.identity);
    }
});

test('tabel jawaban tidak pernah melewati sepuluh kolom', async ({ page }, testInfo) => {
    test.setTimeout(60_000);

    const form = createAdminOwnedForm(testInfo, 8);

    try {
        await openAnswersTab(page, form);

        const table = page.locator('table').first();

        // Lima kolom tetap (pilih, pengirim, status review, dikirim, aksi) + kolom field,
        // totalnya tidak boleh melebihi sepuluh walau form punya delapan field.
        const headers = table.locator('thead th');
        await expect(headers.first()).toBeVisible();
        expect(await headers.count()).toBeLessThanOrEqual(10);

        await expect(page.getByRole('columnheader', { name: 'Pengirim' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Status review' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Dikirim' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Aksi' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'No.' })).toHaveCount(0);

        // Tabel tidak lagi menjadi penggulung halaman: tidak ada overflow horizontal dokumen.
        const horizontalOverflow = await page.evaluate(
            (): number => document.scrollingElement!.scrollWidth - document.scrollingElement!.clientWidth
        );
        expect(horizontalOverflow).toBeLessThanOrEqual(0);
    } finally {
        cleanupOwnerFixture(form.identity);
    }
});
