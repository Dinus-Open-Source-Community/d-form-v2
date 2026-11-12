import { request as playwrightRequest, type Locator, type Page, type TestInfo } from '@playwright/test';
import {
    cleanupOwnerFixture,
    createFormsFixture,
    createOwnerFixture,
    expect,
    ownerIdentityForTest,
    test,
} from './support/fixtures';
import { readEnvironment } from './support/environment';
import { runArtisanCommand } from './support/artisan';
import type { IFixtureFormResult } from './support/fixtures';
import type { IOwnerIdentity } from './support/marker';

const environment = readEnvironment();

const VALID_PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPkqjjBAANMDEgANwcAQLoBUlac4vkAAAAASUVORK5CYII=',
    'base64'
);
const PDF = Buffer.from('%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n');

type TUploadBuilderType = 'image_upload' | 'file_upload';

interface IUploadFixtureOptions {
    builderType: TUploadBuilderType;
    multiple: boolean;
    allowedMimeTypes: string[];
    maxFiles?: number;
    maxTotalSizeKb?: number;
}

interface IUploadFixture {
    identity: IOwnerIdentity;
    eventId: string;
    form: IFixtureFormResult;
    fillUrl: string;
    showUrl: string;
}

interface IStoredUploadAnswer {
    answerId: string;
    value: string | string[] | null;
    paths: string[];
    localExists: boolean[];
    publicExists: boolean[];
}

function assertE2eCredentials(): void {
    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD must be set for multi-upload E2E.');
    }
}

function phpString(value: string): string {
    return value.replaceAll('\\', '\\\\').replaceAll("'", "\\'");
}

function extensionsFor(mimeTypes: readonly string[]): string {
    const extensions: Record<string, string> = {
        'image/jpeg': 'jpg',
        'image/png': 'png',
        'image/webp': 'webp',
        'image/gif': 'gif',
        'application/pdf': 'pdf',
        'text/csv': 'csv',
        'text/plain': 'txt',
    };

    return mimeTypes.map((mimeType: string) => extensions[mimeType] ?? mimeType).join(',');
}

function createUploadFixture(testInfo: TestInfo, options: IUploadFixtureOptions): IUploadFixture {
    assertE2eCredentials();

    const identity = ownerIdentityForTest(testInfo);
    const owner = createOwnerFixture(identity, environment.adminEmail);
    const forms = createFormsFixture(owner, {
        marker: identity.marker,
        slug: identity.slug,
        title: `E2E multi upload ${identity.ownerKey}`,
        ownerEmail: environment.adminEmail,
        forms: [
            {
                title: 'Multi upload form',
                fields: [{ label: 'Upload files', name: 'uploads', input_type: 'fileUpload', required: true }],
                submissions: 0,
            },
        ],
    });
    const form = forms[0];

    if (form === undefined) {
        cleanupOwnerFixture(identity);
        throw new Error('e2e:fixture returned no multi-upload form.');
    }

    const metadata: Record<string, unknown> = {
        marker: identity.marker,
        purpose: 'other',
        builderType: options.builderType,
        multiple: options.multiple,
        allowed_mime_types: options.allowedMimeTypes,
        accepts: extensionsFor(options.allowedMimeTypes),
        rules: {
            required: true,
            mimes: extensionsFor(options.allowedMimeTypes),
            max_size: 5120,
        },
    };

    if (options.multiple) {
        metadata.max_files = options.maxFiles ?? 5;
        metadata.max_total_size_kb = options.maxTotalSizeKb ?? 20 * 1024;
    }

    const metadataJson = phpString(JSON.stringify(metadata));
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        `$form=App\\Models\\Form::find('${phpString(form.formId)}');` +
            `$form->update(['title'=>'Multi upload','metadata'=>json_decode('${metadataJson}',true)]);` +
            `$field=App\\Models\\FormField::find('${phpString(form.fieldIds[0] ?? '')}');` +
            `$field->update(['label'=>'Upload files','metadata'=>json_decode('${metadataJson}',true)]);` +
            `echo json_encode(['fillUrl'=>route('dashboard.events.forms.fill',['event'=>$form->event,'form'=>$form],false),'showUrl'=>route('dashboard.events.forms.show',['event'=>$form->event,'form'=>$form],false)]);`,
    ]);
    const routes = JSON.parse(output) as { fillUrl: string; showUrl: string };

    return { identity, eventId: owner.eventId, form, ...routes };
}

function cleanupUploadFixture(fixture: IUploadFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `\Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('form-uploads/${fixture.form.formId}');` +
            `\Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory('form-uploads/${fixture.form.formId}');`,
    ]);
    cleanupOwnerFixture(fixture.identity);
}

