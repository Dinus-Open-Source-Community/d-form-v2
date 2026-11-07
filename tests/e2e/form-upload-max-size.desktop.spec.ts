import { expect, test as base, type BrowserContext, type Page } from '@playwright/test';
import { readEnvironment } from './support/environment';
import { runArtisanCommand } from './support/artisan';

const environment = readEnvironment();

type TStorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

interface IWorkerFixtures {
    participantStorage: TStorageState;
}

/**
 * Login UI sekali per worker lalu bagikan sesinya ke semua test.
 * Throttle `auth-login` hanya 5 percobaan per menit per email+IP (`config/auth_throttle.php`),
 * jadi enam login per run membuat test terakhir kena 429 dan tetap di `/auth/login`.
 */
const test = base.extend<Record<never, never>, IWorkerFixtures>({
    participantStorage: [
        async ({ browser }, use): Promise<void> => {
            const context = await browser.newContext({
                baseURL: environment.baseUrl,
                storageState: { cookies: [], origins: [] },
            });

            try {
                const page = await context.newPage();
                await signIn(page);
                await use(await context.storageState());
            } finally {
                await context.close();
            }
        },
        { scope: 'worker' },
    ],
});

// Konteks tetap guest sampai login UI di worker fixture selesai; bukan storageState admin.
test.use({
    storageState: async ({ participantStorage }, use): Promise<void> => {
        await use(participantStorage);
    },
});

/** PNG 1x1 valid; dipakai untuk menguji anomali format yang tidak diizinkan field. */
const PNG_STUB = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP4z8DwHwAFAAH/VscvDQAAAABJRU5ErkJggg==',
    'base64'
);

interface IFixture {
    token: string;
    eventId: string;
    formId: string;
    fillUrl: string;
}

/**
 * PDF stub berukuran tepat `size` byte: header `%PDF-` di awal dan `%%EOF` di 1024 byte terakhir,
 * sehingga lolos deteksi struktur `SafeUploadFile` dan hanya ukuran yang membedakan.
 */
function pdfOfSize(size: number): Buffer {
    const head = '%PDF-1.7\n';
    const tail = '\n%%EOF\n';
    const fillerLength = size - head.length - tail.length;

    return Buffer.concat([Buffer.from(head + 'A'.repeat(fillerLength)), Buffer.from(tail)]);
}

function assertCredentials(): void {
    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD must be set for upload max size E2E.');
    }
}

/** Satu field fileUpload milik form "other"; `rules` JSON dikirim apa adanya ke metadata field. */
function createFixture(rulesJson: string): IFixture {
    assertCredentials();

    const token = `e2e-maxsize-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

    const command =
        `$owner=App\\Models\\User::where('email','${environment.adminEmail}')->firstOrFail();` +
        `$event=App\\Models\\Event::factory()->create([` +
        `'title'=>'${token}',` +
        `'status'=>'published',` +
        `'created_by'=>$owner->id,` +
        `'registration_start'=>now()->subDay(),` +
        `'registration_end'=>now()->addDay(),` +
        `'start_date'=>now()->addDay()->toDateString(),` +
        `'end_date'=>now()->addDays(2)->toDateString(),` +
        `'quota'=>10,` +
        `'registered_count'=>0` +
        `]);` +
        `$form=$event->forms()->create([` +
        `'title'=>'${token}',` +
        `'description'=>'E2E upload max size',` +
        `'visible_for'=>['public'],` +
        `'closed_at'=>now()->addDay(),` +
        `'metadata'=>['purpose'=>'other']` +
        `]);` +
        `$form->formFields()->create([` +
        `'label'=>'CV',` +
        `'name'=>'cv',` +
        `'input_type'=>'fileUpload',` +
        `'metadata'=>['rules'=>${rulesJson}],` +
        `'order'=>0` +
        `]);` +
        `echo json_encode([` +
        `'token'=>'${token}',` +
        `'eventId'=>$event->id,` +
        `'formId'=>$form->id,` +
        `'fillUrl'=>route('dashboard.events.forms.fill',['event'=>$event,'form'=>$form],false)` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IFixture;
}

function cleanupFixture(fixture: IFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$form=App\\Models\\Form::withTrashed()->find('${fixture.formId}');` +
            `if($form){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$form->id)->get() as $field){$field->forceDelete();}` +
            `App\\Models\\FormAnswer::where('form_id',$form->id)->delete();` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('form-uploads/'.$form->id);` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('public')->deleteDirectory('form-uploads/'.$form->id);` +
            `$form->forceDelete();` +
            `}` +
            `$event=App\\Models\\Event::withTrashed()->find('${fixture.eventId}');` +
            `if($event){` +
            `foreach(App\\Models\\Form::withTrashed()->where('event_id',$event->id)->get() as $eventForm){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$eventForm->id)->get() as $field){$field->forceDelete();}` +
            `$eventForm->forceDelete();` +
            `}` +
            `$event->forceDelete();` +
            `}`,
    ]);
}

