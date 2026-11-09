import type { Page } from '@playwright/test';
import { countFixtureRows, expect, test } from './support/fixtures';
import { readXsrfToken } from './support/network';
import { isTinkerRecord, parseTinkerJsonRecord } from './support/tinker';

interface IFieldOperationBrowserResponse {
    status: number;
    requestId: string;
    validationErrorKeys: string[];
    message: string;
}

/** Body mentah response dari `page.evaluate`; parsing dilakukan di Node karena guard tidak terserialisasi. */
interface IBrowserResponseBody {
    status: number;
    requestId: string;
    bodyText: string;
}

interface IDuplicateFieldRequest {
    page: Page;
    url: string;
    xsrfToken: string;
    conflictingFieldId: string;
}

test.use({ fixtureFormCount: 2, fixtureFieldCount: 1 });

/** Mapping body JSON endpoint field ke bentuk yang diassert; satu implementasi untuk kedua kasus. */
function toFieldOperationResponse(raw: IBrowserResponseBody): IFieldOperationBrowserResponse {
    const body = parseTinkerJsonRecord(raw.bodyText, 'respons field operation');
    const errors = body['errors'];
    const message = body['message'];

    return {
        status: raw.status,
        requestId: raw.requestId,
        validationErrorKeys: isTinkerRecord(errors) ? Object.keys(errors) : [],
        message: typeof message === 'string' ? message : '',
    };
}

/** Send a malformed field snapshot through the browser fetch boundary. */
async function postInvalidFieldSnapshot(
    page: Page,
    url: string,
    xsrfToken: string
): Promise<IFieldOperationBrowserResponse> {
    const raw = await page.evaluate(
        async ({ url: requestUrl, xsrfToken: token }): Promise<IBrowserResponseBody> => {
            const response = await fetch(requestUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    fields: [
                        {
                            type: 'input',
                            name: 'browser_invalid_field',
                            order: 1,
                            metadata: { type: 'text', rules: [] },
                        },
                    ],
                }),
            });

            return {
                status: response.status,
                requestId: response.headers.get('X-Request-ID') ?? '',
                bodyText: await response.text(),
            };
        },
        { url, xsrfToken }
    );

    return toFieldOperationResponse(raw);
}

/** Send a valid-looking snapshot whose ID belongs to another form. */
async function postDuplicateFieldId({
    page,
    url,
    xsrfToken,
    conflictingFieldId,
}: IDuplicateFieldRequest): Promise<IFieldOperationBrowserResponse> {
    const raw = await page.evaluate(
        async ({ url: requestUrl, xsrfToken: token, fieldId }): Promise<IBrowserResponseBody> => {
            const requestId = crypto.randomUUID();
            const response = await fetch(requestUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Request-ID': requestId,
                    'X-XSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    fields: [
                        {
                            id: fieldId,
                            label: 'Browser duplicate ID',
                            type: 'input',
                            name: 'browser_duplicate_id',
                            order: 1,
                            metadata: { type: 'text', rules: [] },
                        },
                    ],
                }),
            });

            return {
                status: response.status,
                requestId: response.headers.get('X-Request-ID') ?? '',
                bodyText: await response.text(),
            };
        },
        { url, xsrfToken, fieldId: conflictingFieldId }
    );

    return toFieldOperationResponse(raw);
}

test.describe('BE-P1-005 field operation exception contract', () => {
    test('browser anomaly invalid payload remains validation 422', async ({ page, fixtureEvent, fixtureForm }) => {
        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}/edit`, {
            waitUntil: 'domcontentloaded',
        });

        const xsrfToken = await readXsrfToken(page.context());
        const beforeCount = countFixtureRows(fixtureEvent.marker).counts.form_fields;
        const response = await postInvalidFieldSnapshot(
            page,
            `/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}/fields`,
            xsrfToken
        );

        expect(response.status).toBe(422);
        expect(response.validationErrorKeys).toContain('fields.0.label');
        expect(response.message).toContain('fields.0.label');
        expect(countFixtureRows(fixtureEvent.marker).counts.form_fields).toBe(beforeCount);
    });

    test('browser anomaly duplicate database ID returns public-safe 500', async ({
        page,
        fixtureEvent,
        fixtureForm,
    }) => {
        const conflictingForm = fixtureForm.forms[1];
        const conflictingFieldId = conflictingForm?.fieldIds[0];

        if (!conflictingForm || !conflictingFieldId) {
            throw new Error('Expected a second fixture form with one field.');
        }

        await page.goto(`/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}/edit`, {
            waitUntil: 'domcontentloaded',
        });

        const response = await postDuplicateFieldId({
            page,
            url: `/admin/events/${fixtureEvent.eventId}/forms/${fixtureForm.primary.formId}/fields`,
            xsrfToken: await readXsrfToken(page.context()),
            conflictingFieldId,
        });

        expect(response.status).toBe(500);
        expect(response.requestId).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
        );
        expect(response.message).toBe('Fields cannot be saved');
    });
});