function storedUploadAnswer(fixture: IUploadFixture): IStoredUploadAnswer {
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        `$answer=App\\Models\\FormAnswer::where('form_id','${phpString(fixture.form.formId)}')->latest('created_at')->first();` +
            `if($answer===null){throw new RuntimeException('multi-upload answer missing');}` +
            `$value=is_array($answer->answers)?($answer->answers['uploads']??[]):[];` +
            `$paths=is_array($value)?array_values(array_filter($value,'is_string')):(is_string($value)&&$value!==''?[$value]:[]);` +
            `echo json_encode(['answerId'=>$answer->id,'value'=>$value,'paths'=>$paths,'localExists'=>array_map(fn($path)=>\\Illuminate\\Support\\Facades\\Storage::disk('local')->exists($path),$paths),'publicExists'=>array_map(fn($path)=>\\Illuminate\\Support\\Facades\\Storage::disk('public')->exists($path),$paths)]);`,
    ]);

    return JSON.parse(output) as IStoredUploadAnswer;
}

async function openFillPage(page: Page, fixture: IUploadFixture): Promise<void> {
    await page.goto(fixture.fillUrl, { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('button', { name: 'Submit registration' })).toBeVisible({ timeout: 20_000 });
}

async function submitRegistration(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Submit registration' }).click();
    const submit = page.getByRole('alertdialog').getByRole('button', { name: 'Submit', exact: true });
    await submit.focus();
    await page.keyboard.press('Enter');
}

async function assertNoHorizontalOverflow(page: Page): Promise<void> {
    const viewport = await page.evaluate(() => {
        const clientWidth = document.documentElement.clientWidth;
        const offenders = Array.from(document.querySelectorAll<HTMLElement>('*'))
            .map((element: HTMLElement) => ({
                tag: element.tagName.toLowerCase(),
                testId: element.dataset.testid ?? '',
                className: element.className,
                right: Math.ceil(element.getBoundingClientRect().right),
                width: element.scrollWidth,
            }))
            .filter((element) => element.right > clientWidth || element.width > clientWidth)
            .slice(0, 5);

        return { clientWidth, scrollWidth: document.documentElement.scrollWidth, offenders };
    });

    expect(
        viewport.scrollWidth,
        `horizontal overflow: ${viewport.scrollWidth} > ${viewport.clientWidth}; offenders=${JSON.stringify(viewport.offenders)}`
    ).toBeLessThanOrEqual(viewport.clientWidth);
}

function uploadInput(page: Page): Locator {
    return page.locator('input[type="file"]').first();
}

test.describe('multi upload fields', () => {
    test('builder exposes format toggles, multiple toggle, and bounded limits', async ({ page }, testInfo) => {
        const fixture = createUploadFixture(testInfo, {
            builderType: 'file_upload',
            multiple: false,
            allowedMimeTypes: ['application/pdf', 'text/plain'],
        });

        try {
            await page.goto(`/admin/events/${fixture.eventId}/forms/${fixture.form.formId}/edit`, {
                waitUntil: 'domcontentloaded',
            });
            await expect(page.locator('#f-title')).toBeVisible({ timeout: 20_000 });
            const settings = page.getByRole('button', { name: 'Pengaturan field' }).last();
            await settings.click();

            const sheet = page.getByRole('dialog').filter({ hasText: 'Properti field' });
            await expect(sheet).toBeVisible();
            await expect(sheet.getByText('Format yang diizinkan')).toBeVisible();

            const pdf = sheet.getByRole('checkbox', { name: 'PDF' });
            const txt = sheet.getByRole('checkbox', { name: 'TXT' });
            await expect(pdf).toBeChecked();
            await expect(txt).toBeChecked();
            await sheet.locator('label').filter({ hasText: 'PDF' }).click();
            await expect(pdf).not.toBeChecked();
            await expect(
                sheet.locator('label').filter({ hasText: 'PDF' }).getByText('OFF', { exact: true })
            ).toBeVisible();

            const multiple = sheet.getByRole('checkbox', { name: 'Izinkan beberapa file' });
            await expect(multiple).not.toBeChecked();
            await multiple.evaluate((element): void => {
                if (element instanceof HTMLInputElement) element.click();
            });
            await expect(multiple).toBeChecked();
            await expect(sheet.getByText('Maksimal jumlah file')).toBeVisible();
            await expect(sheet.getByText('Batas total ukuran (MB)')).toBeVisible();
            await expect(sheet.locator('input[type="number"]')).toHaveCount(2);
        } finally {
            cleanupUploadFixture(fixture);
        }
    });

    test('multiple image uploads render vertical thumbnails, remove one file, and stay within the viewport', async ({
        page,
    }, testInfo) => {
        const fixture = createUploadFixture(testInfo, {
            builderType: 'image_upload',
            multiple: true,
            allowedMimeTypes: ['image/png'],
            maxFiles: 3,
            maxTotalSizeKb: 20,
        });

        try {
            await openFillPage(page, fixture);
            await uploadInput(page).setInputFiles([
                { name: 'image-one.png', mimeType: 'image/png', buffer: VALID_PNG },
                { name: 'image-two.png', mimeType: 'image/png', buffer: VALID_PNG },
            ]);

            const thumbnails = page.locator('figure img');
            await expect(thumbnails).toHaveCount(2);
            await expect(page.getByRole('button', { name: 'Hapus image-one.png' })).toBeVisible();
            await expect(page.getByRole('button', { name: 'Hapus image-two.png' })).toBeVisible();

            const firstBox = await thumbnails.nth(0).boundingBox();
            const secondBox = await thumbnails.nth(1).boundingBox();
            expect(firstBox).not.toBeNull();
            expect(secondBox).not.toBeNull();
            expect(secondBox?.y ?? 0).toBeGreaterThan(firstBox?.y ?? 0);

            await assertNoHorizontalOverflow(page);
            await page.getByRole('button', { name: 'Hapus image-one.png' }).click();
            await expect(page.getByText('image-one.png', { exact: true })).toHaveCount(0);
            await expect(page.getByText('image-two.png', { exact: true })).toBeVisible();
            await assertNoHorizontalOverflow(page);
        } finally {
            cleanupUploadFixture(fixture);
        }
    });

    test('multiple document uploads render independent rows and remove only the selected row', async ({
        page,
    }, testInfo) => {
        const fixture = createUploadFixture(testInfo, {
            builderType: 'file_upload',
            multiple: true,
            allowedMimeTypes: ['application/pdf'],
            maxFiles: 3,
            maxTotalSizeKb: 20,
        });

        try {
            await openFillPage(page, fixture);
            await uploadInput(page).setInputFiles([
                { name: 'document-one.pdf', mimeType: 'application/pdf', buffer: PDF },
                { name: 'document-two.pdf', mimeType: 'application/pdf', buffer: PDF },
            ]);

            await expect(page.getByText('document-one.pdf', { exact: true })).toBeVisible();
            await expect(page.getByText('document-two.pdf', { exact: true })).toBeVisible();
            await expect(page.getByText('Selesai', { exact: true })).toHaveCount(2);
            await page.getByRole('button', { name: 'Hapus document-one.pdf' }).click();
            await expect(page.getByText('document-one.pdf', { exact: true })).toHaveCount(0);
            await expect(page.getByText('document-two.pdf', { exact: true })).toBeVisible();
            await assertNoHorizontalOverflow(page);
        } finally {
            cleanupUploadFixture(fixture);
        }
    });

    test('single upload remains a scalar answer and uses private storage', async ({ page }, testInfo) => {
        const fixture = createUploadFixture(testInfo, {
            builderType: 'file_upload',
            multiple: false,
            allowedMimeTypes: ['application/pdf'],
        });

        try {
            await openFillPage(page, fixture);
            await uploadInput(page).setInputFiles({
                name: 'single-document.pdf',
                mimeType: 'application/pdf',
                buffer: PDF,
            });
            await expect(page.getByText('single-document.pdf', { exact: true })).toBeVisible();
            await submitRegistration(page);
            await expect(page).not.toHaveURL(/\/fill/);

            const stored = storedUploadAnswer(fixture);
            expect(typeof stored.value).toBe('string');
            expect(stored.paths).toHaveLength(1);
            expect(stored.paths[0]).toContain(`form-uploads/${fixture.form.formId}/`);
            expect(stored.localExists).toEqual([true]);
            expect(stored.publicExists).toEqual([false]);
        } finally {
            cleanupUploadFixture(fixture);
        }
    });

    test('invalid formats and file count/total limits are rejected before submit', async ({ page }, testInfo) => {
        const invalidFixture = createUploadFixture(testInfo, {
            builderType: 'image_upload',
            multiple: true,
            allowedMimeTypes: ['image/png'],
            maxFiles: 3,
            maxTotalSizeKb: 20,
        });

        try {
            await openFillPage(page, invalidFixture);
            await uploadInput(page).setInputFiles({
                name: 'not-an-image.pdf',
                mimeType: 'application/pdf',
                buffer: PDF,
            });
            await expect(page.getByText('Format file tidak didukung.', { exact: true }).first()).toBeVisible();
            await expect(page.getByText('not-an-image.pdf', { exact: true })).toHaveCount(0);
        } finally {
            cleanupUploadFixture(invalidFixture);
        }

        await page.goto('about:blank');

        const limitsFixture = createUploadFixture(testInfo, {
            builderType: 'file_upload',
            multiple: true,
            allowedMimeTypes: ['application/pdf'],
            maxFiles: 2,
            maxTotalSizeKb: 1,
        });

        try {
            await openFillPage(page, limitsFixture);
            await uploadInput(page).setInputFiles([
                { name: 'limit-one.pdf', mimeType: 'application/pdf', buffer: PDF },
                { name: 'limit-two.pdf', mimeType: 'application/pdf', buffer: PDF },
                { name: 'limit-three.pdf', mimeType: 'application/pdf', buffer: PDF },
            ]);
            await expect(page.getByText('Pilih maksimal 2 berkas.', { exact: true }).first()).toBeVisible();

            const largePdf = Buffer.concat([PDF, Buffer.alloc(2048)]);
            await uploadInput(page).setInputFiles({
                name: 'too-large-total.pdf',
                mimeType: 'application/pdf',
                buffer: largePdf,
            });
            await expect(page.getByText('Ukuran total berkas maksimal 1 KB.', { exact: true }).first()).toBeVisible();
            await expect(page.getByText('too-large-total.pdf', { exact: true })).toHaveCount(0);
        } finally {
            cleanupUploadFixture(limitsFixture);
        }
    });

    test('submits multiple images, previews every file in admin detail, and keeps new storage private', async ({
        page,
    }, testInfo) => {
        const fixture = createUploadFixture(testInfo, {
            builderType: 'image_upload',
            multiple: true,
            allowedMimeTypes: ['image/png'],
            maxFiles: 3,
            maxTotalSizeKb: 20,
        });

        const anonymousRequest = await playwrightRequest.newContext({
            baseURL: environment.baseUrl,
            storageState: { cookies: [], origins: [] },
        });

        try {
            await openFillPage(page, fixture);
            await uploadInput(page).setInputFiles([
                { name: 'private-one.png', mimeType: 'image/png', buffer: VALID_PNG },
                { name: 'private-two.png', mimeType: 'image/png', buffer: VALID_PNG },
            ]);
            await expect(page.locator('figure img')).toHaveCount(2);
            await submitRegistration(page);
            await expect(page).not.toHaveURL(/\/fill/);

            const stored = storedUploadAnswer(fixture);
            expect(stored.paths).toHaveLength(2);
            expect(stored.paths.every((path: string) => path.startsWith(`form-uploads/${fixture.form.formId}/`))).toBe(
                true
            );
            expect(stored.localExists).toEqual([true, true]);
            expect(stored.publicExists).toEqual([false, false]);

            await page.goto(`${fixture.showUrl}?tab=jawaban`, { waitUntil: 'domcontentloaded' });
            const detailButton = page.getByRole('button', { name: /Lihat detail jawaban dari/ }).first();
            await expect(detailButton).toBeVisible({ timeout: 20_000 });
            await detailButton.click();

            const fileList = page.getByTestId('form-answer-file-list');
            await expect(fileList).toBeVisible();
            const previews = fileList.locator('img[alt]:not([alt=""])');
            await expect(previews).toHaveCount(2);
            await expect(fileList.getByRole('link', { name: 'Buka berkas' })).toHaveCount(0);
            await expect(fileList).not.toContainText('form-uploads/');

            for (const index of [0, 1]) {
                const source = await previews.nth(index).getAttribute('src');
                if (source === null) throw new Error(`private image ${index} did not expose a preview URL.`);
                expect(source).toContain(`/form-answers/${stored.answerId}/files/uploads/`);

                const ownerResponse = await page.request.get(source);
                expect(ownerResponse.status()).toBe(200);
                const anonymousResponse = await anonymousRequest.get(source);
                expect([403, 404]).toContain(anonymousResponse.status());
            }

            const directStorage = await anonymousRequest.get(`/storage/${stored.paths[0]}`);
            expect([403, 404]).toContain(directStorage.status());
            await assertNoHorizontalOverflow(page);
        } finally {
            await anonymousRequest.dispose();
            cleanupUploadFixture(fixture);
        }
    });
});
