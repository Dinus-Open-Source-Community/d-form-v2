import { expect, test, type Page } from '@playwright/test'
import { runArtisanCommand, runArtisan } from './support/artisan'

const ADMIN = { email: 'stress-user-1@stress.test', password: 'password' } as const
// Spec ini login sendiri, jadi konteks harus guest; storageState admin membuat /auth/login ter-redirect.
test.use({ storageState: { cookies: [], origins: [] } })

const STORAGE_KEY = 'd-form:form-answers:mobile-view'
const PLACEHOLDER_PNG = Uint8Array.from(
    atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2ioAAAAASUVORK5CYII='),
    (character: string) => character.charCodeAt(0),
)

/** Create a small isolated form fixture and return its dashboard URL. */
function createFixture(withAnswers: boolean = true): { url: string; token: string } {
    const token = `e2e-mobile-${Date.now()}`
    fixtureTokens.push(token)
    const command =
        `$owner=App\\Models\\User::where('email','${ADMIN.email}')->firstOrFail();` +
        `$event=App\\Models\\Event::where('created_by',$owner->id)->firstOrFail();` +
        `$form=$event->forms()->create(['title'=>'${token}','description'=>'Fixture E2E','visible_for'=>['public'],'closed_at'=>now()->addDay()]);` +
        `foreach([['Nama','nama','input'],['Lampiran','lampiran','fileUpload']] as $i=>$field){$form->formFields()->create(['label'=>$field[0],'name'=>$field[1],'input_type'=>$field[2],'metadata'=>[],'order'=>$i]);}` +
        (withAnswers ? `foreach(['pending','accepted','rejected'] as $i=>$status){$user=App\\Models\\User::factory()->create(['name'=>'${token} '.($i+1)]);App\\Models\\FormAnswer::create(['form_id'=>$form->id,'user_id'=>$user->id,'answers'=>['nama'=>'Contoh jawaban '.($i+1),'lampiran'=>$i===0?'uploads/e2e/sample.pdf':''],'review_status'=>$status]);}` : '') +
        `echo(route('dashboard.events.forms.show',['event'=>$event,'form'=>$form],false).'?tab=jawaban');`

    return { url: runArtisanCommand(['tinker', '--execute', command]), token }
}

const fixtureTokens: string[] = []

test.afterEach(() => {
    while (fixtureTokens.length > 0) {
        const token = fixtureTokens.pop()
        if (token) {
            runArtisan(`$forms=App\\Models\\Form::where('title','${token}')->get(); foreach($forms as $form){$users=$form->answers()->pluck('user_id');$form->answers()->delete();$form->formFields()->delete();$form->delete();App\\Models\\User::whereIn('id',$users)->delete();}`)
        }
    }
})

/** Sign in as the seeded dashboard administrator. */
async function signIn(page: Page): Promise<void> {
    await page.goto('/auth/login')
    await page.locator('#login-email').fill(ADMIN.email)
    await page.locator('#login-password').fill(ADMIN.password)
    await page.locator('form').filter({ has: page.locator('#login-email') }).getByRole('button', { name: /masuk/i }).click()
    await expect(page).not.toHaveURL(/\/auth\/login/)
}

test.describe('jawaban mobile', () => {
    test.use({ viewport: { width: 375, height: 812 } })

    test('kartu, daftar, pilihan tersimpan, nilai rusak, dan keadaan kosong', async ({ page }) => {
        const fixture = createFixture()
        await signIn(page)
        await page.goto(fixture.url)

        await expect(page.locator('main table')).toHaveCount(0)
        const toggle = page.getByRole('group', { name: 'Tampilan daftar jawaban' })
        const card = toggle.getByRole('button', { name: 'Kartu' })
        const list = toggle.getByRole('button', { name: 'Daftar' })
        await expect(card).toHaveAttribute('aria-pressed', 'true')
        await expect(page.getByText('Contoh jawaban 1')).toBeVisible()
        await page.screenshot({ path: '/tmp/task-212-mobile-card.png', fullPage: true })

        await list.click()
        await expect(list).toHaveAttribute('aria-pressed', 'true')
        await expect(page.getByRole('button', { name: /Lihat detail jawaban dari e2e-mobile-/ })).toHaveCount(3)
        await page.screenshot({ path: '/tmp/task-212-mobile-list.png', fullPage: true })
        await page.reload()
        await expect(list).toHaveAttribute('aria-pressed', 'true')
        expect(await page.evaluate((key) => localStorage.getItem(key), STORAGE_KEY)).toBe('list')

        await page.addInitScript((key) => localStorage.setItem(key, 'grid'), STORAGE_KEY)
        await page.reload()
        await expect(card).toHaveAttribute('aria-pressed', 'true')
        await expect(page.locator('main table')).toHaveCount(0)

        const emptyFixture = createFixture(false)
        await page.goto(emptyFixture.url)
        await expect(page.getByText('Belum ada jawaban')).toBeVisible()
        await expect(page.getByRole('group', { name: 'Tampilan daftar jawaban' })).toHaveCount(0)
        await page.screenshot({ path: '/tmp/task-212-mobile-empty.png', fullPage: true })
    })
})

test.describe('jawaban desktop', () => {
    test.use({ viewport: { width: 1280, height: 900 } })

    test('tabel tetap tampil dan tidak ada error console', async ({ page }) => {
        const errors: string[] = []
        await page.route('https://seeyu.doscom.org/script.js', (route) => route.fulfill({ contentType: 'application/javascript', body: '' }))
        await page.route('**/storage/events/banners/placeholder.jpg', (route) =>
            route.fulfill({
                contentType: 'image/png',
                body: Buffer.from(PLACEHOLDER_PNG),
            }),
        )
        page.on('console', (message) => {
            if (message.type() === 'error') errors.push(`${message.text()} ${message.location().url}`)
        })
        const fixture = createFixture()
        await signIn(page)
        await page.goto(fixture.url)
        await expect(page.locator('main table')).toBeVisible()
        await expect(page.getByRole('group', { name: 'Tampilan daftar jawaban' })).toHaveCount(0)
        await expect(page.locator('[data-testid="form-answer-mobile-card"], [data-testid="form-answer-mobile-list"]')).toHaveCount(0)
        await page.screenshot({ path: '/tmp/task-212-desktop-table.png', fullPage: true })
        expect(errors).toEqual([])
    })

    test('pengaturan form menjadi tab tingkat atas', async ({ page }) => {
        const fixture = createFixture(false)
        await signIn(page)
        await page.goto(fixture.url.replace('?tab=jawaban', '?tab=settings'))

        await expect(page.getByRole('tab', { name: /pengaturan form/i })).toHaveAttribute('aria-selected', 'true')
        await expect(page.getByLabel('Tujuan form')).toBeVisible()
        await expect(page.getByLabel('Registration mode')).toBeVisible()
    })
})
