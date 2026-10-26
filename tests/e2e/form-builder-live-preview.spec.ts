import { expect, test, type Page } from '@playwright/test'
import { runArtisanCommand } from './support/artisan'
import { loginAsOrganizer, resetLoginThrottle } from './support/session'

interface IFormTarget {
    eventId: string
    formId: string
    title: string
}

interface ILookupPayload {
    id: string
    event_id: string
    title: string
}

interface IBounds {
    x: number
    y: number
    width: number
    height: number
}

const LIVE_PREVIEW = '[data-testid="form-builder-live-preview"]'
const PREVIEW_TRIGGER = '[data-testid="form-builder-preview-trigger"]'
const LEGACY_PREVIEW_MODAL = '[role="dialog"][aria-label="Form respondent preview"]'
const BUILDER_COMPONENT = 'Dashboard/Events/Forms/Show'
const NO_FORM_TOKEN = 'NONE'
const FORM_LOOKUP_BATCH = 20
const SKIP_REASON =
    'Dataset uji tidak punya form dengan event_id + judul; isi E2E_BUILDER_FORM_ID/E2E_BUILDER_EVENT_ID/E2E_BUILDER_FORM_TITLE.'

// `forms.event_id` nullable: satu baris pertama saja bisa berakhir skip hijau, jadi ambil batch kecil
// dan pilih form pertama yang layak. `has('formFields')` tidak dipakai karena memicu scan 1 juta baris.
const FORM_LOOKUP = [
    `$rows = \\App\\Models\\FormField::query()->orderBy("id")->limit(${FORM_LOOKUP_BATCH})->get(["form_id"]);`,
    '$forms = \\App\\Models\\Form::query()->whereIn("id", $rows->pluck("form_id")->unique()->values())->orderBy("id")->get(["id", "event_id", "title"]);',
    '$form = $forms->first(fn ($row) => $row->event_id !== null && trim((string) $row->title) !== "");',
    `echo $form ? json_encode(["id" => (string) $form->id, "event_id" => (string) $form->event_id, "title" => (string) $form->title]) : "${NO_FORM_TOKEN}";`,
].join(' ')

let cachedTarget: IFormTarget | null | undefined = undefined

/** URL halaman builder dari pasangan event/form. */
function builderPath({ eventId, formId }: IFormTarget): string {
    return `/admin/events/${eventId}/forms/${formId}`
}

/** Cek bentuk JSON hasil tinker sebelum dipakai (output eksternal wajib dipersempit). */
function isLookupPayload(value: unknown): value is ILookupPayload {
    if (typeof value !== 'object' || value === null) return false
    if (!('id' in value) || !('event_id' in value) || !('title' in value)) return false

    return typeof value.id === 'string' && typeof value.event_id === 'string' && typeof value.title === 'string'
}

