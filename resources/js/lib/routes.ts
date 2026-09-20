/**
 * Pusat definisi URL frontend — selaras dengan `routes/web/**`.
 * Gunakan builder di sini; hindari string path hardcoded di komponen.
 */

const ADMIN_BASE = '/admin';
const MEMBER_JOINED = '/joined';

export const routes = {
    home: '/',

    landing: {
        features: '/features',
        docs: '/docs',
        events: {
            index: '/events',
            show: (slug: string) => `/events/${slug}`,
        },
    },

    // `landing` adalah form apply (nama route BE: `recruitment.apply`), bukan halaman landing terpisah.
    recruitment: {
        landing: '/recruitment',
        success: '/recruitment/success',
        track: {
            login: '/recruitment/track',
            dashboard: '/recruitment/track/dashboard',
            edit: '/recruitment/track/edit',
            update: '/recruitment/track',
            correction: '/recruitment/track/correction',
            feedback: '/recruitment/track/feedback',
            logout: '/recruitment/track/logout',
        },
        attendance: '/recruitment/attendance',
        queue: {
            index: '/recruitment/queue',
            show: (sessionId: string) => `/recruitment/queue/${sessionId}`,
        },
    },

    auth: {
        login: '/auth/login',
        register: '/auth/register',
        forgotPassword: '/auth/forgot-password',
        resetPassword: (token: string) => `/auth/reset-password/${token}`,
        passwordResetLink: '/auth/password-reset-link',
        google: '/auth/google',
        github: '/auth/github',
        logout: '/auth/logout',
        registerWithIntended: (intended: string) => `/auth/login?intended=${encodeURIComponent(intended)}`,
    },

    dashboard: {
        index: '/dashboard',
        profile: '/profile',
        profileAvatar: '/profile/avatar',
        profilePassword: '/profile/password',
    },

    admin: {
        index: ADMIN_BASE,
        recruitment: {
            index: `${ADMIN_BASE}/recruitment`,
            reports: {
                exportFunnel: (periodId?: string) =>
                    `${ADMIN_BASE}/recruitment/reports/export/funnel.csv${periodId ? `?period_id=${periodId}` : ''}`,
                exportApplicants: (periodId?: string) =>
                    `${ADMIN_BASE}/recruitment/reports/export/applicants.csv${periodId ? `?period_id=${periodId}` : ''}`,
            },
            activityLogs: {
                index: `${ADMIN_BASE}/recruitment/activity-logs`,
            },
            periods: {
                index: `${ADMIN_BASE}/recruitment`,
                create: `${ADMIN_BASE}/recruitment/periods/create`,
                store: `${ADMIN_BASE}/recruitment/periods`,
                show: (id: string) => `${ADMIN_BASE}/recruitment/periods/${id}`,
                edit: (id: string) => `${ADMIN_BASE}/recruitment/periods/${id}/edit`,
                update: (id: string) => `${ADMIN_BASE}/recruitment/periods/${id}`,
                open: (id: string) => `${ADMIN_BASE}/recruitment/periods/${id}/open`,
                close: (id: string) => `${ADMIN_BASE}/recruitment/periods/${id}/close`,
                destroy: (id: string) => `${ADMIN_BASE}/recruitment/periods/${id}`,
            },
            divisions: {
                update: (id: string) => `${ADMIN_BASE}/recruitment/divisions/${id}`,
            },
            interviewers: {
                store: `${ADMIN_BASE}/recruitment/interviewers`,
                assign: `${ADMIN_BASE}/recruitment/interviewers/assign`,
                unassign: (id: string) => `${ADMIN_BASE}/recruitment/interviewers/${id}`,
            },
            applications: {
                document: (id: string, type: 'cv' | 'portfolio', preview = false) =>
                    `${ADMIN_BASE}/recruitment/applications/${id}/documents/${type}${preview ? '?preview=1' : ''}`,
                screening: {
                    pass: (id: string) => `${ADMIN_BASE}/recruitment/applications/${id}/screening/pass`,
                    revision: (id: string) => `${ADMIN_BASE}/recruitment/applications/${id}/screening/revision`,
                    reject: (id: string) => `${ADMIN_BASE}/recruitment/applications/${id}/screening/reject`,
                },
                verify: (id: string) => `${ADMIN_BASE}/recruitment/applications/${id}/verify`,
                evaluationOverride: (id: string) => `${ADMIN_BASE}/recruitment/applications/${id}/evaluation`,
                final: {
                    accept: (id: string) => `${ADMIN_BASE}/recruitment/applications/${id}/final/accept`,
                    reject: (id: string) => `${ADMIN_BASE}/recruitment/applications/${id}/final/reject`,
                },
            },
            corrections: {
                approve: (id: string) => `${ADMIN_BASE}/recruitment/corrections/${id}/approve`,
                reject: (id: string) => `${ADMIN_BASE}/recruitment/corrections/${id}/reject`,
            },
            interviewSessions: {
                store: `${ADMIN_BASE}/recruitment/interview-sessions`,
                show: (id: string) => `${ADMIN_BASE}/recruitment/interview-sessions/${id}`,
                schedule: (id: string) => `${ADMIN_BASE}/recruitment/interview-sessions/${id}/schedule`,
            },
            interviews: {
                reschedule: (id: string) => `${ADMIN_BASE}/recruitment/interviews/${id}/reschedule`,
                reassign: (id: string) => `${ADMIN_BASE}/recruitment/interviews/${id}/reassign`,
            },
            myInterviews: {
                index: `${ADMIN_BASE}/recruitment/my-interviews`,
                show: (id: string) => `${ADMIN_BASE}/recruitment/my-interviews/${id}`,
                evaluate: (id: string) => `${ADMIN_BASE}/recruitment/my-interviews/${id}/evaluate`,
            },
            queue: {
                show: (sessionId: string) => `${ADMIN_BASE}/recruitment/queue/${sessionId}`,
                poll: (sessionId: string) => `${ADMIN_BASE}/recruitment/queue/${sessionId}/poll`,
                callNext: (sessionId: string) => `${ADMIN_BASE}/recruitment/queue/${sessionId}/call-next`,
                complete: (entryId: string) => `${ADMIN_BASE}/recruitment/queue/${entryId}/complete`,
                noShow: (sessionId: string) => `${ADMIN_BASE}/recruitment/queue/${sessionId}/no-show`,
            },
        },
        events: {
            index: `${ADMIN_BASE}/events`,
            create: `${ADMIN_BASE}/events/create`,
            show: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}`,
            edit: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}/edit`,
            registrants: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}/registrants`,
            laporan: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}/laporan`,
            exports: {
                registrations: (eventId: string | number) =>
                    `${ADMIN_BASE}/events/${eventId}/exports/registrations.csv`,
                attendance: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}/exports/attendance.csv`,
            },
            forms: {
                /** @deprecated primary UI now inline di Event Detail, keep untuk redirect/bookmark */
                index: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}/forms`,
                create: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}/forms/create`,
                show: (eventId: string | number, formId: string | number) =>
                    `${ADMIN_BASE}/events/${eventId}/forms/${formId}`,
                store: (eventId: string | number) => `${ADMIN_BASE}/events/${eventId}/forms`,
                destroy: (eventId: string | number, formId: string | number) =>
                    `${ADMIN_BASE}/events/${eventId}/forms/${formId}`,
                submissions: (eventId: string | number, formId: string | number) =>
                    `${ADMIN_BASE}/events/${eventId}/forms/${formId}/submissions`,
            },
        },
        scan: {
            index: `${ADMIN_BASE}/scan`,
            store: `${ADMIN_BASE}/scan`,
            feed: `${ADMIN_BASE}/scan/feed`,
            export: (params: { kind: 'event' | 'oprec'; target: string; format: 'csv' | 'xlsx' }) =>
                `${ADMIN_BASE}/scan/export?kind=${params.kind}&target=${encodeURIComponent(params.target)}&format=${params.format}`,
        },
    },

    member: {
        joined: MEMBER_JOINED,
        browse: '/browse',
        checkEmail: `${MEMBER_JOINED}/users/check-email`,
        event: {
            show: (segment: string | number) => `${MEMBER_JOINED}/${segment}`,
            register: (segment: string | number) => `${MEMBER_JOINED}/${segment}/register`,
            registration: (segment: string | number) => `${MEMBER_JOINED}/${segment}/registration`,
            formFill: (eventId: string | number, formId: string | number) =>
                `${MEMBER_JOINED}/${eventId}/forms/${formId}/fill`,
            formSubmitted: (eventId: string | number, formId: string | number) =>
                `${MEMBER_JOINED}/${eventId}/forms/${formId}/submitted`,
        },
        teamInvitation: (token: string) => `${MEMBER_JOINED}/team-invitations/${token}`,
    },

    secret: {
        party: '/party',
        balloons: '/balloons',
    },
} as const;

export function pathWithoutQuery(url: string): string {
    return url.split('?')[0] ?? '';
}

export function isSidebarNavActive(href: string, currentUrl: string): boolean {
    const path = pathWithoutQuery(currentUrl);

    if (href === routes.admin.index) {
        return path === routes.admin.index;
    }
    if (href === routes.dashboard.index) {
        return path === routes.dashboard.index;
    }
    if (href === routes.member.browse) {
        return path === routes.member.browse;
    }
    if (href === routes.member.joined) {
        return path === routes.member.joined;
    }
    if (href === routes.admin.recruitment.index) {
        // Daftar periode kini menyatu di Pusat kerja; halaman detail/create/edit
        // periode (di bawah /admin/recruitment/periods) tetap menandai
        // Pusat kerja sebagai aktif.
        if (path === routes.admin.recruitment.index) return true;
        if (path.startsWith(`${ADMIN_BASE}/recruitment/periods`)) return true;
        return false;
    }
    if (href.startsWith(routes.admin.recruitment.myInterviews.index)) {
        return path.startsWith(routes.admin.recruitment.myInterviews.index);
    }
    if (href.startsWith(routes.admin.recruitment.activityLogs.index)) {
        return path.startsWith(routes.admin.recruitment.activityLogs.index);
    }
    if (href.startsWith(routes.admin.recruitment.myInterviews.index)) {
        return path.startsWith(routes.admin.recruitment.myInterviews.index);
    }
    if (href === routes.admin.scan.index) {
        return path === routes.admin.scan.index;
    }
    if (href.includes('/recruitment/queue/')) {
        return path.includes('/recruitment/queue/');
    }

    return path.startsWith(href);
}

export function resolveNavbarFallbackBackHref(currentUrl: string): string {
    const path = pathWithoutQuery(currentUrl);

    if (path.startsWith(routes.admin.events.index) && path !== routes.admin.events.index) {
        return routes.admin.events.index;
    }
    if (path.startsWith(routes.admin.index) && path !== routes.admin.index) {
        return routes.dashboard.index;
    }
    if (path === routes.dashboard.profile) {
        return routes.dashboard.index;
    }
    if (path === routes.member.browse) {
        return routes.member.joined;
    }
    if (path.startsWith(routes.member.joined) && path !== routes.member.joined) {
        return routes.member.joined;
    }

    return routes.home;
}