function answerCount(fixture: IFixture): number {
    const value = runArtisanCommand([
        'tinker',
        '--execute',
        `echo App\\Models\\FormAnswer::where('form_id','${fixture.formId}')->count();`,
    ]);

    return Number.parseInt(value, 10);
}

async function signIn(page: Page): Promise<void> {
    await page.goto('/auth/login');
    await page.locator('#login-email').fill(environment.adminEmail);
    await page.locator('#login-password').fill(environment.adminPassword);
    await page
        .locator('form')
        .filter({ has: page.locator('#login-email') })
        .getByRole('button', { name: /masuk/i })
        .click();
    await expect(page).not.toHaveURL(/\/auth\/login/);
}

/**
 * Lampirkan file yang lolos preflight klien. `onFileChange` menunggu `validateUploadFile`
 * sebelum menaruh File ke state form, jadi chip nama file dipakai sebagai penanda selesai.
 */
async function attachFile(page: Page, file: { name: string; mimeType: string; buffer: Buffer }): Promise<void> {
    await page.locator('input[type=file]').first().setInputFiles(file);
    await expect(page.getByText(file.name, { exact: true }).first()).toBeVisible();
}

async function submitForm(page: Page): Promise<void> {
    await page.getByRole('button', { name: /submit registration/i }).click();

    // Dialog konfirmasi dirender async setelah submit; `count()` tidak menunggu sehingga
    // Enter bisa terkirim sebelum dialog ada dan submit tidak pernah terjadi.
    const confirm = page.getByRole('alertdialog').getByRole('button', { name: 'Submit', exact: true });
    await confirm.focus();
    await page.keyboard.press('Enter');
}

test.describe('AUDIT-P2-010 per-field upload max_size', () => {
    test('file within the per-field limit is accepted', async ({ page }) => {
        const fixture = createFixture("['required'=>true,'mimes'=>'pdf','max_size'=>2]");

        try {
            await page.goto(fixture.fillUrl);
            await attachFile(page, { name: 'cv.pdf', mimeType: 'application/pdf', buffer: pdfOfSize(1024) });
            await submitForm(page);

            await expect(page).toHaveURL(/\/admin\/events\//);
            expect(answerCount(fixture)).toBe(1);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('file above the per-field limit is rejected with the field limit in the message', async ({ page }) => {
        const fixture = createFixture("['required'=>true,'mimes'=>'pdf','max_size'=>2]");

        try {
            await page.goto(fixture.fillUrl);
            await attachFile(page, { name: 'cv.pdf', mimeType: 'application/pdf', buffer: pdfOfSize(2049) });
            await submitForm(page);

            await expect(page).toHaveURL(/\/forms\/[^/]+\/fill/);
            await expect(page.getByText(/may not be greater than 2 KB/i).first()).toBeVisible();
            expect(answerCount(fixture)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('file exactly at the per-field limit is accepted', async ({ page }) => {
        const fixture = createFixture("['required'=>true,'mimes'=>'pdf','max_size'=>2]");

        try {
            await page.goto(fixture.fillUrl);
            await attachFile(page, { name: 'cv.pdf', mimeType: 'application/pdf', buffer: pdfOfSize(2048) });
            await submitForm(page);

            await expect(page).toHaveURL(/\/admin\/events\//);
            expect(answerCount(fixture)).toBe(1);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('legacy max_size above the global limit still accepts a file under the global limit', async ({ page }) => {
        const fixture = createFixture("['required'=>true,'mimes'=>'pdf','max_size'=>10240]");

        try {
            await page.goto(fixture.fillUrl);
            await attachFile(page, { name: 'cv.pdf', mimeType: 'application/pdf', buffer: pdfOfSize(6144) });
            await submitForm(page);

            await expect(page).toHaveURL(/\/admin\/events\//);
            expect(answerCount(fixture)).toBe(1);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: format outside the field mimes is rejected', async ({ page }) => {
        const fixture = createFixture("['required'=>true,'mimes'=>'pdf','max_size'=>2]");

        try {
            await page.goto(fixture.fillUrl);
            await attachFile(page, { name: 'cv.png', mimeType: 'image/png', buffer: PNG_STUB });
            await submitForm(page);

            await expect(page).toHaveURL(/\/forms\/[^/]+\/fill/);
            await expect(page.getByText(/format is not allowed/i).first()).toBeVisible();
            expect(answerCount(fixture)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: zero-byte upload is rejected', async ({ page }) => {
        const fixture = createFixture("['required'=>true,'mimes'=>'pdf','max_size'=>2]");

        try {
            await page.goto(fixture.fillUrl);
            await page
                .locator('input[type=file]')
                .first()
                .setInputFiles({
                    name: 'cv.pdf',
                    mimeType: 'application/pdf',
                    buffer: Buffer.alloc(0),
                });

            // Preflight klien (`validateUploadFile`) menolak PDF tanpa header `%PDF-`, jadi file tidak pernah terpasang.
            await expect(page.getByText(/isi file pdf tidak valid/i).first()).toBeVisible();

            await submitForm(page);

            await expect(page).toHaveURL(/\/forms\/[^/]+\/fill/);
            expect(answerCount(fixture)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });
});
