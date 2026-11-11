import { expect, test, type Page } from '@playwright/test';
import { readEnvironment } from './support/environment';
import { runArtisanCommand } from './support/artisan';

/**
 * Upload card on the participant side: the file still travels with the form submission
 * (`forceFormData`), so these specs assert the card states and the stored answer path —
 * the contract that must not change when the raw `<input type=file>` is replaced.
 */

const environment = readEnvironment();

/** PNG 1x1 valid: cukup untuk lolos preflight `validateUploadFile` tanpa base64/FileReader. */
const PNG_STUB = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP4z8DwHwAFAAH/VscvDQAAAABJRU5ErkJggg==',
    'base64'
);

const PDF_STUB = Buffer.from('%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n');

interface IFillFixture {
    token: string;
    eventId: string;
    formId: string;
    fillUrl: string;
}

interface IInvitationFixture {
    token: string;
    invitationUrl: string;
    eventId: string;
    formId: string;
    memberAnswerId: string;
    storedPath: string;
    memberEmail: string;
    memberPassword: string;
}

function assertCredentials(): void {
    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD must be set for the upload card E2E.');
    }
}

/** Form "other" berisi satu field berkas; `builderType` menentukan jalur gambar atau dokumen. */
function createFillFixture(builderType: 'image_upload' | 'file_upload', name: string): IFillFixture {
    assertCredentials();

    const token = `e2e-card-${builderType}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const mimes = builderType === 'image_upload' ? 'jpg,jpeg,png,webp' : 'pdf';

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
        `'description'=>'E2E upload card',` +
        `'visible_for'=>['public'],` +
        `'closed_at'=>now()->addDay(),` +
        `'metadata'=>['purpose'=>'other']` +
        `]);` +
        `$form->formFields()->create([` +
        `'label'=>'Berkas',` +
        `'name'=>'${name}',` +
        `'input_type'=>'fileUpload',` +
        `'metadata'=>['builderType'=>'${builderType}','rules'=>['required'=>true,'mimes'=>'${mimes}','max_size'=>5120]],` +
        `'order'=>0` +
        `]);` +
        `echo json_encode([` +
        `'token'=>'${token}',` +
        `'eventId'=>$event->id,` +
        `'formId'=>$form->id,` +
        `'fillUrl'=>route('dashboard.events.forms.fill',['event'=>$event,'form'=>$form],false)` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IFillFixture;
}

function cleanupFillFixture(fixture: IFillFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$form=App\\Models\\Form::withTrashed()->find('${fixture.formId}');` +
            `if($form){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$form->id)->get() as $field){$field->forceDelete();}` +
            `App\\Models\\FormAnswer::where('form_id',$form->id)->delete();` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('form-uploads/'.$form->id);` +
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

interface IStoredAnswer {
    count: number;
    path: string | null;
}

function storedAnswer(fixture: IFillFixture, name: string): IStoredAnswer {
    const command =
        `$answers=App\\Models\\FormAnswer::where('form_id','${fixture.formId}')->get();` +
        `$first=$answers->first();` +
        `echo json_encode([` +
        `'count'=>$answers->count(),` +
        `'path'=>$first !== null && is_array($first->answers) ? ($first->answers['${name}'] ?? null) : null` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IStoredAnswer;
}

/** Team form dengan satu field berkas appendable, plus undangan pending untuk anggota. */
function createInvitationFixture(): IInvitationFixture {
    assertCredentials();

    const token = `e2e-invite-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const invitationToken = `inv-${token}`;
    const memberEmail = `${token}@example.test`;
    const memberPassword = 'e2e-invitation-password';

    const command =
        `$member=App\\Models\\User::factory()->create([` +
        `'email'=>'${memberEmail}',` +
        `'password'=>\\Illuminate\\Support\\Facades\\Hash::make('${memberPassword}'),` +
        `'email_verified_at'=>now()` +
        `]);` +
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
        `'description'=>'E2E invitation upload card',` +
        `'visible_for'=>['public'],` +
        `'closed_at'=>now()->addDay(),` +
        `'metadata'=>['registration_mode'=>'team','team_size'=>2]` +
        `]);` +
        `$form->formFields()->create([` +
        `'label'=>'Portfolio',` +
        `'name'=>'portfolio',` +
        `'input_type'=>'fileUpload',` +
        `'is_append'=>true,` +
        `'order'=>0,` +
        `'metadata'=>['rules'=>['required'=>true,'mimes'=>'pdf','max_size'=>5120]]` +
        `]);` +
        `$leader=App\\Models\\FormAnswer::factory()->create([` +
        `'form_id'=>$form->id,` +
        `'user_id'=>$owner->id,` +
        `'registration_role'=>\\App\\Enums\\RegistrationRole::Leader,` +
        `'member_confirmation_status'=>\\App\\Enums\\MemberConfirmationStatus::Accepted` +
        `]);` +
        `$storedPath='form-uploads/'.$form->id.'/old-portfolio.pdf';` +
        `$memberAnswer=App\\Models\\FormAnswer::factory()->create([` +
        `'form_id'=>$form->id,` +
        `'user_id'=>$member->id,` +
        `'leader_form_answer_id'=>$leader->id,` +
        `'registration_role'=>\\App\\Enums\\RegistrationRole::Member,` +
        `'member_confirmation_status'=>\\App\\Enums\\MemberConfirmationStatus::Pending,` +
        `'invitation_token'=>'${invitationToken}',` +
        `'invitation_expired_at'=>now()->addDays(7),` +
        `'answers'=>['portfolio'=>$storedPath]` +
        `]);` +
        `echo json_encode([` +
        `'token'=>'${invitationToken}',` +
        `'invitationUrl'=>route('dashboard.user.team-invitations.show',['token'=>'${invitationToken}'],false),` +
        `'eventId'=>$event->id,` +
        `'formId'=>$form->id,` +
        `'memberAnswerId'=>$memberAnswer->id,` +
        `'storedPath'=>$storedPath,` +
        `'memberEmail'=>'${memberEmail}',` +
        `'memberPassword'=>'${memberPassword}'` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IInvitationFixture;
}

function cleanupInvitationFixture(fixture: IInvitationFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `App\\Models\\FormAnswer::where('form_id','${fixture.formId}')->delete();` +
            `$form=App\\Models\\Form::withTrashed()->find('${fixture.formId}');` +
            `if($form){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$form->id)->get() as $field){$field->forceDelete();}` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('form-uploads/'.$form->id);` +
            `$form->forceDelete();` +
            `}` +
            `$event=App\\Models\\Event::withTrashed()->find('${fixture.eventId}');` +
            `if($event){` +
            `foreach(App\\Models\\Form::withTrashed()->where('event_id',$event->id)->get() as $eventForm){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$eventForm->id)->get() as $field){$field->forceDelete();}` +
            `$eventForm->forceDelete();` +
            `}` +
            `$event->forceDelete();` +
            `}` +
            `App\\Models\\User::where('email','${fixture.memberEmail}')->delete();`,
    ]);
}

function invitationStoredPath(fixture: IInvitationFixture): string | null {
    const value = runArtisanCommand([
        'tinker',
        '--execute',
        `$row=App\\Models\\FormAnswer::find('${fixture.memberAnswerId}');` +
            `echo json_encode($row !== null && is_array($row->answers) ? ($row->answers['portfolio'] ?? null) : null);`,
    ]);

    const parsed: unknown = JSON.parse(value);

    return typeof parsed === 'string' ? parsed : null;
}

async function signIn(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/auth/login');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill(password);
    await page
        .locator('form')
        .filter({ has: page.locator('#login-email') })
        .getByRole('button', { name: /masuk/i })
        .click();
    await expect(page).not.toHaveURL(/\/auth\/login/);
}

/** Dialog konfirmasi dirender async; aktifkan lewat keyboard seperti jalur pengguna nyata. */
async function confirmSubmit(page: Page): Promise<void> {
    await page.getByRole('button', { name: /submit registration/i }).click();
    const confirm = page.getByRole('alertdialog').getByRole('button', { name: 'Submit', exact: true });
    await confirm.focus();
    await page.keyboard.press('Enter');
}

test.describe('participant upload card', () => {
    test('image field shows the picked photo preview and stores the uploaded path', async ({ page }) => {
        const fixture = createFillFixture('image_upload', 'photo');

        try {
            await page.goto(fixture.fillUrl);
            await page.locator('input[type=file]').first().setInputFiles({
                name: 'photo.png',
                mimeType: 'image/png',
                buffer: PNG_STUB,
            });

            const preview = page.locator('figure img[src^="blob:"]');
            await expect(preview).toBeVisible();
            await expect(page.getByText('photo.png', { exact: false })).toBeVisible();

            await confirmSubmit(page);
            await expect(page).not.toHaveURL(/\/fill/);

            const stored = storedAnswer(fixture, 'photo');
            expect(stored.count).toBe(1);
            expect(stored.path).toMatch(new RegExp(`^form-uploads/${fixture.formId}/`));
        } finally {
            cleanupFillFixture(fixture);
        }
    });

    test('document field shows a completed card, removes the file, and submission stays blocked', async ({ page }) => {
        const fixture = createFillFixture('file_upload', 'cv');

        try {
            await page.goto(fixture.fillUrl);
            await page.locator('input[type=file]').first().setInputFiles({
                name: 'cv.pdf',
                mimeType: 'application/pdf',
                buffer: PDF_STUB,
            });

            await expect(page.getByText(/· Selesai/)).toBeVisible();

            await page.getByRole('button', { name: 'Hapus cv.pdf' }).click();
            await expect(page.getByRole('button', { name: 'Pilih berkas' })).toBeVisible();
            await expect(page.getByText('cv.pdf', { exact: true })).toHaveCount(0);

            await confirmSubmit(page);
            await expect(page).toHaveURL(/\/fill/);
            await expect(page.getByText(/required|wajib/i).first()).toBeVisible();
            expect(storedAnswer(fixture, 'cv').count).toBe(0);
        } finally {
            cleanupFillFixture(fixture);
        }
    });
});

test.describe('team invitation attachment card', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('member attaches a new PDF through the card and the invitation stores the replaced path', async ({ page }) => {
        const fixture = createInvitationFixture();

        try {
            await signIn(page, fixture.memberEmail, fixture.memberPassword);
            await page.goto(fixture.invitationUrl);

            await page.locator('input[type=file]').first().setInputFiles({
                name: 'portfolio.pdf',
                mimeType: 'application/pdf',
                buffer: PDF_STUB,
            });
            await expect(page.getByText(/· Selesai/)).toBeVisible();

            await page.getByRole('button', { name: 'Accept invitation' }).click();

            // Lapisan overlay dialog menutup tombol aksi pada stack ini, jadi konfirmasi
            // dikirim langsung ke handler tombol (spec upload privacy memakai keyboard).
            const accept = page.getByRole('alertdialog').getByRole('button', { name: 'Accept and submit' });
            await expect(accept).toBeVisible();

            const submitted = page.waitForResponse(
                (response) => response.request().method() === 'POST' && response.url().includes('/team-invitations/')
            );
            await accept.dispatchEvent('click');

            expect((await submitted).status()).toBe(302);

            await expect.poll(() => invitationStoredPath(fixture), { timeout: 10_000 }).not.toBe(fixture.storedPath);

            const storedPath = invitationStoredPath(fixture);
            expect(storedPath).not.toBeNull();
            expect(storedPath).toMatch(new RegExp(`^form-uploads/${fixture.formId}/`));
        } finally {
            cleanupInvitationFixture(fixture);
        }
    });
});
