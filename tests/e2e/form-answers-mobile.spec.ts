import { expect, test, type Page } from '@playwright/test';
import { runArtisanCommand, runArtisan } from './support/artisan';

const ADMIN = { email: 'stress-user-1@stress.test', password: 'password' } as const;
// Spec ini login sendiri, jadi konteks harus guest; storageState admin membuat /auth/login ter-redirect.
test.use({ storageState: { cookies: [], origins: [] } });

const STORAGE_KEY = 'd-form:form-answers:mobile-view';
const PLACEHOLDER_PNG = Uint8Array.from(
    atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2ioAAAAASUVORK5CYII='),
    (character: string) => character.charCodeAt(0)
);

/** Create a small isolated form fixture and return its dashboard URL. */
function createFixture(withAnswers: boolean = true): { url: string; token: string } {
    const token = `e2e-mobile-${Date.now()}`;
    fixtureTokens.push(token);
    const command =
        `$owner=App\\Models\\User::where('email','${ADMIN.email}')->firstOrFail();` +
        `$event=App\\Models\\Event::where('created_by',$owner->id)->firstOrFail();` +
        `$form=$event->forms()->create(['title'=>'${token}','description'=>'Fixture E2E','visible_for'=>['public'],'closed_at'=>now()->addDay()]);` +
        `foreach([['Nama','nama','input'],['Lampiran','lampiran','fileUpload']] as $i=>$field){$form->formFields()->create(['label'=>$field[0],'name'=>$field[1],'input_type'=>$field[2],'metadata'=>[],'order'=>$i]);}` +
        (withAnswers
            ? `foreach(['pending','accepted','rejected'] as $i=>$status){$user=App\\Models\\User::factory()->create(['name'=>'${token} '.($i+1)]);App\\Models\\FormAnswer::create(['form_id'=>$form->id,'user_id'=>$user->id,'answers'=>['nama'=>'Contoh jawaban '.($i+1),'lampiran'=>$i===0?'uploads/e2e/sample.pdf':''],'review_status'=>$status]);}`
            : '') +
        `echo(route('dashboard.events.forms.show',['event'=>$event,'form'=>$form],false).'?tab=jawaban');`;

    return { url: runArtisanCommand(['tinker', '--execute', command]), token };
}

const fixtureTokens: string[] = [];

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

/** Sign in as the seeded dashboard administrator. */
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

