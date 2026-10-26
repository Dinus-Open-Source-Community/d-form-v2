import { expect, test, type Page } from '@playwright/test'
import { runArtisanCommand } from './support/artisan'
import { loginAsOrganizer, resetLoginThrottle } from './support/session'

interface IFormTarget {
    eventId: string
    formId: string
    title: string
}

const LIVE_PREVIEW = '[data-testid="form-builder-live-preview"]'
const PREVIEW_TRIGGER = '[data-testid="form-builder-preview-trigger"]'
const LEGACY_PREVIEW_MODAL = '[role="dialog"][aria-label="Form respondent preview"]'
const BUILDER_COMPONENT = 'Dashboard/Events/Forms/Show'

// FormField diurutkan PK uuid (deterministik) lalu form-nya diambil: pola `has('formFields')`
// tidak dipakai karena memicu scan 1 juta baris yang timeout di dataset stress.
const FORM_LOOKUP = [
    '$row = \\App\\Models\\FormField::query()->orderBy("id")->limit(1)->first(["form_id"]);',
    '$form = $row ? \\App\\Models\\Form::query()->whereKey($row->form_id)->first(["id", "title", "event_id"]) : null;',
    'echo $form ? $form->id."|".$form->event_id."|".$form->title : "";',
].join(' ')

/** URL halaman builder dari pasangan event/form. */
function builderPath({ eventId, formId }: IFormTarget): string {
    return `/admin/events/${eventId}/forms/${formId}`
}

/** Ambil satu form berisi field dari DB nyata; galat artisan/DB dibiarkan gagal, bukan jadi skip. */
function lookupFormTarget(): IFormTarget | null {
    const lines = runArtisanCommand(['tinker', '--execute', FORM_LOOKUP]).split('\n')
    const payload = lines.reverse().find((line) => line.split('|').length >= 3)

    if (payload === undefined) {
        return null
    }

    const parts = payload.split('|')
    const formId = parts[0] ?? ''
    const eventId = parts[1] ?? ''

    if (formId === '' || eventId === '') {
        return null
    }

    return { formId, eventId, title: parts.slice(2).join('|') }
}

/** Baca override env E2E_BUILDER_*; terisi sebagian dianggap salah konfigurasi, bukan alasan skip. */
function readEnvTarget(): IFormTarget | null {
    const formId = process.env.E2E_BUILDER_FORM_ID
    const eventId = process.env.E2E_BUILDER_EVENT_ID
    const title = process.env.E2E_BUILDER_FORM_TITLE

    if (formId === undefined && eventId === undefined && title === undefined) {
        return null
    }

    if (formId === undefined || eventId === undefined || title === undefined) {
        throw new Error(
            'E2E_BUILDER_FORM_ID, E2E_BUILDER_EVENT_ID, dan E2E_BUILDER_FORM_TITLE harus diisi bersamaan.',
        )
    }

    return { formId, eventId, title }
}

/** Target form untuk spec ini: env override bila ada, selain itu query DB. */
function resolveFormTarget(): IFormTarget | null {
    return readEnvTarget() ?? lookupFormTarget()
}

/** Buka builder lalu pastikan 200, komponen Inertia benar, dan Vue sudah ter-mount. */
async function openBuilder(page: Page, target: IFormTarget): Promise<void> {
    await loginAsOrganizer(page)

    const response = await page.goto(builderPath(target))
    expect(response?.status(), 'halaman builder tidak menjawab 200').toBe(200)

    const payload = await page.locator('script[data-page="app"]').textContent()
    const component = payload?.match(/"component":"([^"]+)"/)?.[1]?.replace(/\\\//g, '/')

    expect(component, 'builder tidak merender komponen Show').toBe(BUILDER_COMPONENT)
    await expect
        .poll(() => page.locator('#app > *').count(), { message: 'builder belum ter-hydrate' })
        .toBeGreaterThan(0)
}

test.describe('form builder live preview', () => {
    let target: IFormTarget | null = null

    test.beforeAll(() => {
        target = resolveFormTarget()
    })

    test.beforeEach(() => {
        resetLoginThrottle()
    })

    test('desktop 1600px menampilkan kolom pratinjau dan menyembunyikan tombolnya', async ({ page }) => {
        if (target === null) {
            test.skip(true, 'Dataset uji tidak punya form; isi E2E_BUILDER_FORM_ID/E2E_BUILDER_EVENT_ID/E2E_BUILDER_FORM_TITLE.')
            return
        }

        await page.setViewportSize({ width: 1600, height: 900 })
        await openBuilder(page, target)

        const preview = page.locator(LIVE_PREVIEW)
        await expect(preview).toBeVisible()
        await expect(preview.getByText(target.title).first()).toBeVisible()
        await expect(page.locator(PREVIEW_TRIGGER)).toBeHidden()
    })

    test('tablet 1100px tanpa kolom pratinjau dan tombol membuka modal lama', async ({ page }) => {
        if (target === null) {
            test.skip(true, 'Dataset uji tidak punya form; isi E2E_BUILDER_FORM_ID/E2E_BUILDER_EVENT_ID/E2E_BUILDER_FORM_TITLE.')
            return
        }

        await page.setViewportSize({ width: 1100, height: 800 })
        await openBuilder(page, target)

        await expect(page.locator(LIVE_PREVIEW)).toHaveCount(0)

        const trigger = page.locator(PREVIEW_TRIGGER)
        await expect(trigger).toBeVisible()
        await trigger.click()

        await expect(page.locator(LEGACY_PREVIEW_MODAL)).toBeVisible()
        await expect(page.getByRole('button', { name: 'Save All' }).first()).toBeVisible()
    })
})
