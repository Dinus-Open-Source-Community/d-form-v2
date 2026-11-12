import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import type { Page, TestInfo } from '@playwright/test';

type TReviewStatus = 'pending' | 'accepted';

interface IBulkReviewRequestPayload {
    submission_ids: string[];
    review_status: 'accepted' | 'rejected';
}

interface IBrowserDiagnostics {
    consoleErrors: string[];
    pageErrors: string[];
    failedRequests: string[];
    badResponses: string[];
}

test.use({ fixtureFormCount: 1, fixtureFieldCount: 1, fixtureSubmissionCount: 1 });

/** Set the fixture form to the single-registration mode required by bulk review. */
function enableSingleRegistrationMode(formId: string): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$form=App\\Models\\Form::findOrFail('${formId}');$form->metadata=['registration_mode'=>'single'];$form->save();`,
    ]);
}

/** Add a marker-owned guest answer so the fixture can exercise two explicit IDs. */
function createGuestAnswer(formId: string, status: TReviewStatus): string {
    const reviewedAt = status === 'accepted' ? 'now()' : 'null';
    const answers = JSON.stringify({ field_1: 'Guest fixture answer' });
    const command =
        `$id=(string)Illuminate\\Support\\Str::orderedUuid();` +
        `$now=now();` +
        `Illuminate\\Support\\Facades\\DB::table('form_answers')->insert(` +
        `['id'=>$id,'answers'=>'${answers}','user_id'=>null,'form_id'=>'${formId}','is_active'=>1,` +
        `'review_status'=>'${status}','reviewed_at'=>${reviewedAt},'reviewed_by'=>null,` +
        `'created_at'=>$now,'updated_at'=>$now]);echo $id;`;

    return runArtisanCommand(['tinker', '--execute', command]);
}

/** Install browser diagnostics and silence known development-only telemetry requests. */
function installBrowserDiagnostics(page: Page): IBrowserDiagnostics {
    const diagnostics: IBrowserDiagnostics = {
        consoleErrors: [],
        pageErrors: [],
        failedRequests: [],
        badResponses: [],
    };

    void page.route('**/_boost/browser-logs', (route) => route.fulfill({ status: 204, body: '' }));
    void page.route('https://seeyu.doscom.org/script.js', (route) =>
        route.fulfill({ contentType: 'application/javascript', body: '' })
    );

    page.on('console', (message) => {
        if (message.type() === 'error') diagnostics.consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
    page.on('requestfailed', (request) => {
        diagnostics.failedRequests.push(`${request.method()} ${request.url()}: ${request.failure()?.errorText ?? 'unknown'}`);
    });
    page.on('response', (response) => {
        if (response.status() >= 400) diagnostics.badResponses.push(`${response.status()} ${response.url()}`);
    });

    return diagnostics;
}

/** Open the answer tab through the same authenticated fixture owner flow used by the harness. */
async function openAnswers(page: Page, eventId: string, formId: string): Promise<void> {
    await page.goto(`/admin/events/${eventId}/forms/${formId}?tab=jawaban`, { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('tab', { name: /jawaban/i })).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('checkbox', { name: 'Pilih semua jawaban di halaman ini' })).toBeVisible();
}

/** Assert responsive overflow and retain a screenshot for the task verification report. */
async function captureResponsiveEvidence(page: Page, testInfo: TestInfo, label: string): Promise<void> {
    const viewport = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));
    expect(viewport.scrollWidth).toBeLessThanOrEqual(viewport.clientWidth);
    await page.screenshot({
        path: `/tmp/task-4-${testInfo.project.name}-${label}.png`,
        fullPage: true,
    });
}

function isBulkReviewRequestPayload(value: unknown): value is IBulkReviewRequestPayload {
    if (typeof value !== 'object' || value === null || !('submission_ids' in value) || !('review_status' in value)) {
        return false;
    }

    const payload = value as { submission_ids?: unknown; review_status?: unknown };

    return (
        Array.isArray(payload.submission_ids) &&
        payload.submission_ids.every((id: unknown): id is string => typeof id === 'string') &&
        (payload.review_status === 'accepted' || payload.review_status === 'rejected')
    );
}

test.beforeEach(async ({ page }): Promise<void> => {
    installBrowserDiagnostics(page);
});

test.afterEach(async ({ page }, testInfo): Promise<void> => {
    const diagnostics = (page as Page & { __task4Diagnostics?: IBrowserDiagnostics }).__task4Diagnostics;

    if (diagnostics !== undefined && testInfo.status === 'passed') {
        expect(diagnostics.consoleErrors, 'console errors').toEqual([]);
        expect(diagnostics.pageErrors, 'page errors').toEqual([]);
        expect(diagnostics.failedRequests, 'failed requests').toEqual([]);
        expect(diagnostics.badResponses, 'HTTP error responses').toEqual([]);
    }
});

test.describe('bulk form answer review', () => {
    test('keeps desktop/mobile selection count, header controls, exact copy, cancel, focus, and zero-selection state', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }, testInfo) => {
        enableSingleRegistrationMode(fixtureForm.primary.formId);
        createGuestAnswer(fixtureForm.primary.formId, 'pending');
        await openAnswers(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        const selectAll = page.getByRole('checkbox', { name: 'Pilih semua jawaban di halaman ini' });
        await selectAll.click();

        await expect(page.getByText('2 dipilih', { exact: true })).toBeVisible();
        const acceptButton = page.getByRole('button', { name: 'Terima', exact: true });
        const rejectButton = page.getByRole('button', { name: 'Tolak', exact: true });
        await expect(acceptButton).toBeVisible();
        await expect(rejectButton).toBeVisible();

        await acceptButton.click();
        const dialog = page.getByRole('dialog');
        await expect(dialog.getByRole('heading', { name: 'Terima 2 jawaban terpilih?' })).toBeVisible();
        await expect(dialog.getByText('Hanya jawaban yang dipilih yang akan diproses.')).toBeVisible();
        await expect(dialog.getByRole('button', { name: 'Batal', exact: true })).toBeVisible();
        await expect(dialog.getByRole('button', { name: 'Terima jawaban', exact: true })).toBeVisible();

        await dialog.getByRole('button', { name: 'Batal', exact: true }).focus();
        await expect(dialog.getByRole('button', { name: 'Batal', exact: true })).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(page.getByText('2 dipilih', { exact: true })).toBeVisible();

        await rejectButton.click();
        await expect(page.getByRole('heading', { name: 'Tolak 2 jawaban terpilih?' })).toBeVisible();
        await page.getByRole('dialog').getByRole('button', { name: 'Batal', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(page.getByText('2 dipilih', { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Batalkan pilihan', exact: true }).click();
        await expect(page.getByText('2 dipilih', { exact: true })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Terima', exact: true })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Tolak', exact: true })).toHaveCount(0);
        await expect(selectAll).toHaveAttribute('aria-checked', 'false');
        await captureResponsiveEvidence(page, testInfo, 'selection-cancel');
    });

    test('sends only explicit selected IDs, accepts successfully, and reloads the answer props', async ({
        page,
        fixtureEvent,
        fixtureForm,
        fixtureSubmissions,
    }, testInfo) => {
        enableSingleRegistrationMode(fixtureForm.primary.formId);
        const extraId = createGuestAnswer(fixtureForm.primary.formId, 'pending');
        const expectedIds = [...fixtureSubmissions.submissionIds, extraId].sort();
        let payload: IBulkReviewRequestPayload | null = null;
        let partialReloads = 0;
        page.on('request', (request) => {
            if (request.method() === 'PATCH' && request.url().includes('/submissions/bulk-review')) {
                const body: unknown = JSON.parse(request.postData() ?? '{}');
                if (isBulkReviewRequestPayload(body)) payload = body;
            }
            if (request.method() === 'GET' && request.headers()['x-inertia'] === 'true' && request.url().includes(fixtureForm.primary.formId)) {
                partialReloads += 1;
            }
        });

        await openAnswers(page, fixtureEvent.eventId, fixtureForm.primary.formId);
        const rowCheckboxes = page.getByRole('checkbox', { name: /Pilih jawaban nomor/ });
        await rowCheckboxes.nth(0).click();
        await rowCheckboxes.nth(1).click();
        await expect(page.getByText('2 dipilih', { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Terima', exact: true }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Terima jawaban', exact: true }).click();

        await expect(page.getByText('2 jawaban diterima.')).toBeVisible();
        await expect.poll(() => payload).toEqual({ submission_ids: expectedIds, review_status: 'accepted' });
        await expect.poll(() => partialReloads).toBeGreaterThan(0);
        await expect(page.getByText('Diterima', { exact: true })).toHaveCount(2);
        await expect(page.getByText('2 dipilih', { exact: true })).toHaveCount(0);
        await captureResponsiveEvidence(page, testInfo, 'bulk-accept-reload');
    });

    test('blocks a mixed ineligible selection without mutating either row', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }, testInfo) => {
        enableSingleRegistrationMode(fixtureForm.primary.formId);
        createGuestAnswer(fixtureForm.primary.formId, 'accepted');
        await openAnswers(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        await page.getByRole('checkbox', { name: 'Pilih semua jawaban di halaman ini' }).click();
        await expect(page.getByText('2 dipilih', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Terima', exact: true })).toBeDisabled();
        await expect(page.getByRole('button', { name: 'Tolak', exact: true })).toBeDisabled();
        await expect(page.getByText(/jawaban terpilih tidak bisa diproses massal/i)).toBeVisible();
        await expect(page.getByText('Menunggu review', { exact: true })).toHaveCount(1);
        await expect(page.getByText('Diterima', { exact: true })).toHaveCount(1);
        await captureResponsiveEvidence(page, testInfo, 'mixed-ineligible-rollback');
    });

    test('supports individual accept and reject row actions without a confirmation dialog', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }, testInfo) => {
        enableSingleRegistrationMode(fixtureForm.primary.formId);
        createGuestAnswer(fixtureForm.primary.formId, 'pending');
        await openAnswers(page, fixtureEvent.eventId, fixtureForm.primary.formId);

        await page.getByRole('button', { name: /Terima jawaban dari/i }).first().click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(page.getByText('Jawaban diterima.')).toBeVisible();
        await expect(page.getByText('Diterima', { exact: true })).toHaveCount(1);

        await page.getByRole('button', { name: /Tolak jawaban dari/i }).first().click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(page.getByText('Jawaban ditolak.')).toBeVisible();
        await expect(page.getByText('Ditolak', { exact: true })).toHaveCount(1);
        await captureResponsiveEvidence(page, testInfo, 'individual-actions');
    });
});
