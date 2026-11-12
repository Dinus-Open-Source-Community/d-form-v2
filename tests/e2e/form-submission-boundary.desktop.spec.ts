import { expect as baseExpect, test, type BrowserContext, type Page, type Response } from '@playwright/test';
import { readEnvironment } from './support/environment';
import { runArtisanCommand } from './support/artisan';
import { readXsrfToken } from './support/network';

// Konfirmasi/invitation email berjalan sinkron di queue E2E dengan jitter 5-10 detik per job,
// sehingga redirect registrasi bisa melewati timeout assertion default 10 detik.
const expect = baseExpect.configure({ timeout: 60_000 });

const environment = readEnvironment();

/** Token unik per run supaya fixture dari run lain tidak pernah bertabrakan. */
const RUN_TOKEN = `e2e-boundary-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
let fixtureSequence = 0;

type TFixtureMode = 'single' | 'team' | 'team3' | 'bundle' | 'closed';

interface IFixtureUser {
    id: string;
    email: string;
}

interface IFixture {
    token: string;
    ownerId: string;
    eventId: string;
    formId: string;
    fillPath: string;
    submitPath: string;
    users: IFixtureUser[];
}

interface IAnswerSnapshot {
    count: number;
    userId: string | null;
    reviewStatus: string | null;
    answerKeys: string[];
}

interface ISubmitResult {
    status: number;
    locationPath: string;
}

const FORM_METADATA: Record<TFixtureMode, string> = {
    single: "['purpose'=>'registration','registration_mode'=>'single']",
    closed: "['purpose'=>'registration','registration_mode'=>'single']",
    team: "['purpose'=>'registration','registration_mode'=>'team','team_size'=>2]",
    // Team dengan dua slot anggota: dipakai untuk anomali duplikat email, supaya
    // `size:2` lolos dan yang menolak benar-benar guard keunikan.
    team3: "['purpose'=>'registration','registration_mode'=>'team','team_size'=>3]",
    bundle: "['purpose'=>'registration','registration_mode'=>'bundle','max_team_size'=>2]",
};

function assertCredentials(): void {
    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD must be set for form submission boundary E2E.');
    }
}

/** Kutip nilai sebagai literal string PHP; token/email bisa memuat karakter khusus. */
function phpString(value: string): string {
    return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function nextToken(mode: TFixtureMode): string {
    fixtureSequence += 1;

    return `${RUN_TOKEN}-${mode}-${fixtureSequence}`;
}

/**
 * Field skalar disimpan tanpa `builderType` agar input tidak mendapat atribut `maxlength`:
 * anomali "nama terlalu panjang" harus sampai ke validasi server, bukan dipotong browser.
 */
function fieldStatements(mode: TFixtureMode): string {
    if (mode === 'team' || mode === 'team3') {
        return `$form->formFields()->create(['label'=>'Full Name','name'=>'full_name','input_type'=>'input','metadata'=>['type'=>'text','rules'=>['required'=>true]],'order'=>0]);`;
    }

    if (mode === 'bundle') {
        return `$form->formFields()->create(['label'=>'Full Name','name'=>'full_name','input_type'=>'input','metadata'=>['type'=>'text','rules'=>['required'=>true],'duplicatable'=>true],'order'=>0]);`;
    }

    return (
        `$form->formFields()->create(['label'=>'Full Name','name'=>'full_name','input_type'=>'input','metadata'=>['type'=>'text','rules'=>['required'=>true,'max'=>100]],'order'=>0]);` +
        `$form->formFields()->create(['label'=>'Email Contact','name'=>'email_contact','input_type'=>'input','metadata'=>['type'=>'email','rules'=>['required'=>true]],'order'=>1]);`
    );
}

/** Single/closed memakai akun decoy untuk uji injeksi `user_id`; team memakai akun teammate asli. */
function extraUserStatement(mode: TFixtureMode, token: string): string {
    if (mode === 'team' || mode === 'team3') {
        return `$extra=App\\Models\\User::factory()->create(['name'=>'E2E Boundary Mate','email'=>${phpString(`e2e-mate-${token}@example.test`)}]);`;
    }

    if (mode === 'single' || mode === 'closed') {
        return `$extra=App\\Models\\User::factory()->create(['name'=>'E2E Boundary Decoy','email'=>${phpString(`e2e-decoy-${token}@example.test`)}]);`;
    }

    return '$extra=null;';
}

function createFixture(mode: TFixtureMode): IFixture {
    assertCredentials();

    const token = nextToken(mode);
    const closedAt = mode === 'closed' ? 'now()->subHour()' : 'now()->addDay()';

    const command =
        `$owner=App\\Models\\User::where('email',${phpString(environment.adminEmail)})->firstOrFail();` +
        `$event=App\\Models\\Event::factory()->create([` +
        `'title'=>${phpString(token)},` +
        `'status'=>'published',` +
        `'created_by'=>$owner->id,` +
        `'registration_start'=>now()->subDay(),` +
        `'registration_end'=>now()->addDay(),` +
        `'start_date'=>now()->addDay()->toDateString(),` +
        `'end_date'=>now()->addDays(2)->toDateString(),` +
        `'quota'=>50,` +
        `'registered_count'=>0` +
        `]);` +
        `$form=$event->forms()->create([` +
        `'title'=>${phpString(token)},` +
        `'description'=>'E2E form submission boundary',` +
        `'visible_for'=>['public'],` +
        `'closed_at'=>${closedAt},` +
        `'metadata'=>${FORM_METADATA[mode]}` +
        `]);` +
        fieldStatements(mode) +
        extraUserStatement(mode, token) +
        `echo json_encode([` +
        `'token'=>${phpString(token)},` +
        `'ownerId'=>$owner->id,` +
        `'eventId'=>$event->id,` +
        `'formId'=>$form->id,` +
        `'fillPath'=>route('dashboard.events.forms.fill',['event'=>$event,'form'=>$form],false),` +
        `'submitPath'=>route('dashboard.forms.submission',['event'=>$event,'form'=>$form],false),` +
        `'users'=>$extra===null?[]:[['id'=>$extra->id,'email'=>$extra->email]]` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IFixture;
}

function cleanupFixture(fixture: IFixture): void {
    const userIds = fixture.users.map((user: IFixtureUser): string => phpString(user.id)).join(',');

    runArtisanCommand([
        'tinker',
        '--execute',
        `$event=App\\Models\\Event::withTrashed()->find(${phpString(fixture.eventId)});` +
            `if($event){` +
            // Job konfirmasi yang antre menulis `email_logs` dengan `event_id`, dan FK itu RESTRICT:
            // baris log harus hilang sebelum event dihapus.
            `App\\Models\\EmailLog::where('event_id',$event->id)->delete();` +
            `foreach(App\\Models\\Form::withTrashed()->where('event_id',$event->id)->get() as $eventForm){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$eventForm->id)->get() as $field){$field->forceDelete();}` +
            `App\\Models\\FormAnswer::where('form_id',$eventForm->id)->delete();` +
            `$eventForm->forceDelete();` +
            `}` +
            `$event->forceDelete();` +
            `}` +
            (userIds === '' ? '' : `App\\Models\\User::withTrashed()->whereIn('id',[${userIds}])->forceDelete();`),
    ]);
}

function readAnswers(formId: string): IAnswerSnapshot {
    const formIdLiteral = phpString(formId);
    const command =
        `$answer=App\\Models\\FormAnswer::where('form_id',${formIdLiteral})->first();` +
        `echo json_encode([` +
        `'count'=>App\\Models\\FormAnswer::where('form_id',${formIdLiteral})->count(),` +
        `'userId'=>$answer===null?null:$answer->user_id,` +
        `'reviewStatus'=>$answer===null||$answer->review_status===null?null:$answer->review_status->value,` +
        `'answerKeys'=>$answer===null?[]:array_keys($answer->answers ?? [])` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IAnswerSnapshot;
}

function answerCount(formId: string): number {
    return readAnswers(formId).count;
}

/** Baris guest bundle yang sudah terdaftar lebih dulu di form yang sama. */
function seedRegisteredGuest(formId: string, email: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `App\\Models\\FormAnswer::create([` +
            `'form_id'=>${phpString(formId)},` +
            `'user_id'=>null,` +
            `'invited_email'=>${phpString(email)},` +
            `'answers'=>['full_name'=>'Existing Guest']` +
            `]);`,
    ]);
}

interface IAnswerRowMeta {
    userId: string | null;
    role: string | null;
    groupToken: string | null;
}

/** Baris FormAnswer (user, peran, group token) untuk asersi DB; urutan tidak andalkan. */
function readAnswerRows(formId: string): IAnswerRowMeta[] {
    const command =
        `echo json_encode(App\\Models\\FormAnswer::where('form_id',${phpString(formId)})` +
        `->get(['user_id','registration_role','group_token'])->map(function($a){` +
        `return ['userId'=>$a->user_id,'role'=>$a->registration_role===null?null:$a->registration_role->value,'groupToken'=>$a->group_token];` +
        `})->values()->all());`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IAnswerRowMeta[];
}

/** FormAnswer aktif milik user tertentu; dipakai membuktikan guard duplikat. */
function seedRegisteredUserAnswer(formId: string, userId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `App\\Models\\FormAnswer::create(['form_id'=>${phpString(formId)},'user_id'=>${phpString(userId)},'answers'=>['full_name'=>'Existing Person']]);`,
    ]);
}

/** User nyata + FormAnswer aktif dengan email tertentu, untuk anomali bundle. */
function createRegisteredUserAnswer(formId: string, email: string): IFixtureUser {
    const command =
        `$user=App\\Models\\User::factory()->create(['name'=>'E2E Existing Bundle User','email'=>${phpString(email)}]);` +
        `App\\Models\\FormAnswer::create(['form_id'=>${phpString(formId)},'user_id'=>$user->id,'answers'=>['full_name'=>'Existing Bundle User']]);` +
        `echo json_encode(['id'=>$user->id,'email'=>$user->email]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IFixtureUser;
}

function firstUser(fixture: IFixture): IFixtureUser {
    const user = fixture.users[0];

    if (user === undefined) {
        throw new Error('Fixture did not create the extra user this test needs.');
    }

    return user;
}

function locationPath(location: string): string {
    if (location === '') {
        return '';
    }

    try {
        return new URL(location, environment.baseUrl).pathname;
    } catch {
        return location;
    }
}

/**
 * POST urlencoded lewat stack HTTP asli dengan sesi admin, tanpa mengikuti redirect.
 * `URLSearchParams` dipakai agar key array seperti `full_name[]=a&full_name[]=b` bisa dikirim.
 */
async function postSubmit(context: BrowserContext, submitPath: string, fields: string[][]): Promise<ISubmitResult> {
    const response = await context.request.post(submitPath, {
        headers: {
            Accept: 'text/html',
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-XSRF-TOKEN': await readXsrfToken(context),
        },
        data: new URLSearchParams(fields).toString(),
        maxRedirects: 0,
        failOnStatusCode: false,
    });

    return {
        status: response.status(),
        locationPath: locationPath(response.headers()['location'] ?? ''),
    };
}

/**
 * POST urlencoded dan ikuti redirect (default `maxRedirects`), lalu kembalikan
 * body halaman tujuan. Dipakai saat anomali hanya bisa dibuktikan dari pesan
 * server yang dirender sebagai `props.errors`, bukan dari status 302 saja.
 */
async function postSubmitFollowingRedirect(
    context: BrowserContext,
    submitPath: string,
    fields: string[][]
): Promise<{ status: number; body: string }> {
    const response = await context.request.post(submitPath, {
        headers: {
            Accept: 'text/html',
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-XSRF-TOKEN': await readXsrfToken(context),
        },
        data: new URLSearchParams(fields).toString(),
        failOnStatusCode: false,
    });

    return {
        status: response.status(),
        body: await response.text(),
    };
}

async function submitForm(page: Page): Promise<void> {
    await page.getByRole('button', { name: /submit registration/i }).click();

    // Dialog konfirmasi dirender async setelah submit; `count()` tidak menunggu sehingga
    // Enter bisa terkirim sebelum dialog ada dan submit tidak pernah terjadi.
    const confirm = page.getByRole('alertdialog').getByRole('button', { name: 'Submit', exact: true });
    await confirm.focus();
    await page.keyboard.press('Enter');
}

const SUBMIT_DOCUMENT_ATTR = 'data-e2e-submit-document';

/**
 * Tandai dokumen yang mengirim submit. Reload penuh dari dev-server (mis. edit paralel di
 * `resources/views/**` atau `routes/**`, atau re-optimasi dependency Vite) mengganti dokumen
 * sehingga atribut ini hilang; assert URL sesudahnya hanya bermakna selama dokumen bertahan.
 */
async function markSubmitDocument(page: Page): Promise<void> {
    await page.locator('body').evaluate((body: HTMLElement, attribute: string): void => {
        body.setAttribute(attribute, 'pending');
    }, SUBMIT_DOCUMENT_ATTR);
}

/** True bila dokumen pengirim submit masih hidup (tanpa reload penuh). */
async function submitDocumentSurvived(page: Page): Promise<boolean> {
    return (await page.locator(`body[${SUBMIT_DOCUMENT_ATTR}="pending"]`).count()) > 0;
}

/**
 * Submit lewat dialog lalu verifikasi redirect.
 *
 * Post-submit Inertia adalah XHR; kontrak server yang deterministik adalah response POST
 * 302 + `Location` ke halaman event (plus header `X-Inertia` yang membuktikan jalur Inertia,
 * bukan native form POST). Reload penuh dari dev-server bisa mengganti dokumen setelah 302
 * diterima sehingga redirect client-side tidak pernah mendarat; karena itu assert URL hanya
 * dijalankan bila dokumen pengirim masih hidup. Timeout response 60 dtk karena queue E2E
 * `sync` menjalankan job email (jitter 5-10 dtk per job) di dalam request, bukan memperbesar
 * timeout global.
 */
async function submitFormAndAssertRedirect(page: Page, submitPath: string): Promise<void> {
    await markSubmitDocument(page);

    const submitted = page.waitForResponse(
        (response: Response): boolean =>
            response.request().method() === 'POST' && new URL(response.url()).pathname === submitPath,
        { timeout: 60_000 }
    );

    await submitForm(page);

    const response = await submitted;

    expect(response.request().headers()['x-inertia'], 'submit harus lewat jalur Inertia (XHR)').toBe('true');
    expect(response.status(), 'submit registrasi harus redirect 302').toBe(302);
    expect(locationPath(response.headers()['location'] ?? ''), 'redirect submit harus ke halaman event').toMatch(
        /^\/admin\/events\//
    );

    if (await submitDocumentSurvived(page)) {
        await expect(page).toHaveURL(/\/admin\/events\//, { timeout: 10_000 });
    }
}

/**
 * Chrome menahan submit `input[type=email]` yang dianggap tidak valid sebelum request keluar,
 * sehingga validasi server tidak pernah tercapai. Matikan hanya validasi native untuk anomali ini.
 */
async function disableNativeValidation(page: Page): Promise<void> {
    await page
        .locator('form')
        .filter({ has: page.getByRole('button', { name: /submit registration/i }) })
        .evaluate((form: HTMLElement): void => {
            form.setAttribute('novalidate', '');
        });
}

test.describe('AUDIT-P1-004 form submission request boundary', () => {
    test('anomaly: empty single registration stays on the fill page with a required error', async ({ page }) => {
        const fixture = createFixture('single');

        try {
            await page.goto(fixture.fillPath);
            await submitForm(page);

            await expect(page).toHaveURL(/\/forms\/[^/]+\/fill/);
            await expect(page.getByText(/wajib diisi/i).first()).toBeVisible();
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('single registration submits and stores exactly one answer', async ({ page }) => {
        const fixture = createFixture('single');

        try {
            await page.goto(fixture.fillPath);
            await page.locator('#full_name').fill('Jane Doe');
            await page.locator('#email_contact').fill('jane.doe@example.test');
            await submitForm(page);

            await expect(page).toHaveURL(/\/admin\/events\//);
            expect(answerCount(fixture.formId)).toBe(1);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: invalid email format is rejected with a field error', async ({ page }) => {
        const fixture = createFixture('single');

        try {
            await page.goto(fixture.fillPath);
            await page.locator('#full_name').fill('Jane Doe');
            await page.locator('#email_contact').fill('not-an-email');
            await disableNativeValidation(page);
            await submitForm(page);

            await expect(page).toHaveURL(/\/forms\/[^/]+\/fill/);
            await expect(page.getByText(/alamat email yang valid/i).first()).toBeVisible();
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: over-long full name is rejected with a max length error', async ({ page }) => {
        const fixture = createFixture('single');

        try {
            await page.goto(fixture.fillPath);
            await page.locator('#full_name').fill('A'.repeat(101));
            await page.locator('#email_contact').fill('jane.doe@example.test');
            await submitForm(page);

            await expect(page).toHaveURL(/\/forms\/[^/]+\/fill/);
            await expect(page.getByText(/maksimal 100 karakter/i).first()).toBeVisible();
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: array posted to a scalar field is rejected without writing a row', async ({ page }) => {
        const fixture = createFixture('single');

        try {
            await page.goto(fixture.fillPath);

            const result = await postSubmit(page.context(), fixture.submitPath, [
                ['full_name[]', 'Jane'],
                ['full_name[]', 'Doe'],
                ['email_contact', 'jane.doe@example.test'],
            ]);

            expect(result.status).toBe(302);
            expect(result.locationPath).toBe(fixture.fillPath);
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: foreign payload keys never reach the stored answer', async ({ page }) => {
        const fixture = createFixture('single');
        const decoy = firstUser(fixture);

        try {
            await page.goto(fixture.fillPath);

            const result = await postSubmit(page.context(), fixture.submitPath, [
                ['full_name', 'Jane Doe'],
                ['email_contact', 'jane.doe@example.test'],
                ['user_id', decoy.id],
                ['review_status', 'accepted'],
                ['form_id', decoy.id],
                ['registered_count', '999'],
                ['answers[full_name]', 'Injected'],
            ]);

            expect(result.status).toBe(302);
            expect(result.locationPath).toContain('/admin/events/');

            const snapshot = readAnswers(fixture.formId);
            expect(snapshot.count).toBe(1);
            expect(snapshot.userId).toBe(fixture.ownerId);
            expect(snapshot.reviewStatus).toBe('pending');
            expect(snapshot.answerKeys.sort()).toEqual(['email_contact', 'full_name']);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: team registration rejects the leader own email', async ({ page }) => {
        const fixture = createFixture('team');

        try {
            await page.goto(fixture.fillPath);

            const result = await postSubmit(page.context(), fixture.submitPath, [
                ['full_name', 'Team Leader'],
                ['team_member_emails[0]', environment.adminEmail],
            ]);

            expect(result.status).toBe(302);
            expect(result.locationPath).toBe(fixture.fillPath);
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: team registration rejects an unknown teammate account', async ({ page }) => {
        const fixture = createFixture('team');

        try {
            await page.goto(fixture.fillPath);

            const result = await postSubmit(page.context(), fixture.submitPath, [
                ['full_name', 'Team Leader'],
                ['team_member_emails[0]', `e2e-unknown-${fixture.token}@example.test`],
            ]);

            expect(result.status).toBe(302);
            expect(result.locationPath).toBe(fixture.fillPath);
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: team registration rejects duplicate teammate emails', async ({ page }) => {
        const fixture = createFixture('team3');
        const mate = firstUser(fixture);

        try {
            await page.goto(fixture.fillPath);

            const result = await postSubmitFollowingRedirect(page.context(), fixture.submitPath, [
                ['full_name', 'Team Leader'],
                ['team_member_emails[0]', mate.email],
                ['team_member_emails[1]', mate.email],
            ]);

            expect(result.status).toBe(200);
            expect(result.body).toContain('must be unique');
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: bundle registration rejects an empty duplicatable slot', async ({ page }) => {
        const fixture = createFixture('bundle');

        try {
            await page.goto(fixture.fillPath);

            const result = await postSubmit(page.context(), fixture.submitPath, [
                ['full_name', 'Lead Person'],
                ['team_member_emails[0]', `e2e-bundle-slot-${fixture.token}@example.test`],
            ]);

            expect(result.status).toBe(302);
            expect(result.locationPath).toBe(fixture.fillPath);
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: bundle registration rejects an already registered guest email', async ({ page }) => {
        const fixture = createFixture('bundle');
        const guestEmail = `e2e-bundle-guest-${fixture.token}@example.test`;

        try {
            seedRegisteredGuest(fixture.formId, guestEmail);
            await page.goto(fixture.fillPath);

            const result = await postSubmit(page.context(), fixture.submitPath, [
                ['full_name', 'Lead Person'],
                ['bundle__full_name__0', 'Second Person'],
                ['team_member_emails[0]', guestEmail],
            ]);

            expect(result.status).toBe(302);
            expect(result.locationPath).toBe(fixture.fillPath);
            expect(answerCount(fixture.formId)).toBe(1);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: a closed form is blocked before validation runs', async ({ page }) => {
        const fixture = createFixture('closed');

        try {
            await page.goto(fixture.fillPath);

            const result = await postSubmit(page.context(), fixture.submitPath, []);

            expect(result.status).toBe(302);
            expect(result.locationPath).toBe(fixture.fillPath);
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('team registration submits leader and member rows', async ({ page }) => {
        test.setTimeout(120_000);
        const fixture = createFixture('team');
        const mate = firstUser(fixture);

        try {
            await page.goto(fixture.fillPath);
            await page.locator('#full_name').fill('Team Leader');
            await page.locator('#team_member_emails_1').fill(mate.email);
            await submitFormAndAssertRedirect(page, fixture.submitPath);

            expect(answerCount(fixture.formId)).toBe(2);

            const rows = readAnswerRows(fixture.formId);
            const leaderRow = rows.find((row) => row.userId === fixture.ownerId);
            const memberRow = rows.find((row) => row.userId === mate.id);

            expect(leaderRow?.role).toBe('leader');
            expect(memberRow?.role).toBe('member');
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: team registration rejects a teammate already registered on the form', async ({ page }) => {
        const fixture = createFixture('team');
        const mate = firstUser(fixture);

        try {
            seedRegisteredUserAnswer(fixture.formId, mate.id);
            await page.goto(fixture.fillPath);

            const result = await postSubmitFollowingRedirect(page.context(), fixture.submitPath, [
                ['full_name', 'Team Leader'],
                ['team_member_emails[0]', mate.email],
            ]);

            expect(result.status).toBe(200);
            expect(result.body).toMatch(/already registered/i);
            expect(answerCount(fixture.formId)).toBe(1);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('bundle registration submits leader and participant under one group token', async ({ page }) => {
        test.setTimeout(120_000);
        const fixture = createFixture('bundle');
        const guestEmail = `e2e-bundle-happy-${fixture.token}@example.test`;

        try {
            await page.goto(fixture.fillPath);
            await page.locator('#full_name').fill('Lead Participant');
            await page.locator('#bundle__full_name__0').fill('Second Participant');
            await page.locator('#team_member_emails_1').fill(guestEmail);
            await submitFormAndAssertRedirect(page, fixture.submitPath);

            expect(answerCount(fixture.formId)).toBe(2);

            const tokens = readAnswerRows(fixture.formId).map((row) => row.groupToken);

            expect(tokens).toHaveLength(2);
            expect(tokens[0]).not.toBeNull();
            expect(tokens[0]).toBe(tokens[1]);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: bundle registration rejects a member account already registered on the form', async ({ page }) => {
        const fixture = createFixture('bundle');
        const guestEmail = `e2e-bundle-registered-${fixture.token}@example.test`;

        try {
            const registeredUser = createRegisteredUserAnswer(fixture.formId, guestEmail);
            fixture.users.push(registeredUser);

            await page.goto(fixture.fillPath);

            const result = await postSubmitFollowingRedirect(page.context(), fixture.submitPath, [
                ['full_name', 'Lead Participant'],
                ['bundle__full_name__0', 'Second Participant'],
                ['team_member_emails[0]', guestEmail],
            ]);

            expect(result.status).toBe(200);
            expect(result.body).toMatch(/already registered/i);
            expect(answerCount(fixture.formId)).toBe(1);
        } finally {
            cleanupFixture(fixture);
        }
    });

    test('anomaly: unauthenticated submit is redirected to the login page', async ({ browser }) => {
        const fixture = createFixture('single');
        // Playwright Test meneruskan storageState project ke `browser.newContext()`;
        // state kosong eksplisit membuat konteks ini benar-benar guest.
        const guestContext = await browser.newContext({
            baseURL: environment.baseUrl,
            storageState: { cookies: [], origins: [] },
        });

        try {
            // GET halaman fill sebagai guest: dapat sesi + cookie XSRF agar CSRF lolos
            // dan yang menolak request adalah middleware `auth`, bukan CSRF.
            const guestPage = await guestContext.newPage();
            await guestPage.goto(fixture.fillPath);

            const result = await postSubmit(guestContext, fixture.submitPath, [
                ['full_name', 'Guest Intruder'],
                ['email_contact', 'guest.intruder@example.test'],
            ]);

            expect(result.status).toBe(302);
            expect(result.locationPath).toBe('/auth/login');
            expect(answerCount(fixture.formId)).toBe(0);
        } finally {
            await guestContext.close();
            cleanupFixture(fixture);
        }
    });
});
