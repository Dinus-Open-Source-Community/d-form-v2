import { expect as baseExpect, test } from '@playwright/test';
import { readEnvironment } from './support/environment';
import { runArtisanCommand } from './support/artisan';

// Job email sinkron di queue E2E bisa berjitter beberapa detik setelah PATCH review.
const expect = baseExpect.configure({ timeout: 60_000 });

const environment = readEnvironment();

/** Token unik per run agar baris fixture tidak bertabrakan dengan run lain. */
const RUN_TOKEN = `e2e-audit-005-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

interface IReviewFixture {
    eventId: string;
    formId: string;
    answerId: string;
    showPath: string;
}

function assertCredentials(): void {
    if (environment.adminEmail === '' || environment.adminPassword === '') {
        throw new Error('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD must be set for FE-AUDIT-005 E2E.');
    }
}

/** Kutip nilai sebagai literal string PHP. */
function phpString(value: string): string {
    return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

/** Event + form + satu submisi `pending` milik admin, untuk alur review nyata. */
function createReviewFixture(): IReviewFixture {
    assertCredentials();

    const token = `${RUN_TOKEN}-review`;
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
        `'description'=>'E2E FE-AUDIT-005 review narrowing',` +
        `'visible_for'=>['public'],` +
        `'closed_at'=>now()->addDay(),` +
        `'metadata'=>['purpose'=>'registration','registration_mode'=>'single']` +
        `]);` +
        `$form->formFields()->create([` +
        `'label'=>'Full Name','name'=>'full_name','input_type'=>'input',` +
        `'metadata'=>['type'=>'text','rules'=>['required'=>true]],'order'=>0` +
        `]);` +
        `$answer=App\\Models\\FormAnswer::create([` +
        `'form_id'=>$form->id,` +
        `'user_id'=>$owner->id,` +
        `'answers'=>['full_name'=>'E2E Review Subject']` +
        `]);` +
        `echo json_encode([` +
        `'eventId'=>$event->id,` +
        `'formId'=>$form->id,` +
        `'answerId'=>$answer->id,` +
        `'showPath'=>route('dashboard.events.forms.show',['event'=>$event,'form'=>$form],false)` +
        `]);`;

    return JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as IReviewFixture;
}

/** Hapus fixture termasuk log email yang menahan FK `events`. */
function cleanupReviewFixture(fixture: IReviewFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$event=App\\Models\\Event::withTrashed()->find(${phpString(fixture.eventId)});` +
            `if($event){` +
            `App\\Models\\EmailLog::where('event_id',$event->id)->delete();` +
            `foreach(App\\Models\\Form::withTrashed()->where('event_id',$event->id)->get() as $eventForm){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$eventForm->id)->get() as $field){$field->forceDelete();}` +
            `App\\Models\\FormAnswer::where('form_id',$eventForm->id)->delete();` +
            `$eventForm->forceDelete();` +
            `}` +
            `$event->forceDelete();` +
            `}`,
    ]);
}

/** Baca status review langsung dari database sebagai bukti PATCH nyata. */
function readReviewStatus(answerId: string): string | null {
    const command =
        `$answer=App\\Models\\FormAnswer::query()->find(${phpString(answerId)});` +
        `echo json_encode(['status'=>$answer===null||$answer->review_status===null?null:$answer->review_status->value]);`;
    const parsed = JSON.parse(runArtisanCommand(['tinker', '--execute', command])) as { status: string | null };

    return parsed.status;
}

