import { expect, test, type Page } from '@playwright/test';
import { readEnvironment } from './support/environment';
import { runArtisanCommand } from './support/artisan';

// Spec ini login sendiri agar anonim bisa diuji tanpa storageState admin.
test.use({ storageState: { cookies: [], origins: [] } });

const environment = readEnvironment();
const PDF_STUB = Buffer.from('%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n');

interface IFixture {
    token: string;
    eventId: string;
    formId: string;
    fillUrl: string;
    showUrl: string;
}

function assertCredentials(): void {
    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD must be set for upload privacy E2E.');
    }
}

/** Create an isolated published event, an "other" form, and one file field. */
function createFixture(purpose: 'registration' | 'other'): IFixture {
    assertCredentials();

    const token = `e2e-upload-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const metadata =
        purpose === 'other' ? "['purpose'=>'other']" : "['purpose'=>'registration','registration_mode'=>'single']";

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
        `'description'=>'E2E upload privacy',` +
        `'visible_for'=>['public'],` +
        `'closed_at'=>now()->addDay(),` +
        `'metadata'=>${metadata}` +
        `]);` +
        `$form->formFields()->create([` +
        `'label'=>'CV',` +
        `'name'=>'cv',` +
        `'input_type'=>'fileUpload',` +
        `'metadata'=>['rules'=>['required'=>true,'mimes'=>'pdf']],` +
        `'order'=>0` +
        `]);` +
        `echo json_encode([` +
        `'token'=>'${token}',` +
        `'eventId'=>$event->id,` +
        `'formId'=>$form->id,` +
        `'fillUrl'=>route('dashboard.events.forms.fill',['event'=>$event,'form'=>$form],false),` +
        `'showUrl'=>route('dashboard.events.forms.show',['event'=>$event,'form'=>$form],false)` +
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

interface IStoredUpload {
    answerId: string;
    path: string;
    localExists: boolean;
    publicExists: boolean;
}

function storedUpload(fixture: IFixture): IStoredUpload {
    const command =
        `$answer=App\\Models\\FormAnswer::where('form_id','${fixture.formId}')->firstOrFail();` +
        `$path=$answer->answers['cv'];` +
        `echo json_encode([` +
        `'answerId'=>$answer->id,` +
        `'path'=>$path,` +
        `'localExists'=>\\Illuminate\\Support\\Facades\\Storage::disk('local')->exists($path),` +
        `'publicExists'=>\\Illuminate\\Support\\Facades\\Storage::disk('public')->exists($path)` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IStoredUpload;
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

async function submitFile(page: Page, file: { name: string; mimeType: string; buffer: Buffer }): Promise<void> {
    await page.locator('input[type=file]').first().setInputFiles(file);
    await page.getByRole('button', { name: /submit registration/i }).click();

    // The confirmation overlay currently paints above its own dialog content in
    // this stack, so pointer clicks are intercepted. Keyboard activation is a
    // real user path and keeps this spec focused on upload privacy.
    const submit = page.getByRole('alertdialog').getByRole('button', { name: 'Submit', exact: true });
    await submit.focus();
    await page.keyboard.press('Enter');
}

test.describe('AUDIT-P1-002 form upload privacy', () => {
    test('submission upload is private, shown through the authorized route, and anonymous access is denied', async ({
        page,
        request,
    }) => {
        const fixture = createFixture('other');

        try {
            await signIn(page);
            await page.goto(fixture.fillUrl);
            await submitFile(page, { name: 'cv.pdf', mimeType: 'application/pdf', buffer: PDF_STUB });

            await expect(page).not.toHaveURL(/\/fill/);

            const upload = storedUpload(fixture);
            expect(upload.path).toMatch(new RegExp(`^form-uploads/${fixture.formId}/`));
            expect(upload.localExists).toBe(true);
            expect(upload.publicExists).toBe(false);

            await page.goto(`${fixture.showUrl}?tab=jawaban`);
            await page
                .getByRole('button', { name: /Lihat detail jawaban dari/ })
                .first()
                .click();

            const preview = page.locator('iframe[title="Pratinjau PDF"]');
            await expect(preview).toBeVisible();
            const fileUrl = await preview.getAttribute('src');
            expect(fileUrl).toBeTruthy();
            expect(fileUrl).toContain(`/form-answers/${upload.answerId}/files/cv/`);

            const ownerStatus = await page.evaluate(async (url) => (await fetch(url as string)).status, fileUrl);
            expect(ownerStatus).toBe(200);

            const anonymousResponse = await request.get(fileUrl as string);
            expect([403, 404]).toContain(anonymousResponse.status());

            const directStorage = await request.get(`/storage/${upload.path}`);
            expect([403, 404]).toContain(directStorage.status());
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: non-PDF and active-content PDF uploads are rejected by server validation', async ({ page }) => {
        const fixture = createFixture('other');

        try {
            await signIn(page);
            await page.goto(fixture.fillUrl);

            await submitFile(page, {
                name: 'malware.exe',
                mimeType: 'application/octet-stream',
                buffer: Buffer.from('MZ fake executable'),
            });

            await expect(page.getByText(/format is not allowed|tidak didukung/i).first()).toBeVisible();
            await expect(page).toHaveURL(/\/fill/);

            await submitFile(page, {
                name: 'active.pdf',
                mimeType: 'application/pdf',
                buffer: Buffer.from('%PDF-1.7\n1 0 obj\n<< /JavaScript (alert(1)) >>\nendobj\ntrailer\n<<>>\n%%EOF\n'),
            });

            await expect(
                page.getByText(/active or embedded content|konten aktif|tidak diizinkan/i).first()
            ).toBeVisible();
            await expect(page).toHaveURL(/\/fill/);

            const count = runArtisanCommand([
                'tinker',
                '--execute',
                `echo App\\Models\\FormAnswer::where('form_id','${fixture.formId}')->count();`,
            ]);

            expect(count).toBe('0');
        } finally {
            cleanupFixture(fixture);
        }
    });
});
