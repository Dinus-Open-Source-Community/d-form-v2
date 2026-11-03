import { expect, test, type Page } from '@playwright/test';
import { runArtisanCommand, runArtisan } from './support/artisan';

const ADMIN = { email: 'stress-user-1@stress.test', password: 'password' } as const;
test.use({ storageState: { cookies: [], origins: [] } });

interface IFormAnswerFixtureOptions {
    answers?: number;
    statuses?: string[];
}

const fixtureTokens: string[] = [];

function createFixture(options: IFormAnswerFixtureOptions = {}): { url: string; token: string } {
    const token = `e2e-answers-${Date.now()}-${fixtureTokens.length}`;
    fixtureTokens.push(token);
    const answerCount = options.answers ?? 3;
    const statuses = options.statuses ?? ['pending', 'accepted', 'rejected'];
    const statusLiteral = statuses.map((status) => `'${status}'`).join(',');
    const command =
        `$owner=App\\Models\\User::where('email','${ADMIN.email}')->firstOrFail();` +
        `$event=App\\Models\\Event::where('created_by',$owner->id)->firstOrFail();` +
        `$form=$event->forms()->create(['title'=>'${token}','description'=>'Fixture E2E','visible_for'=>['public'],'closed_at'=>now()->addDay()]);` +
        `$form->formFields()->create(['label'=>'Motivasi','name'=>'motivasi','input_type'=>'input','metadata'=>[],'order'=>0]);` +
        `$form->formFields()->create(['label'=>'Lampiran','name'=>'lampiran','input_type'=>'fileUpload','metadata'=>[],'order'=>1]);` +
        `foreach(range(1,${answerCount}) as $i){$user=App\\Models\\User::factory()->create(['name'=>'${token} '.($i)]);App\\Models\\FormAnswer::create(['form_id'=>$form->id,'user_id'=>$user->id,'answers'=>['motivasi'=>'Contoh jawaban '.($i),'lampiran'=>'uploads/e2e/sample.pdf'],'review_status'=>[${statusLiteral}][($i-1)%${statuses.length}]]);}` +
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

async function signIn(page: Page): Promise<void> {
    await page.goto('/auth/login');
    await page.locator('#login-email').fill(ADMIN.email);
    await page.locator('#login-password').fill(ADMIN.password);
    await page
        .locator('form')
        .filter({ has: page.locator('#login-email') })
        .getByRole('button', { name: /masuk/i })
        .click();
    await expect(page).not.toHaveURL(/\/auth\/login/);
}

test.describe('jawaban desktop', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test('default tabel dan pilihan tampilan bertahan setelah reload', async ({ page }) => {
        const fixture = createFixture();
        await signIn(page);
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
        await signIn(page);
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

    test('pencarian, filter status, dan pagination klien', async ({ page }) => {
        const fixture = createFixture({ answers: 30, statuses: Array.from({ length: 30 }, () => 'pending') });
        await signIn(page);
        await page.goto(fixture.url);

        await expect(page.getByTestId('form-answers-table').locator('tbody tr')).toHaveCount(25);
        await expect(page.getByText('1–25 dari 30')).toBeVisible();
        await page.getByRole('button', { name: 'Berikutnya' }).click();
        await expect(page.getByTestId('form-answers-table').locator('tbody tr')).toHaveCount(5);
        await page.getByPlaceholder('Cari nama atau email').fill('tidak-ada-hasil');
        await expect(page.getByText('Tidak ada jawaban yang cocok')).toBeVisible();
        await page.getByRole('button', { name: 'Reset filter' }).click();
        await expect(page.getByTestId('form-answers-table').locator('tbody tr')).toHaveCount(25);
    });
});
