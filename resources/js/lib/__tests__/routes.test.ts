import { describe, expect, it } from 'vitest';
import { isSidebarNavActive, pathWithoutQuery, resolveNavbarFallbackBackHref, routes } from '@/lib/routes';

/** Ambil leaf key route tanpa mengevaluasi getter/fungsi (descriptor, bukan nilai). */
function collectRouteKeys(value: object, prefix = ''): string[] {
    return Reflect.ownKeys(value).flatMap((key): string[] => {
        if (typeof key !== 'string') return [];

        const descriptor = Object.getOwnPropertyDescriptor(value, key);
        if (descriptor === undefined) return [];

        const path = prefix === '' ? key : `${prefix}.${key}`;
        const nested: unknown = descriptor.value;

        if (typeof nested === 'object' && nested !== null) {
            return collectRouteKeys(nested, path);
        }

        return [path];
    });
}

/** Kontrak key adapter: penghapusan/penambahan harus lewat perubahan snapshot ini. */
const SURVIVING_ROUTE_KEYS = [
    'home',
    'landing.features',
    'landing.docs',
    'landing.events.index',
    'landing.events.show',
    'recruitment.landing',
    'recruitment.track.login',
    'auth.login',
    'auth.register',
    'auth.passwordResetLink',
    'auth.registerWithIntended',
    'dashboard.index',
    'dashboard.profile',
    'dashboard.profileAvatar',
    'dashboard.profilePassword',
    'admin.index',
    'admin.recruitment.index',
    'admin.recruitment.reports.exportFunnel',
    'admin.recruitment.reports.exportApplicants',
    'admin.recruitment.activityLogs.index',
    'admin.recruitment.periods.create',
    'admin.recruitment.periods.store',
    'admin.recruitment.periods.show',
    'admin.recruitment.periods.update',
    'admin.recruitment.periods.open',
    'admin.recruitment.periods.close',
    'admin.recruitment.periods.destroy',
    'admin.recruitment.periods.sendGroupLink',
    'admin.recruitment.periods.sendQr',
    'admin.recruitment.periods.exportInterviews',
    'admin.recruitment.divisions.update',
    'admin.recruitment.interviewers.store',
    'admin.recruitment.interviewers.assign',
    'admin.recruitment.interviewers.unassign',
    'admin.recruitment.applications.document',
    'admin.recruitment.applications.screening.pass',
    'admin.recruitment.applications.screening.revision',
    'admin.recruitment.applications.screening.reject',
    'admin.recruitment.applications.verify',
    'admin.recruitment.applications.resendEmail',
    'admin.recruitment.applications.resendTracking',
    'admin.recruitment.applications.final.accept',
    'admin.recruitment.applications.final.reject',
    'admin.recruitment.corrections.approve',
    'admin.recruitment.corrections.reject',
    'admin.recruitment.interviewSessions.store',
    'admin.recruitment.interviewSessions.show',
    'admin.recruitment.interviewSessions.update',
    'admin.recruitment.interviewSessions.destroy',
    'admin.recruitment.myInterviews.index',
    'admin.recruitment.myInterviews.show',
    'admin.recruitment.myInterviews.secondaryClaim',
    'admin.recruitment.myInterviews.primaryClaim',
    'admin.recruitment.myInterviews.primaryAssign',
    'admin.events.index',
    'admin.events.create',
    'admin.events.show',
    'admin.events.edit',
    'admin.events.registrants',
    'admin.events.laporan',
    'admin.events.exports.registrations',
    'admin.events.forms.create',
    'admin.events.forms.show',
    'admin.events.forms.store',
    'admin.events.forms.draft',
    'admin.events.forms.destroy',
    'admin.scan.index',
    'admin.scan.export',
    'admin.users.index',
    'admin.users.create',
    'admin.users.store',
    'admin.users.show',
    'admin.users.edit',
    'admin.users.update',
    'admin.users.destroy',
    'admin.broadcasts.index',
    'admin.broadcasts.create',
    'admin.broadcasts.store',
    'admin.broadcasts.show',
    'admin.broadcasts.destroy',
    'admin.broadcasts.content',
    'admin.broadcasts.snapshot',
    'admin.broadcasts.recipients',
    'admin.broadcasts.attachments',
    'admin.broadcasts.preview',
    'admin.broadcasts.test',
    'admin.broadcasts.schedule',
    'admin.broadcasts.cancel',
    'admin.broadcasts.retry',
    'admin.broadcasts.inlineImage',
    'member.joined',
    'member.browse',
    'member.checkEmail',
    'member.event.show',
    'member.event.register',
    'member.event.registration',
];

