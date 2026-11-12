import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { fetchApplicantDetail } from '@/adapters/http/recruitment';
import { startJsonServer } from '@/adapters/http/__tests__/support/jsonServer';
import type { IJsonServerHandle, IJsonServerResponse } from '@/adapters/http/__tests__/support/jsonServer';

/** Payload detail applicant minimal yang lolos guard. */
function validApplication(): Record<string, unknown> {
    return {
        id: 'app-1',
        registration_number: 'OPREC-1',
        full_name: 'Budi',
        nim: 'A11.2020.1',
        semester: 4,
        phone: '0800',
        personal_email: 'budi@example.test',
        student_email: 'budi@student.test',
        instagram_username: 'budi',
        stage: 'screening',
        stage_label: 'Screening',
        result: 'pending',
        result_label: 'Menunggu',
        is_verified: true,
        revision_required: false,
        submitted_at: null,
        period: null,
        primary_division: null,
        secondary_division: null,
        document: null,
        screenings: [],
        activity_logs: [],
        correction_requests: [],
        evaluation: null,
        evaluations: { primary: null, secondary: null },
        final_decision: null,
        can_screen: true,
        can_verify: true,
        can_decide_final: false,
        can_resend_tracking: false,
    };
}

describe('recruitment adapter', () => {
    let server: IJsonServerHandle;

    beforeAll(async (): Promise<void> => {
        server = await startJsonServer((rawUrl: string): IJsonServerResponse => {
            const path = new URL(rawUrl, 'http://127.0.0.1').pathname;

            if (path === '/valid') {
                return { body: JSON.stringify({ application: validApplication() }) };
            }

            if (path === '/missing-screening') {
                const application = validApplication();
                delete application.screenings;

                return { body: JSON.stringify({ application }) };
            }

            if (path === '/bad-semester') {
                const application = validApplication();
                application.semester = '4';

                return { body: JSON.stringify({ application }) };
            }

            if (path === '/no-application-key') {
                return { body: JSON.stringify({ period: { id: 'p-1' } }) };
            }

            return { status: 404, body: JSON.stringify({ message: 'Tidak ada.' }) };
        });
    });

    afterAll(async (): Promise<void> => {
        await server.close();
    });

    it('returns a validated application detail', async () => {
        const application = await fetchApplicantDetail(`${server.baseUrl}/valid`);

        expect(application.id).toBe('app-1');
        expect(application.screenings).toEqual([]);
    });

    it('rejects a detail without the screening list', async () => {
        await expect(fetchApplicantDetail(`${server.baseUrl}/missing-screening`)).rejects.toMatchObject({
            kind: 'invalid_response',
            status: 200,
        });
    });

    it('rejects a detail with a non-numeric semester', async () => {
        await expect(fetchApplicantDetail(`${server.baseUrl}/bad-semester`)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });

    it('rejects a payload that does not carry the application key', async () => {
        await expect(fetchApplicantDetail(`${server.baseUrl}/no-application-key`)).rejects.toMatchObject({
            kind: 'invalid_response',
        });
    });

    it('maps a missing resource to not_found', async () => {
        await expect(fetchApplicantDetail(`${server.baseUrl}/missing`)).rejects.toMatchObject({
            kind: 'not_found',
            status: 404,
        });
    });
});
