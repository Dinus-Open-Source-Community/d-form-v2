import type { Page, TestInfo } from '@playwright/test';
import { runArtisan } from './support/artisan';
import { clearDuCourses, seedDuCourse } from './support/duCourses';
import { readEnvironment } from './support/environment';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';

const SEEDED_UID_PREFIX = 'e2e-du-class-ui-';
const COURSES = [
    { uid: `${SEEDED_UID_PREFIX}db`, title: 'Database Design dan SQL' },
    { uid: `${SEEDED_UID_PREFIX}devops`, title: 'DevOps Essentials' },
    { uid: `${SEEDED_UID_PREFIX}rest`, title: 'REST API Development' },
];

interface IOwnedForm {
    identity: ReturnType<typeof ownerIdentityForTest>;
    eventId: string;
    formId: string;
}

async function waitForHydration(page: Page): Promise<void> {
    await page.waitForFunction(
        (): boolean => Reflect.has(document.querySelector('#app') ?? {}, '__vue_app__'),
        undefined,
        { timeout: 30_000 }
    );
}

/**
 * Field kelas sistem dengan katalog DU: opsi berasal dari `du_catalog`, bukan
 * snapshot opsi biasa, sehingga halaman isi harus memakai kartu centang.
 */
function prepareClassField(formId: string): void {
    const metadata = {
        system: true,
        locked: true,
        options_source: 'du_catalog',
        duplicatable: true,
        selected_course_uids: COURSES.map((course) => course.uid),
        rules: { required: true },
    };

    runArtisan(`
        \\Illuminate\\Support\\Facades\\DB::table('forms')
            ->where('id', '${formId}')
            ->update(['closed_at' => now()->addDays(30), 'updated_at' => now()]);

        \\Illuminate\\Support\\Facades\\DB::table('form_fields')
            ->where('form_id', '${formId}')
            ->where('name', 'du_course_uid')
            ->update([
                'input_type' => 'selectInput',
                'metadata' => ${JSON.stringify(JSON.stringify(metadata))},
                'updated_at' => now(),
            ]);
    `);
}

function createOwnedForm(testInfo: TestInfo): IOwnedForm {
    const identity = ownerIdentityForTest(testInfo);
    const owned = createOwnerFixture(identity, readEnvironment().adminEmail);

    const [form] = createFormsFixture(owned, {
        marker: identity.marker,
        slug: identity.slug,
        title: `E2E fixture ${identity.ownerKey}`,
        forms: [
            {
                title: 'Form D-Kelas',
                purpose: 'd_kelas',
                fields: [{ label: 'Kelas', name: 'du_course_uid', input_type: 'selectInput', required: true }],
                submissions: 0,
            },
        ],
    });

    if (form === undefined) {
        cleanupOwnerFixture(identity);
        throw new Error('e2e:fixture tidak mengembalikan form.');
    }

    return { identity, eventId: owned.eventId, formId: form.formId };
}

test.describe('Halaman isi: pilihan kelas DU', () => {
    test('kelas tampil sebagai kartu centang, bukan dropdown, dan hanya satu yang terpilih', async ({
        page,
    }, testInfo) => {
        const owned = createOwnedForm(testInfo);

        try {
            COURSES.forEach((course) => seedDuCourse(course.uid, course.title));
            prepareClassField(owned.formId);

            await page.goto(`/joined/${owned.eventId}/forms/${owned.formId}/fill`, {
                waitUntil: 'domcontentloaded',
            });
            await waitForHydration(page);

            const group = page.getByRole('radiogroup', { name: 'Kelas' });
            await expect(group).toBeVisible();
            await expect(group.locator('select')).toHaveCount(0);

            for (const course of COURSES) {
                await expect(group.getByText(course.title, { exact: true })).toBeVisible();
                // UID tidak boleh tampil ke pengisi form.
                await expect(group.getByText(course.uid, { exact: false })).toHaveCount(0);
            }

            const devops = page.locator('input[type="radio"][value="DevOps Essentials"]');
            const database = page.locator('input[type="radio"][value="Database Design dan SQL"]');

            await group.getByText('DevOps Essentials', { exact: true }).click();
            await expect(devops).toBeChecked();
            await expect(database).not.toBeChecked();

            await group.getByText('Database Design dan SQL', { exact: true }).click();
            await expect(database).toBeChecked();
            await expect(devops).not.toBeChecked();

            await page.screenshot({ path: 'test-results/du-class-checkcards.png', fullPage: false });
        } finally {
            try {
                clearDuCourses(SEEDED_UID_PREFIX);
            } finally {
                cleanupOwnerFixture(owned.identity);
            }
        }
    });
});