describe('routes builders', () => {
    it('builds a public event show path from a slug', () => {
        expect(routes.landing.events.show('open-house')).toBe('/events/open-house');
    });

    it('builds login-with-intended path', () => {
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

    it('encodes special characters in the export period filter', () => {
        const encoded = '..%2Fperiod+%3F%231';

        expect(routes.admin.recruitment.reports.exportFunnel('../period ?#1')).toBe(
            `/admin/recruitment/reports/export/funnel.csv?period_id=${encoded}`
        );
        expect(routes.admin.recruitment.reports.exportApplicants('../period ?#1')).toBe(
            `/admin/recruitment/reports/export/applicants.csv?period_id=${encoded}`
        );
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

    it('builds admin index path', () => {
        expect(routes.admin.index).toBe('/admin');
    });

    it('keeps the surviving key set deliberate', () => {
        expect(collectRouteKeys(routes)).toEqual(SURVIVING_ROUTE_KEYS);
    });

    // Pasangan action yang shape parameternya identik mudah tertukar dan tetap
    // lolos typecheck; snapshot ini menjaga pemetaan key -> route.
    it('keeps action pairs mapped to the correct endpoint', () => {
        const periodId = 'p-1';
        const applicationId = 'a-1';
        const eventId = 'e-1';

        const cases: Array<[string, string, string]> = [
            [
                'period open',
                routes.admin.recruitment.periods.open(periodId),
                `/admin/recruitment/periods/${periodId}/open`,
            ],
            [
                'period close',
                routes.admin.recruitment.periods.close(periodId),
                `/admin/recruitment/periods/${periodId}/close`,
            ],
            [
                'period update',
                routes.admin.recruitment.periods.update(periodId),
                `/admin/recruitment/periods/${periodId}`,
            ],
            [
                'period destroy',
                routes.admin.recruitment.periods.destroy(periodId),
                `/admin/recruitment/periods/${periodId}`,
            ],
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

    it('separates admin and dashboard active state', () => {
        expect(isSidebarNavActive(routes.admin.index, '/admin')).toBe(true);
        expect(isSidebarNavActive(routes.admin.index, '/dashboard')).toBe(false);
        expect(isSidebarNavActive(routes.dashboard.index, '/admin')).toBe(false);
        expect(isSidebarNavActive(routes.dashboard.index, '/dashboard')).toBe(true);
    });

    it('keeps recruitment sub-pages under the recruitment active item', () => {
        expect(isSidebarNavActive(routes.admin.recruitment.index, '/admin/recruitment')).toBe(true);
        expect(isSidebarNavActive(routes.admin.recruitment.index, '/admin/recruitment/periods/p-1')).toBe(true);
        expect(isSidebarNavActive(routes.admin.recruitment.index, '/admin/events')).toBe(false);
        expect(isSidebarNavActive(routes.admin.scan.index, '/admin/scan?desk=1')).toBe(true);
        expect(isSidebarNavActive(routes.member.joined, '/joined')).toBe(true);
    });

    it('strips query strings before comparing paths', () => {
        expect(pathWithoutQuery('/admin/events?page=2')).toBe('/admin/events');
        expect(pathWithoutQuery('/admin/events')).toBe('/admin/events');
    });

    it('resolves navbar back targets per domain', () => {
        expect(resolveNavbarFallbackBackHref('/admin/events/e-1')).toBe('/admin/events');
        expect(resolveNavbarFallbackBackHref('/admin/users/u-1')).toBe('/dashboard');
        expect(resolveNavbarFallbackBackHref('/profile')).toBe('/dashboard');
        expect(resolveNavbarFallbackBackHref('/browse')).toBe('/joined');
        expect(resolveNavbarFallbackBackHref('/joined/e-1')).toBe('/joined');
        expect(resolveNavbarFallbackBackHref('/')).toBe('/');
    });
});
