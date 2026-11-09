import type { Page, Request, TestInfo } from '@playwright/test';
import { cleanupOwnerFixture, expect, ownerIdentityForTest, runFixtureCommand, test } from './support/fixtures';
import { readPageProps } from './support/network';
import { runArtisan } from './support/artisan';

const TOO_BROAD_MESSAGE = 'Kata kunci terlalu umum, coba lebih spesifik.';
const SEARCH_INPUT = 'input[placeholder="Cari acara..."]';
/** Waktu tunggu melewati debounce 350 ms plus round-trip Inertia. */
const SEARCH_SETTLE_MS = 1_500;

/**
 * Event milik test ini sendiri: pencarian harus punya hasil tanpa bergantung
 * pada event sisa run lain atau dataset stress. Marker/identity memakai bentuk
 * kanonik `e2e+<runId>+o<12 hex>` yang divalidasi command fixture.
 */
const SEARCH_TERM = '5387';
const SEARCH_TITLE = `E2E event ${SEARCH_TERM}`;

interface IEventPageProps {
    filters: { q: string | null };
}

/** Marker negative-cache lewat API publik service, bukan lewat query berat. */
function markEventTermTooBroad(): void {
    runArtisan(
        '$s = app(\\App\\Services\\Event\\PublicEventSearch::class); ' +
            "\\Illuminate\\Support\\Facades\\Cache::put($s->cacheKey((string) \\App\\Services\\Event\\PublicEventSearch::terms('event'), 1), ['too_broad' => true], 60);"
    );
}

/**
 * Ketik lalu klik Reset dalam satu tick DOM, sehingga klik terjadi jauh di bawah
 * debounce 350 ms — kondisi yang memicu timer pending pada perilaku lama.
 */
async function typeThenResetInOneTick(page: Page, term: string): Promise<void> {
    await page.evaluate((value: string): void => {
        const input = document.querySelector<HTMLInputElement>('input[placeholder="Cari acara..."]');

        if (input === null) throw new Error('search input not found');

        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;

        if (setter === undefined) throw new Error('HTMLInputElement value setter not found');

        setter.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));

        const button = Array.from(document.querySelectorAll('button')).find(
            (candidate: HTMLButtonElement): boolean => (candidate.textContent ?? '').trim() === 'Reset Pencarian'
        );

        if (button === undefined) throw new Error('reset button not found');

        button.click();
    }, term);
}

/**
 * Hitung visit Inertia ke `/events` (header `X-Inertia`), bukan redirect/dokumen/aset.
 * Request duplikat yang dicari tiket ini selalu berupa visit XHR, sehingga hitungan
 * ini tidak terpengaruh reload dokumen atau noise browser.
 */
function startInertiaVisitCount(page: Page): () => number {
    let count = 0;
    const onRequest = (request: Request): void => {
        if (request.method() !== 'GET' || request.headers()['x-inertia'] !== 'true') return;
        if (!/\/events(\?|$)/.test(request.url())) return;

        count += 1;
    };

    page.on('request', onRequest);

    return (): number => count;
}

