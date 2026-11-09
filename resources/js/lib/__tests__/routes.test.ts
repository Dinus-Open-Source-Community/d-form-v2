import { describe, expect, it } from 'vitest';
import { routes } from '@/lib/routes';

describe('routes builders', () => {
    it('builds a public event show path from a slug', () => {
        expect(routes.landing.events.show('open-house')).toBe('/events/open-house');
    });

    it('builds password reset and login-with-intended paths', () => {
        expect(routes.auth.resetPassword('tok-1')).toBe('/auth/reset-password/tok-1');
        // URLSearchParams meng-encode spasi sebagai `+` (bukan `%20`); keduanya
        // ekuivalen saat PHP mem-parsing query string.
        expect(routes.auth.registerWithIntended('/events/a b')).toBe('/auth/login?intended=%2Fevents%2Fa+b');
    });

    it('builds a recruitment funnel export with and without a period filter', () => {
        expect(routes.admin.recruitment.reports.exportFunnel()).toBe('/admin/recruitment/reports/export/funnel.csv');
        expect(routes.admin.recruitment.reports.exportFunnel('p-1')).toBe(
            '/admin/recruitment/reports/export/funnel.csv?period_id=p-1'
        );
    });

    it('builds admin event form submission path', () => {
        expect(routes.admin.events.forms.submissions('e-1', 'f-1')).toBe('/admin/events/e-1/forms/f-1/submissions');
    });

    it('builds document download path with preview query', () => {
        expect(routes.admin.recruitment.applications.document('a-1', 'cv', true)).toBe(
            '/admin/recruitment/applications/a-1/documents/cv?preview=1'
        );
    });

    it('builds scan export query params', () => {
        expect(routes.admin.scan.export({ kind: 'event', target: 'e-1', format: 'csv' })).toBe(
            '/admin/scan/export?kind=event&target=e-1&format=csv'
        );
    });

    it('builds member form fill path', () => {
        expect(routes.member.event.formFill('e-1', 'f-1')).toBe('/joined/e-1/forms/f-1/fill');
    });

    it('builds admin index path', () => {
        expect(routes.admin.index).toBe('/admin');
    });

    // Pasangan action yang shape parameternya identik mudah tertukar dan tetap
    // lolos typecheck; snapshot ini menjaga pemetaan key -> route.
    it('keeps action pairs mapped to the correct endpoint', () => {
        const periodId = 'p-1';
        const applicationId = 'a-1';
        const eventId = 'e-1';

        const cases: Array<[string, string, string]> = [
            ['period open', routes.admin.recruitment.periods.open(periodId), `/admin/recruitment/periods/${periodId}/open`],
            ['period close', routes.admin.recruitment.periods.close(periodId), `/admin/recruitment/periods/${periodId}/close`],
            ['period update', routes.admin.recruitment.periods.update(periodId), `/admin/recruitment/periods/${periodId}`],
            ['period destroy', routes.admin.recruitment.periods.destroy(periodId), `/admin/recruitment/periods/${periodId}`],
            [
                'screening pass',
                routes.admin.recruitment.applications.screening.pass(applicationId),
                `/admin/recruitment/applications/${applicationId}/screening/pass`,
            ],
            [
                'screening reject',
                routes.admin.recruitment.applications.screening.reject(applicationId),
                `/admin/recruitment/applications/${applicationId}/screening/reject`,
            ],
            [
                'final accept',
                routes.admin.recruitment.applications.final.accept(applicationId),
                `/admin/recruitment/applications/${applicationId}/final/accept`,
            ],
            [
                'final reject',
                routes.admin.recruitment.applications.final.reject(applicationId),
                `/admin/recruitment/applications/${applicationId}/final/reject`,
            ],
            [
                'application verify',
                routes.admin.recruitment.applications.verify(applicationId),
                `/admin/recruitment/applications/${applicationId}/verify`,
            ],
            [
                'application resend email',
                routes.admin.recruitment.applications.resendEmail(applicationId),
                `/admin/recruitment/applications/${applicationId}/resend-email`,
            ],
            [
                'application resend tracking',
                routes.admin.recruitment.applications.resendTracking(applicationId),
                `/admin/recruitment/applications/${applicationId}/resend-tracking`,
            ],
            ['event show', routes.admin.events.show(eventId), `/admin/events/${eventId}`],
            ['event edit', routes.admin.events.edit(eventId), `/admin/events/${eventId}/edit`],
        ];

        for (const [label, actual, expected] of cases) {
            expect(actual, label).toBe(expected);
        }
    });
});