test.describe('jawaban mobile', () => {
    test.use({ viewport: { width: 375, height: 812 } });

    test('kartu, daftar, pilihan tersimpan, nilai rusak, dan keadaan kosong', async ({ page }) => {
        const fixture = createFixture();
        await signIn(page);
        await page.goto(fixture.url);

        await expect(page.locator('main table')).toHaveCount(0);
        const toggle = page.getByRole('group', { name: 'Tampilan daftar jawaban' });
        const card = toggle.getByRole('button', { name: 'Kartu' });
        const list = toggle.getByRole('button', { name: 'Daftar' });
        await expect(card).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByText('Contoh jawaban 1')).toBeVisible();
        await page.screenshot({ path: '/tmp/task-212-mobile-card.png', fullPage: true });

        await list.click();
        await expect(list).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByRole('listitem', { name: /Lihat detail jawaban nomor/ })).toHaveCount(3);
        await page.screenshot({ path: '/tmp/task-212-mobile-list.png', fullPage: true });
        await page.reload();
        await expect(list).toHaveAttribute('aria-pressed', 'true');
        expect(await page.evaluate((key) => localStorage.getItem(key), STORAGE_KEY)).toBe('list');

        await page.addInitScript((key) => localStorage.setItem(key, 'grid'), STORAGE_KEY);
        await page.reload();
        await expect(card).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('main table')).toHaveCount(0);

        const emptyFixture = createFixture(false);
        await page.goto(emptyFixture.url);
        await expect(page.getByText('Belum ada jawaban')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Tampilan daftar jawaban' })).toHaveCount(0);
        await page.screenshot({ path: '/tmp/task-212-mobile-empty.png', fullPage: true });
    });

    test('pilihan halaman, aksi kartu, buka detail lewat isi baris, dan tanpa overflow', async ({ page }) => {
        const fixture = createFixture();
        await signIn(page);
        await page.goto(fixture.url);

        const selectPage = page.getByRole('checkbox', { name: 'Pilih semua jawaban di halaman ini' });
        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'true');

        const firstRowCheckbox = page.getByRole('checkbox', { name: /Pilih jawaban nomor 1/ });
        await firstRowCheckbox.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'mixed');
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await page.route('**/submissions/*/review', (route) =>
            route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({}) })
        );
        await page
            .getByRole('button', { name: /Terima jawaban dari/i })
            .first()
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await page
            .getByRole('button', { name: /Tolak jawaban dari/i })
            .first()
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await page
            .getByRole('button', { name: /Lihat detail jawaban dari/i })
            .first()
            .click();
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');

        await page.getByText('Contoh jawaban 1').click();
        const detailDrawer = page.getByRole('dialog');
        await expect(detailDrawer).toBeVisible();
        const openFileLink = detailDrawer.getByRole('link', { name: 'Buka' }).first();
        const downloadFileLink = detailDrawer.getByRole('link', { name: 'Unduh' }).first();
        await expect(openFileLink).toHaveAttribute('href', /storage/);
        await expect(downloadFileLink).toHaveAttribute('download');
        const actionGroup = openFileLink.locator('..');
        await expect(actionGroup.getByRole('link')).toHaveCount(2);
        expect((await actionGroup.getByRole('link').allTextContents()).map((label) => label.trim())).toEqual([
            'Buka',
            'Unduh',
        ]);
        await expect(actionGroup).toHaveCSS('justify-content', 'flex-end');
        const actionGroupMetrics = await actionGroup.evaluate((element) => ({
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
        }));
        expect(actionGroupMetrics.scrollWidth).toBeLessThanOrEqual(actionGroupMetrics.clientWidth);
        await page.screenshot({ path: '/tmp/submissions-redesign-file-actions.png', fullPage: true });
        await page.keyboard.press('Escape');

        const firstCard = page.getByTestId('form-answer-mobile-card').first();
        await firstCard.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');

        await page.getByRole('button', { name: 'Daftar' }).click();
        const listRow = page.getByRole('listitem', { name: /Lihat detail jawaban nomor 1/ });
        const listCheckbox = listRow.getByRole('checkbox');
        await listCheckbox.click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await listRow.click({ position: { x: 180, y: 32 } });
        await expect(page.getByRole('dialog')).toBeVisible();

        const viewport = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(viewport.scrollWidth).toBeLessThanOrEqual(viewport.clientWidth);
        await page.screenshot({ path: '/tmp/submissions-redesign-mobile.png', fullPage: true });
    });
});

test.describe('jawaban desktop', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test('tabel tetap tampil dan tidak ada error console', async ({ page }) => {
        const errors: string[] = [];
        await page.route('**/_boost/browser-logs', (route) => route.fulfill({ status: 204, body: '' }));
        await page.route('https://seeyu.doscom.org/script.js', (route) =>
            route.fulfill({ contentType: 'application/javascript', body: '' })
        );
        await page.route('**/storage/events/banners/**', (route) =>
            route.fulfill({
                contentType: 'image/png',
                body: Buffer.from(PLACEHOLDER_PNG),
            })
        );
        page.on('console', (message) => {
            if (message.type() === 'error') errors.push(`${message.text()} ${message.location().url}`);
        });
        const fixture = createFixture();
        await signIn(page);
        await page.goto(fixture.url);
        await expect(page.locator('main table')).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'No.' })).toBeVisible();
        await expect(page.getByRole('group', { name: 'Tampilan daftar jawaban' })).toHaveCount(0);
        await expect(
            page.locator('[data-testid="form-answer-mobile-card"], [data-testid="form-answer-mobile-list"]')
        ).toHaveCount(0);
        await page.screenshot({ path: '/tmp/task-212-desktop-table.png', fullPage: true });
        expect(errors).toEqual([]);
    });

    test('pilihan halaman, klik baris, kontrol aksi, label aksesibel, dan tanpa chip', async ({ page }) => {
        const fixture = createFixture();
        await signIn(page);
        await page.goto(fixture.url);

        const selectPage = page.getByRole('checkbox', { name: 'Pilih semua jawaban di halaman ini' });
        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'true');

        const rowCheckbox = page.getByRole('checkbox', { name: /Pilih jawaban nomor 1/ });
        await rowCheckbox.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'mixed');
        await expect(page.getByRole('dialog')).toHaveCount(0);

        const actionButtons = page.getByRole('button', { name: /(?:Terima|Tolak|Lihat detail) jawaban dari/i });
        await expect(actionButtons).toHaveCount(5);
        for (const actionButton of await actionButtons.all()) {
            await expect(actionButton).toHaveAttribute('data-size', 'icon-sm');
            await expect(actionButton).not.toHaveClass(/rounded-full|rounded-lg|rounded-xl/);
        }

        const acceptButton = page.getByRole('button', { name: /Terima jawaban dari/i });
        await expect(acceptButton).not.toHaveAttribute('title');
        await acceptButton.hover();
        await expect(page.getByRole('tooltip')).toHaveCount(0);
        await expect(page.getByText(/^Terima jawaban dari/)).toHaveCount(0);

        await page.route('**/submissions/*/review', (route) =>
            route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({}) })
        );
        await page
            .getByRole('button', { name: /Terima jawaban dari/i })
            .first()
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await page
            .getByRole('button', { name: /Tolak jawaban dari/i })
            .first()
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await page
            .getByRole('button', { name: /Lihat detail jawaban dari/i })
            .first()
            .click();
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');

        await page.getByText('Contoh jawaban 2').click();
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');

        const answerRow = page.getByRole('row').filter({ hasText: 'Contoh jawaban 2' });
        await answerRow.focus();
        await page.keyboard.press('Space');
        await expect(page.getByRole('dialog')).toBeVisible();

        const viewport = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(viewport.scrollWidth).toBeLessThanOrEqual(viewport.clientWidth);
        await page.screenshot({ path: '/tmp/submissions-redesign-desktop.png', fullPage: true });
    });

    test('pengaturan form menjadi tab tingkat atas', async ({ page }) => {
        const fixture = createFixture(false);
        await signIn(page);
        await page.goto(fixture.url.replace('?tab=jawaban', '?tab=settings'));

        await expect(page.getByRole('tab', { name: /pengaturan form/i })).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByLabel('Tujuan form')).toBeVisible();
        await expect(page.getByLabel('Registration mode')).toBeVisible();
    });
});

