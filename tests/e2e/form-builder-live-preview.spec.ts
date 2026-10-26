import { expect, test, type APIResponse, type Page } from '@playwright/test';
import { runArtisanCommand } from './support/artisan';

const ORGANIZER = {
    email: 'e2e-live-preview@example.test',
    password: 'password',
} as const;

interface ILivePreviewFixture {
    eventId: string;
    formId: string;
}

/** Ambil token CSRF dari cookie XSRF-TOKEN milik context halaman (pola sama dengan auth.spec). */
async function readXsrfToken(page: Page): Promise<string> {
    const cookies = await page.context().cookies();
    const cookie = cookies.find((entry) => entry.name === 'XSRF-TOKEN');
    return cookie ? decodeURIComponent(cookie.value) : '';
}

/** Login lewat POST agar halaman /dashboard yang berat tidak pernah dirender. */
async function loginAsOrganizer(page: Page): Promise<APIResponse> {
    await page.goto('/auth/login');

    const token = await readXsrfToken(page);

    return page.context().request.post('/auth/login', {
        form: { email: ORGANIZER.email, password: ORGANIZER.password },
        headers: { 'X-XSRF-TOKEN': token, Accept: 'text/html' },
        maxRedirects: 0,
        failOnStatusCode: false,
    });
}

/** Buat organizer + event + form berisi satu field, lalu kembalikan id untuk URL builder. */
function createLivePreviewFixture(): ILivePreviewFixture {
    const output = runArtisanCommand([
        'tinker',
        '--execute',
        [
            "$u = \\App\\Models\\User::updateOrCreate(['email' => 'e2e-live-preview@example.test'], ['name' => 'E2E Live Preview', 'password' => bcrypt('password'), 'email_verified_at' => now()]);",
            "$u->syncRoles(['super-admin']);",
            "$e = \\App\\Models\\Event::factory()->create(['title' => 'E2E Pratinjau Live', 'created_by' => $u->id]);",
            "$f = \\App\\Models\\Form::factory()->create(['event_id' => $e->id, 'title' => 'Formulir Pratinjau Live', 'description' => 'Deskripsi formulir uji.']);",
            "\\App\\Models\\FormField::factory()->create(['form_id' => $f->id, 'label' => 'Nama lengkap', 'input_type' => 'input', 'order' => 1]);",
            "echo $e->id.':'.$f->id;",
        ].join(' '),
    ]);

    const [eventId, formId] = output.split(':');

    return { eventId, formId };
}

test.describe('pratinjau live form builder', () => {
    let fixture: ILivePreviewFixture;

    test.beforeEach(() => {
        fixture = createLivePreviewFixture();
    });

    test('desktop lebar menampilkan kolom pratinjau dan menyembunyikan tombol Pratinjau', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        expect((await loginAsOrganizer(page)).status()).toBe(302);

        await page.goto(`/admin/events/${fixture.eventId}/forms/${fixture.formId}`, {
            waitUntil: 'domcontentloaded',
        });

        const livePreview = page.locator('[data-testid="form-builder-live-preview"]');
        await expect(livePreview).toBeVisible();
        await expect(livePreview).toContainText('Formulir Pratinjau Live');
        await expect(livePreview).toContainText('Nama lengkap');
        await expect(page.locator('[data-testid="form-builder-preview-trigger"]')).toHaveCount(0);

        const judulBaru = `Judul pratinjau ${Date.now()}`;
        await page.locator('#f-title').fill(judulBaru);
        await expect(livePreview).toContainText(judulBaru);
    });

    test('layar tab menyembunyikan kolom live dan tombol Pratinjau membuka modal', async ({ page }) => {
        await page.setViewportSize({ width: 1100, height: 800 });
        expect((await loginAsOrganizer(page)).status()).toBe(302);

        await page.goto(`/admin/events/${fixture.eventId}/forms/${fixture.formId}`, {
            waitUntil: 'domcontentloaded',
        });

        await expect(page.locator('[data-testid="form-builder-live-preview"]')).toHaveCount(0);

        const trigger = page.locator('[data-testid="form-builder-preview-trigger"]');
        await expect(trigger).toBeVisible();

        await trigger.click();
        await expect(page.getByRole('dialog', { name: 'Form respondent preview' })).toBeVisible();
        await expect(page.getByRole('dialog')).toContainText('Formulir Pratinjau Live');
    });
});
