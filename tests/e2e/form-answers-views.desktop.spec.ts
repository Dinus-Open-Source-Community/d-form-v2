import { expect, test } from '@playwright/test';
import { runArtisanCommand, runArtisan } from './support/artisan';

const ADMIN = { email: 'stress-user-1@stress.test', password: 'password' } as const;

interface IFormAnswerFixtureOptions {
    answers?: number;
    statuses?: string[];
    guest?: boolean;
    mixedTypes?: boolean;
}

const fixtureTokens: string[] = [];

function createFixture(options: IFormAnswerFixtureOptions = {}): { url: string; token: string } {
    const token = `e2e-answers-${Date.now()}-${fixtureTokens.length}`;
    fixtureTokens.push(token);
    const answerCount = options.answers ?? 3;
    const statuses = options.statuses ?? ['pending', 'accepted', 'rejected'];
    const statusLiteral = statuses.map((status) => `'${status}'`).join(',');
    const userAssignment = options.guest
        ? `$user=$i===1?null:App\\Models\\User::factory()->create(['name'=>'${token} '.($i)]);`
        : `$user=App\\Models\\User::factory()->create(['name'=>'${token} '.($i)]);`;
    const motivationValue = options.mixedTypes ? `$i===1?['Satu','Dua']:($i===2?true:null)` : `'Contoh jawaban '.($i)`;
    const attachmentValue = options.mixedTypes ? `$i===1?'uploads/e2e/sample.pdf':''` : `'uploads/e2e/sample.pdf'`;
    const answerSeed =
        answerCount > 0
            ? `foreach(range(1,${answerCount}) as $i){${userAssignment}App\\Models\\FormAnswer::create(['form_id'=>$form->id,'user_id'=>$user?->id,'answers'=>['motivasi'=>${motivationValue},'lampiran'=>${attachmentValue}],'review_status'=>[${statusLiteral}][($i-1)%${statuses.length}]]);}`
            : '';
    const command =
        `$owner=App\\Models\\User::where('email','${ADMIN.email}')->firstOrFail();` +
        `$event=App\\Models\\Event::where('created_by',$owner->id)->firstOrFail();` +
        `$form=$event->forms()->create(['title'=>'${token}','description'=>'Fixture E2E','visible_for'=>['public'],'closed_at'=>now()->addDay()]);` +
        `$form->formFields()->create(['label'=>'Motivasi','name'=>'motivasi','input_type'=>'input','metadata'=>[],'order'=>0]);` +
        `$form->formFields()->create(['label'=>'Lampiran','name'=>'lampiran','input_type'=>'fileUpload','metadata'=>[],'order'=>1]);` +
        answerSeed +
        `echo(route('dashboard.events.forms.show',['event'=>$event,'form'=>$form],false).'?tab=jawaban');`;

    return { url: runArtisanCommand(['tinker', '--execute', command]), token };
}

test.afterEach(() => {
    while (fixtureTokens.length > 0) {
        const token = fixtureTokens.pop();
        if (token) {
            runArtisan(
                `$forms=App\\Models\\Form::where('title','${token}')->get(); foreach($forms as $form){$users=$form->answers()->pluck('user_id');$form->answers()->delete();$form->formFields()->delete();$form->delete();App\\Models\\User::whereIn('id',$users)->delete();}`
            );
        }
    }
});