test.describe('pencarian acara publik', () => {
    test.beforeEach(({}, testInfo: TestInfo) => {
        runArtisan('\\Illuminate\\Support\\Facades\\Cache::flush();');

        const identity = ownerIdentityForTest(testInfo);
        runFixtureCommand({
            marker: identity.marker,
            slug: identity.slug,
            title: SEARCH_TITLE,
            forms: [],
        });
    });

    test.afterEach(({}, testInfo: TestInfo) => {
        cleanupOwnerFixture(ownerIdentityForTest(testInfo));
    });

    test('pesan "terlalu umum" muncul lalu hilang setelah kata kunci diganti', async ({ page }) => {
        markEventTermTooBroad();

        await page.goto('/events?q=event', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText(TOO_BROAD_MESSAGE)).toBeVisible();

        const input = page.locator(SEARCH_INPUT);
        await input.fill('5387');
        await page.waitForURL(/q=5387/);

        await expect(page.getByText(TOO_BROAD_MESSAGE)).toBeHidden();
        await expect(page.locator('#event-list h3').first()).toBeVisible();
    });

    test('reset saat debounce pending hanya mengirim satu request', async ({ page }) => {
        // State "tidak ada hasil" menampilkan tombol reset tanpa bergantung marker cache,
        // sehingga test ini tidak ikut gagal saat cache negatif di-flush lane lain.
        await page.goto('/events?q=zzzz-unknown-term', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('button', { name: 'Reset Pencarian' })).toBeVisible();

        const visitCount = startInertiaVisitCount(page);

        await typeThenResetInOneTick(page, '5387');
        await page.waitForTimeout(SEARCH_SETTLE_MS);

        // Hanya visit reset yang dihitung; request dokumen awal bukan visit XHR.
        expect(visitCount()).toBe(1);
        expect(new URL(page.url()).search).toBe('');
        await expect(page.locator('#event-list h3').first()).toBeVisible();
    });

    test('respons lama tidak menimpa hasil term terbaru', async ({ page }) => {
        const firstTerm = 'zzzzfirst';
        const secondTerm = 'zzzzsecond';

        await page.goto('/events', { waitUntil: 'domcontentloaded' });

        // Request pertama sengaja diperlambat agar selesai setelah request kedua dikirim.
        await page.route('**/events*', async (route): Promise<void> => {
            const url = new URL(route.request().url());

            if (url.searchParams.get('q') === firstTerm) {
                await new Promise<void>((done: () => void): void => {
                    setTimeout(done, 900);
                });
            }

            try {
                await route.continue();
            } catch {
                // Request pertama sudah di-abort Inertia saat request kedua dikirim.
            }
        });

        const input = page.locator(SEARCH_INPUT);

        await input.fill(firstTerm);
        await page.waitForRequest((request): boolean => {
            return new URL(request.url()).searchParams.get('q') === firstTerm;
        });
        await input.fill(secondTerm);

        await page.waitForURL(new RegExp(`q=${secondTerm}`));
        // Beri kesempatan respons pertama (delayed 900 ms) mendarat bila tidak di-abort.
        await page.waitForTimeout(1_200);

        // URL + DOM membuktikan respons lama tidak menimpa hasil term terakhir.
        expect(new URL(page.url()).searchParams.get('q')).toBe(secondTerm);
        await expect(page.locator('#event-list').getByText(secondTerm, { exact: true })).toBeVisible();
        await expect(page.locator('#event-list').getByText(firstTerm, { exact: true })).toHaveCount(0);

        // Payload `script[data-page]` tidak diperbarui oleh partial visit, jadi reload dulu
        // agar props yang dibaca adalah state server dari URL final.
        await page.reload({ waitUntil: 'domcontentloaded' });

        const props = await readPageProps<IEventPageProps>(page);
        expect(props.filters.q).toBe(secondTerm);
    });

    test('anomali input: dua karakter, spasi, dan tag script', async ({ page }) => {
        await page.goto('/events?q=5387', { waitUntil: 'domcontentloaded' });

        const visitCount = startInertiaVisitCount(page);
        const input = page.locator(SEARCH_INPUT);

        await input.fill('ab');
        await page.waitForTimeout(SEARCH_SETTLE_MS);
        expect(visitCount()).toBe(0);

        await input.fill('   ');
        await page.waitForTimeout(SEARCH_SETTLE_MS);
        expect(visitCount()).toBe(1);
        expect(new URL(page.url()).search).toBe('');

        let dialogCount = 0;
        page.on('dialog', async (dialog): Promise<void> => {
            dialogCount += 1;
            await dialog.dismiss();
        });

        await input.fill('<script>alert(1)</script>');
        await page.waitForTimeout(SEARCH_SETTLE_MS);

        expect(visitCount()).toBe(2);
        expect(dialogCount).toBe(0);
        await expect(page.locator(SEARCH_INPUT)).toBeVisible();
    });

    test('429 tampil sebagai pesan ramah, bukan modal Inertia', async ({ page }) => {
        // Jangan menghabiskan rate limiter `event-search` yang nyata: kuotanya per menit
        // tetap terpakai di server, sehingga spec berikutnya yang mengharapkan `/events`
        // 200 ikut kena 429 (polusi lintas spec). 429 disimulasikan di level jaringan;
        // yang diuji adalah perilaku UI saat limiter menolak.
        await page.route('**/events?*', async (route): Promise<void> => {
            const request = route.request();
            const url = new URL(request.url());

            if (request.method() === 'GET' && url.searchParams.has('q')) {
                await route.fulfill({
                    status: 429,
                    headers: {
                        'Content-Type': 'application/json',
                        'Retry-After': '60',
                        'X-RateLimit-Limit': '30',
                        'X-RateLimit-Remaining': '0',
                    },
                    body: JSON.stringify({ message: 'Too Many Attempts.' }),
                });

                return;
            }

            await route.continue();
        });

        await page.goto('/events', { waitUntil: 'domcontentloaded' });
        await page.locator(SEARCH_INPUT).fill('5387');

        // Pesan dari aplikasi (bukan halaman error Laravel), shell tetap ada, tanpa modal Inertia.
        await expect(page.getByText(/Terlalu banyak pencarian/)).toBeVisible();
        await expect(page.locator(SEARCH_INPUT)).toBeVisible();
        await expect(page.locator('#inertia-error-dialog')).toHaveCount(0);
    });
});