test.describe('editor layout', () => {
    test.use({ viewport: { width: 777, height: 900 } });

    test('shell tidak ikut scroll dan parent kanvas menjadi scroll container', async ({ page }) => {
        const fixture = createFixture(false);
        await signIn(page);
        await page.goto(fixture.url.replace('?tab=jawaban', '?tab=editor'));

        await expect(page.getByTestId('form-builder-workspace')).toBeVisible();
        await expect(page.getByText(/Lebar pratinjau mengikuti/i)).toHaveCount(0);
        await expect(page.getByTestId('form-builder-canvas-scroll')).toHaveCSS('overflow-y', 'auto');
        await expect(page.getByTestId('form-builder-fields-scroll')).toHaveCSS('overflow-y', 'visible');
        await expect(page.getByTestId('dashboard-content')).toHaveCSS('padding-bottom', '8px');

        const paneBackgrounds = await page.evaluate(() => {
            const canvas = document.querySelector('[data-testid="form-builder-canvas-scroll"]');
            const palette = document.querySelector('[aria-label="Component palette"]');
            return {
                canvas: canvas ? getComputedStyle(canvas).backgroundColor : '',
                palette: palette ? getComputedStyle(palette).backgroundColor : '',
            };
        });

        expect(paneBackgrounds.palette).toBe(paneBackgrounds.canvas);

        const shellMetrics = await page.getByTestId('dashboard-content').evaluate((element) => ({
            clientHeight: element.clientHeight,
            scrollHeight: element.scrollHeight,
        }));

        expect(shellMetrics.scrollHeight).toBeLessThanOrEqual(shellMetrics.clientHeight + 1);
    });

    test('section form dapat ditambah dan dinavigasi tanpa batas lima field', async ({ page }) => {
        const fixture = createFixture(false);
        await signIn(page);
        await page.goto(fixture.url.replace('?tab=jawaban', '?tab=editor'));

        const canvas = page.getByTestId('form-builder-canvas-scroll');
        await canvas.evaluate((element) => element.scrollTo({ top: element.scrollHeight, behavior: 'auto' }));

        const addSectionButton = page.getByRole('button', { name: /tambah bagian setelah ini/i });
        await expect(addSectionButton).toBeVisible();
        await expect(page.getByRole('button', { name: 'Tambah bagian', exact: true })).toHaveCount(0);
        await addSectionButton.click();

        await expect(page.getByLabel('Bagian 2 dari 2')).toBeVisible();
        await expect(page.getByText(/bagian ini belum memiliki field/i)).toBeVisible();

        await page.getByRole('button', { name: /sebelumnya/i }).click();
        await expect(page.getByLabel('Bagian 1 dari 2')).toBeVisible();
    });
});