test.describe('jawaban desktop', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test('default tabel dan pilihan tampilan bertahan setelah reload', async ({ page }) => {
        const fixture = createFixture();
        await page.goto(fixture.url);

        const group = page.getByRole('group', { name: 'Tampilan daftar jawaban' });
        await expect(group.getByRole('button', { name: 'Tabel' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('form-answers-table')).toBeVisible();
        await group.getByRole('button', { name: 'Kartu' }).click();
        await expect(page.getByTestId('form-answers-card-grid')).toBeVisible();
        await page.reload();
        await expect(group.getByRole('button', { name: 'Kartu' })).toHaveAttribute('aria-pressed', 'true');
        await expect(group.getByRole('button', { name: 'Split' })).toHaveCount(0);
    });

    test('tabel memakai label field, kolom berkas, dan aksi review', async ({ page }) => {
        const fixture = createFixture({ answers: 1 });
        await page.goto(fixture.url);

        const table = page.getByTestId('form-answers-table');
        await expect(table.getByRole('columnheader', { name: 'Motivasi' })).toBeVisible();
        await expect(table.getByRole('columnheader', { name: 'Lampiran' })).toBeVisible();
        await table
            .getByRole('button', { name: /Terima jawaban/ })
            .first()
            .click();
        await expect(table.getByText('Diterima')).toBeVisible();
    });

    test('kartu menampilkan ringkasan dan aksi review', async ({ page }) => {
        const fixture = createFixture({ answers: 2 });
        await page.goto(fixture.url);

        await page
            .getByRole('group', { name: 'Tampilan daftar jawaban' })
            .getByRole('button', { name: 'Kartu' })
            .click();
        const grid = page.getByTestId('form-answers-card-grid');
        await expect(grid.getByTestId('form-answer-mobile-card')).toHaveCount(2);
        await expect(grid.getByText('Contoh jawaban 1')).toBeVisible();
        await grid
            .getByRole('button', { name: /Tolak jawaban/ })
            .first()
            .click();
        await expect(grid.getByText('Ditolak')).toBeVisible();
    });

    test('pencarian, filter status, dan pagination klien', async ({ page }) => {
        const fixture = createFixture({ answers: 30, statuses: ['pending', 'accepted', 'rejected'] });
        await page.goto(fixture.url);

        await expect(page.getByTestId('form-answers-table').locator('tbody tr')).toHaveCount(25);
        await expect(page.getByText('1–25 dari 30')).toBeVisible();
        await expect(page.getByLabel('Ukuran halaman')).toHaveValue('25');
        await expect(page.getByLabel('Ukuran halaman').locator('option')).toHaveCount(3);
        await page.getByRole('button', { name: 'Berikutnya' }).click();
        await expect(page.getByTestId('form-answers-table').locator('tbody tr')).toHaveCount(5);
        await page.getByRole('group', { name: 'Filter status' }).getByRole('button', { name: 'Ditolak' }).click();
        await expect(page.getByTestId('form-answers-table').locator('tbody tr')).toHaveCount(10);
        await page
            .getByRole('group', { name: 'Tampilan daftar jawaban' })
            .getByRole('button', { name: 'Kartu' })
            .click();
        await expect(page.getByTestId('form-answers-card-grid').getByTestId('form-answer-mobile-card')).toHaveCount(10);
        await page.getByPlaceholder('Cari nama atau email').fill('tidak-ada-hasil');
        await expect(page.getByText('Tidak ada jawaban yang cocok')).toBeVisible();
        await page.getByRole('button', { name: 'Reset filter' }).click();
        await expect(page.getByTestId('form-answers-card-grid').getByTestId('form-answer-mobile-card')).toHaveCount(25);
        await page
            .getByRole('group', { name: 'Tampilan daftar jawaban' })
            .getByRole('button', { name: 'Tabel' })
            .click();
        await expect(page.getByTestId('form-answers-table').locator('tbody tr')).toHaveCount(25);
    });

    test('keadaan kosong total berbeda dari filter kosong dan guest aman', async ({ page }) => {
        const emptyFixture = createFixture({ answers: 0 });
        await page.goto(emptyFixture.url);
        await expect(page.getByText('Belum ada jawaban')).toBeVisible();

        const fixture = createFixture({ answers: 2, guest: true, mixedTypes: true });
        await page.goto(fixture.url);
        await page.getByPlaceholder('Cari nama atau email').fill('tidak-ada-hasil');
        await expect(page.getByText('Tidak ada jawaban yang cocok')).toBeVisible();
        await expect(page.getByText('Belum ada jawaban')).toHaveCount(0);
        await page.getByRole('button', { name: 'Reset filter' }).click();
        await expect(page.getByText('Tanpa nama')).toBeVisible();
        await expect(page.locator('body')).not.toContainText('undefined');
    });

    test('reload jawaban gagal menampilkan pesan dan aksi coba lagi', async ({ page }) => {
        const fixture = createFixture({ answers: 1 });
        await page.goto(fixture.url);

        let failedReload = false;
        await page.route(
            (url) => url.pathname.includes('/admin/events/') && url.pathname.includes('/forms/'),
            async (route) => {
                const request = route.request();
                const partialData = request.headers()['x-inertia-partial-data'] ?? '';
                const isSubmissionsReload =
                    request.method() === 'GET' && partialData.split(',').includes('submissions');

                if (!isSubmissionsReload) {
                    await route.continue();
                    return;
                }

                if (!failedReload) {
                    failedReload = true;
                    await route.fulfill({
                        status: 500,
                        headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true' },
                        body: JSON.stringify({ message: 'Jawaban gagal dimuat.' }),
                    });
                    return;
                }

                await route.continue();
            }
        );

        await page
            .getByTestId('form-answers-table')
            .getByRole('button', { name: /Terima jawaban/ })
            .click();

        const reloadError = page.getByRole('alert');
        await expect(reloadError).toContainText('Jawaban gagal dimuat.');
        const retryButton = reloadError.getByRole('button', { name: 'Coba lagi' });
        await expect(retryButton).toBeVisible();

        await retryButton.click();
        await expect(reloadError).toHaveCount(0);
    });
});