test.describe('FE-AUDIT-005 response narrowing', () => {
    test('review: menerima submisi pending menampilkan sukses dan menyimpan status accepted', async ({ page }) => {
        test.setTimeout(120_000);
        const fixture = createReviewFixture();

        try {
            await page.goto(`${fixture.showPath}?tab=jawaban`);

            const acceptButton = page.getByRole('button', { name: /Terima jawaban dari/i }).first();
            await expect(acceptButton).toBeVisible();
            await acceptButton.click();

            await expect(page.locator('[data-sonner-toast]').filter({ hasText: /Jawaban diterima/i })).toBeVisible();
            expect(readReviewStatus(fixture.answerId)).toBe('accepted');
        } finally {
            cleanupReviewFixture(fixture);
        }
    });

    test('anomali: review 200 dengan body tidak sesuai kontrak menjadi error feedback, bukan sukses', async ({
        page,
    }) => {
        const fixture = createReviewFixture();
        const pageErrors: string[] = [];
        page.on('pageerror', (error: Error): void => {
            pageErrors.push(error.message);
        });

        try {
            await page.route('**/submissions/*/review', (route) =>
                route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ unexpected: true }),
                })
            );

            await page.goto(`${fixture.showPath}?tab=jawaban`);
            await page
                .getByRole('button', { name: /Terima jawaban dari/i })
                .first()
                .click();

            await expect(
                page.locator('[data-sonner-toast]').filter({ hasText: 'Tidak dapat menghubungi server. Coba lagi.' })
            ).toBeVisible();
            await expect(page.locator('[data-sonner-toast]').filter({ hasText: /Jawaban diterima/i })).toHaveCount(0);
            expect(readReviewStatus(fixture.answerId)).toBe('pending');
            expect(pageErrors).toEqual([]);
        } finally {
            cleanupReviewFixture(fixture);
        }
    });

    test('scan: kode manual tidak valid menampilkan feedback 422, bukan sukses', async ({ page }) => {
        await page.goto('/admin/scan');

        const input = page.getByPlaceholder(/OPREC-2026-00001/i);
        await expect(input).toBeVisible();
        await input.fill(`INVALID-${RUN_TOKEN}`);
        await page.getByRole('button', { name: /Proses check-in/i }).click();

        await expect(page.getByText('Tidak dapat diproses').first()).toBeVisible();
        await expect(page.getByText('QR tidak valid').first()).toBeVisible();
    });

    test('scan: feed dengan baris/payload rusak tidak menambah baris palsu atau pageerror', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error: Error): void => {
            pageErrors.push(error.message);
        });

        let feedCalls = 0;
        await page.route('**/admin/scan/feed**', (route) => {
            feedCalls += 1;
            const body =
                feedCalls === 1
                    ? JSON.stringify({
                          cursor: '2026-01-01T00:00:00+00:00',
                          rows: [null, 'junk', { id: '', name: 'Ghost Row' }],
                      })
                    : JSON.stringify(['bukan record']);

            return route.fulfill({ status: 200, contentType: 'application/json', body });
        });

        const firstFeed = page.waitForResponse((response) => response.url().includes('/admin/scan/feed'));
        const secondFeed = page.waitForResponse((response) => response.url().includes('/admin/scan/feed'));

        await page.goto('/admin/scan');
        await firstFeed;
        await secondFeed;

        await expect(page.getByText('Feed scan tertunda').first()).toBeVisible();
        await expect(page.getByText('Ghost Row')).toHaveCount(0);
        expect(pageErrors).toEqual([]);
    });

    test('scan: envelope store 200 rusak menjadi error feedback, bukan sukses', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error: Error): void => {
            pageErrors.push(error.message);
        });

        await page.route('**/admin/scan', (route) => {
            if (route.request().method() !== 'POST') {
                return route.continue();
            }

            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ unexpected: true }),
            });
        });

        await page.goto('/admin/scan');

        const input = page.getByPlaceholder(/OPREC-2026-00001/i);
        await input.fill(`MALFORMED-${RUN_TOKEN}`);
        await page.getByRole('button', { name: /Proses check-in/i }).click();

        await expect(
            page.locator('[data-sonner-toast]').filter({ hasText: 'Respons server tidak sesuai kontrak.' })
        ).toBeVisible();
        await expect(page.locator('[data-sonner-toast]').filter({ hasText: /Check-in berhasil/i })).toHaveCount(0);
        await expect(page.getByText('Respons tidak valid').first()).toBeVisible();
        expect(pageErrors).toEqual([]);
    });
});
