import { expect, test, type Page } from '@playwright/test';
import { runArtisanCommand, runArtisan } from './support/artisan';

const PLACEHOLDER_PNG = Uint8Array.from(
    atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2ioAAAAASUVORK5CYII='),
    (character: string) => character.charCodeAt(0)
);

/** Create a small isolated form fixture and return its dashboard URL. */
function createFixture(withAnswers: boolean = true): { url: string; token: string } {
    const token = `e2e-mobile-${Date.now()}`;
    fixtureTokens.push(token);
    const command =
        `$event=App\\Models\\Event::where('status','published')->whereNull('deleted_at')->orderByDesc('created_at')->firstOrFail();` +
        `$form=$event->forms()->create(['title'=>'${token}','description'=>'Fixture E2E','visible_for'=>['public'],'closed_at'=>now()->addDay()]);` +
        `foreach([['Nama','nama','input'],['Lampiran','lampiran','fileUpload']] as $i=>$field){$form->formFields()->create(['label'=>$field[0],'name'=>$field[1],'input_type'=>$field[2],'metadata'=>[],'order'=>$i]);}` +
        `$filePath='form-uploads/'.$form->id.'/sample.pdf';` +
        `Illuminate\\Support\\Facades\\Storage::disk('public')->put($filePath, '%PDF-1.4 E2E');` +
        (withAnswers
            ? `foreach(['pending','accepted','rejected'] as $i=>$status){$user=App\\Models\\User::factory()->create(['name'=>'${token} '.($i+1)]);App\\Models\\FormAnswer::create(['form_id'=>$form->id,'user_id'=>$user->id,'answers'=>['nama'=>'Contoh jawaban '.($i+1),'lampiran'=>$i===0?$filePath:''],'review_status'=>$status]);}`
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

test.describe('jawaban mobile', () => {
    test.use({ viewport: { width: 375, height: 812 } });

    test('daftar jawaban mobile tanpa tabel dan tanpa pemilih tampilan', async ({ page }) => {
        const fixture = createFixture();
        await page.goto(fixture.url);

        // Mobile hanya memakai daftar: tabel desktop tersembunyi oleh CSS.
        await expect(page.locator('main table')).toBeHidden();

        await expect(page.getByTestId('form-answer-mobile-list')).toBeVisible();
        await expect(page.getByRole('listitem', { name: /Lihat detail jawaban nomor/ })).toHaveCount(3);
        await page.screenshot({ path: '/tmp/opencode/answers-shots/mobile-list.png', fullPage: true });

        const emptyFixture = createFixture(false);
        await page.goto(emptyFixture.url);
        await expect(page.getByText('Belum ada jawaban').filter({ visible: true })).toBeVisible();
        await expect(page.getByTestId('form-answer-mobile-list')).toHaveCount(0);
        // Tabel sekarang selalu ada di DOM untuk fallback statis; di mobile hanya disembunyikan.
        await expect(page.locator('main table')).toBeHidden();
        await page.screenshot({ path: '/tmp/opencode/answers-shots/mobile-empty.png', fullPage: true });
    });

    test('pilihan halaman, buka detail lewat baris, review di detail, dan tanpa overflow', async ({ page }) => {
        const fixture = createFixture();
        await page.goto(fixture.url);

        const selectPage = page.getByRole('checkbox', { name: 'Pilih semua jawaban' });
        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'true');

        const firstRowCheckbox = page.getByRole('checkbox', { name: /Pilih jawaban nomor 1/ });
        await firstRowCheckbox.click({ force: true });
        await expect(page.getByRole('dialog')).toHaveCount(0);

        // Baris pertama bisa berstatus final (tidak selectable), jadi keadaan "mixed" tidak
        // dijamin. Yang diuji: pilih-semua bisa dibatalkan dan dinyalakan lagi.
        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'false');
        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'true');

        // Tutup bulk bar sebelum membuka detail agar daftar tidak tertutup panel aksi.
        await page.getByRole('button', { name: 'Batalkan pilihan' }).click({ force: true });

        // Baris pending memuat lampiran contoh dan tombol review; baris final bisa tidak punya keduanya.
        const pendingRow = page
            .getByRole('listitem', { name: /Lihat detail jawaban nomor/ })
            .filter({ hasText: 'Menunggu review' })
            .first();

        await pendingRow.focus();
        await page.keyboard.press('Enter');

        const detailDrawer = page.getByRole('dialog');
        await expect(detailDrawer).toBeVisible();

        const openFileLink = detailDrawer.getByRole('link', { name: 'Buka' }).first();
        const downloadFileLink = detailDrawer.getByRole('link', { name: 'Unduh' }).first();
        await expect(openFileLink).toHaveAttribute('href', /\/form-answers\/.*\/files\/lampiran\/sample\.pdf/);
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

        await expect(detailDrawer.getByRole('button', { name: 'Terima', exact: true })).toBeVisible();
        await expect(detailDrawer.getByRole('button', { name: 'Tolak', exact: true })).toBeVisible();
        await page.keyboard.press('Escape');

        const firstRow = page.getByRole('listitem', { name: /Lihat detail jawaban nomor 1/ });
        // Enter pada baris daftar membuka detail tanpa mouse.
        await firstRow.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeVisible();
        await page.keyboard.press('Escape');

        const viewport = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(viewport.scrollWidth).toBeLessThanOrEqual(viewport.clientWidth);
        await page.screenshot({ path: '/tmp/opencode/answers-shots/mobile-detail.png', fullPage: true });
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
        await page.goto(fixture.url);
        await expect(page.locator('main table')).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Pengirim' })).toBeVisible();
        await expect(page.getByTestId('form-answer-mobile-list')).toBeHidden();
        await page.screenshot({ path: '/tmp/task-212-desktop-table.png', fullPage: true });
        expect(errors).toEqual([]);
    });

    test('pilihan halaman, klik baris, kontrol aksi, label aksesibel, dan tanpa chip', async ({ page }) => {
        const fixture = createFixture();
        await page.goto(fixture.url);

        const selectPage = page.getByRole('checkbox', { name: 'Pilih semua jawaban' });
        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'true');

        const rowCheckbox = page.getByRole('checkbox', { name: /Pilih jawaban nomor 1/ });
        await rowCheckbox.click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'false');
        await selectPage.click();
        await expect(selectPage).toHaveAttribute('aria-checked', 'true');

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