/** Ambil satu form layak dari DB nyata; `null` hanya bila memang tidak ada, galat artisan/JSON dibiarkan gagal. */
function lookupFormTarget(): IFormTarget | null {
    const lines = runArtisanCommand(['tinker', '--execute', FORM_LOOKUP])
        .split('\n')
        .filter((line) => line.trim() !== '')
    const last = lines[lines.length - 1]

    if (last === undefined) {
        throw new Error('tinker tidak mengembalikan output untuk lookup form')
    }

    if (last.trim() === NO_FORM_TOKEN) {
        return null
    }

    const payload: unknown = JSON.parse(last)

    if (!isLookupPayload(payload)) {
        throw new Error(`payload lookup form tidak dikenali: ${last}`)
    }

    if (payload.id === '' || payload.event_id === '') {
        throw new Error(`payload lookup form tidak lengkap: ${last}`)
    }

    return { formId: payload.id, eventId: payload.event_id, title: payload.title }
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

/** Target form untuk spec ini: env override bila ada, selain itu query DB (di-cache per proses). */
function resolveFormTarget(): IFormTarget | null {
    if (cachedTarget === undefined) {
        cachedTarget = readEnvTarget() ?? lookupFormTarget()
    }

    return cachedTarget
}

/** Persempit target yang nullable setelah `test.skip`; galat ini berarti skip tidak jalan. */
function requireFormTarget(target: IFormTarget | null): IFormTarget {
    if (target === null) {
        throw new Error(SKIP_REASON)
    }

    return target
}

/** True bila dua kotak beririsan; dipakai membuktikan tombol tidak tertutup panel dialog. */
function boxesOverlap(first: IBounds, second: IBounds): boolean {
    return (
        first.x < second.x + second.width &&
        second.x < first.x + first.width &&
        first.y < second.y + second.height &&
        second.y < first.y + first.height
    )
}

/** Buka builder lalu pastikan 200, komponen Inertia benar, tab Editor aktif, dan Vue sudah ter-mount. */
async function openBuilder(page: Page, target: IFormTarget): Promise<void> {
    await loginAsOrganizer(page)

    const response = await page.goto(builderPath(target))
    expect(response?.status(), 'halaman builder tidak menjawab 200').toBe(200)

    const payload = await page.locator('script[data-page="app"]').textContent()
    const component = payload?.match(/"component":"([^"]+)"/)?.[1]?.replace(/\\\//g, '/')

    expect(component, 'builder tidak merender komponen Show').toBe(BUILDER_COMPONENT)
    await expect(
        page.getByRole('tab', { name: 'Editor' }),
        'tab Editor tidak aktif sehingga workspace builder tidak ter-render',
    ).toHaveAttribute('data-state', 'active')
    await expect
        .poll(() => page.locator('#app > *').count(), { message: 'builder belum ter-hydrate' })
        .toBeGreaterThan(0)
}

test.describe('form builder live preview di desktop', () => {
    test.use({ viewport: { width: 1600, height: 900 } })

    let target: IFormTarget | null = null

    test.beforeAll(() => {
        target = resolveFormTarget()
    })

    test.beforeEach(() => {
        resetLoginThrottle()
    })

    test('menampilkan kolom pratinjau live dan menyembunyikan tombol pratinjau', async ({ page }) => {
        test.skip(target === null, SKIP_REASON)
        const formTarget = requireFormTarget(target)

        await openBuilder(page, formTarget)

        const preview = page.locator(LIVE_PREVIEW)
        await expect(preview, 'kolom pratinjau live tidak tampil di 1600px').toBeVisible()
        await expect(preview.getByText(formTarget.title).first(), 'judul form tidak ada di kolom pratinjau').toBeVisible()
        await expect(page.locator(PREVIEW_TRIGGER), 'tombol pratinjau masih dirender padahal kolom live tampil').toHaveCount(
            0,
        )
    })
})

test.describe('form builder live preview di tablet', () => {
    test.use({ viewport: { width: 1100, height: 800 } })

    let target: IFormTarget | null = null

    test.beforeAll(() => {
        target = resolveFormTarget()
    })

    test.beforeEach(() => {
        resetLoginThrottle()
    })

    test('menyembunyikan kolom pratinjau dan tombol membuka modal lama', async ({ page }) => {
        test.skip(target === null, SKIP_REASON)
        const formTarget = requireFormTarget(target)

        await openBuilder(page, formTarget)

        await expect(page.locator(LIVE_PREVIEW), 'kolom pratinjau live ikut tampil di 1100px').toHaveCount(0)

        const trigger = page.locator(PREVIEW_TRIGGER)
        await expect(trigger, 'tombol pratinjau tidak tampil di 1100px').toBeVisible()
        await trigger.click()

        const modal = page.locator(LEGACY_PREVIEW_MODAL)
        await expect(modal, 'klik pratinjau tidak membuka modal lama').toBeVisible()

        const saveAll = page.getByRole('button', { name: 'Save All' }).first()
        await expect(saveAll, 'tombol Save All hilang saat modal pratinjau terbuka').toBeVisible()

        const [modalBox, saveAllBox] = await Promise.all([modal.boundingBox(), saveAll.boundingBox()])

        if (modalBox === null || saveAllBox === null) {
            throw new Error('kotak dialog pratinjau atau tombol Save All tidak terukur')
        }

        expect(boxesOverlap(modalBox, saveAllBox), 'tombol Save All tertutup panel dialog pratinjau').toBe(false)
    })
})
