import { expect, test, type Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';

test.use({ storageState: { cookies: [], origins: [] } });

const DRAFT_KEY = 'oprec-apply-draft-v1';

interface IPeriodFixture {
    id: string;
    slug: string;
}

interface IOprecDraftStorage {
    step: number;
    values: Record<string, unknown>;
}

function parseStoredDraft(raw: string | null): IOprecDraftStorage {
    if (raw === null) throw new Error('Draft OPRec tidak ditemukan di localStorage.');
    const parsed: unknown = JSON.parse(raw);
    if (typeof parsed !== 'object' || parsed === null || !('step' in parsed) || !('values' in parsed)) {
        throw new Error('Draft OPRec tidak memakai shape serializer terkini.');
    }
    const record: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(parsed)) record[key] = value;
    if (typeof record.step !== 'number' || typeof record.values !== 'object' || record.values === null) {
        throw new Error('Draft OPRec memiliki shape serializer yang tidak valid.');
    }
    const values: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(record.values)) values[key] = value;
    return { step: record.step, values };
}

async function readDraftStorage(page: Page): Promise<IOprecDraftStorage> {
    const raw = await page.evaluate((key: string): string | null => window.localStorage.getItem(key), DRAFT_KEY);
    return parseStoredDraft(raw);
}

function createOpenPeriod(): IPeriodFixture {
    const marker = `e2e-fe-audit-006-f3-${Date.now()}`;
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        `$period=App\\Models\\Recruitment\\RecruitmentPeriod::factory()->open()->create(['name'=>'${marker}','slug'=>'${marker}']);echo json_encode(['id'=>$period->id,'slug'=>$period->slug]);`,
    ]);
    const parsed: unknown = JSON.parse(output);
    if (typeof parsed !== 'object' || parsed === null || !('id' in parsed) || !('slug' in parsed)) {
        throw new Error('Fixture periode FE-AUDIT-006-F3 tidak valid.');
    }
    const record: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(parsed)) record[key] = value;
    if (typeof record.id !== 'string' || typeof record.slug !== 'string') {
        throw new Error('Fixture periode FE-AUDIT-006-F3 tidak memiliki id/slug.');
    }
    return { id: record.id, slug: record.slug };
}

function removePeriod(fixture: IPeriodFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `App\\Models\\Recruitment\\RecruitmentPeriod::withTrashed()->whereKey('${fixture.id}')->forceDelete();`,
    ]);
}

test.describe('FE-AUDIT-006-F3 OPRec draft restore', () => {
    test('FE_AUDIT_006_F3 restores mixed values and legacy values without silent loss', async ({ page }) => {
        const fixture = createOpenPeriod();
        try {
            await page.addInitScript(
                ({ key, value }: { key: string; value: string }): void => {
                    window.localStorage.setItem(key, value);
                },
                {
                    key: DRAFT_KEY,
                    value: JSON.stringify({
                        step: 3,
                        values: {
                            full_name: 'Draft String',
                            semester: 3,
                            portfolio_type: true,
                            secondary_division_id: ['Draft Array A', 'Draft Array B'],
                        },
                    }),
                }
            );

            await page.goto('/recruitment');
            await expect(page.getByRole('heading', { name: 'Periksa kembali data kamu' })).toBeVisible();
            await expect(page.getByText('Draft String', { exact: true })).toBeVisible();
            await expect(page.getByText('3', { exact: true }).last()).toBeVisible();
            await expect(page.getByText('true', { exact: true })).toBeVisible();
            await expect(page.getByText('Draft Array A, Draft Array B', { exact: true })).toBeVisible();

            await page.getByRole('button', { name: /Data diri/ }).click();
            const editedValue = 'Edited serializer value';
            await page.locator('#full_name').fill(editedValue);
            await expect(page.getByText(/Draft tersimpan otomatis ·/)).toBeVisible();

            const storedDraft = await readDraftStorage(page);
            expect(storedDraft.step).toBe(1);
            expect(storedDraft.values.full_name).toBe(editedValue);

            await page.reload();
            await expect(page.locator('#full_name')).toHaveValue(editedValue);

            await page.evaluate((key: string): void => {
                window.localStorage.setItem(key, JSON.stringify({ values: { full_name: 'Legacy String' } }));
            }, DRAFT_KEY);
            await page.reload();
            await expect(page.getByText('Legacy String', { exact: true })).toBeVisible();
        } finally {
            removePeriod(fixture);
        }
    });
});
