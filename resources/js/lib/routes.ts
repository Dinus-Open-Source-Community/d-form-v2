/**
 * Pusat definisi URL frontend — selaras dengan `routes/web/**`.
 * Gunakan builder di sini; hindari string path hardcoded di komponen.
 */

const ADMIN_DASHBOARD = '/admin/dashboard'
const MEMBER_JOINED = '/events/joined'

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

    auth: {
        login: '/auth/login',
        register: '/auth/register',
        forgotPassword: '/auth/forgot-password',
        resetPassword: (token: string) => `/auth/reset-password/${token}`,
        passwordResetLink: '/auth/password-reset-link',
        google: '/auth/google',
        github: '/auth/github',
        logout: '/auth/logout',
        registerWithIntended: (intended: string) =>
            `/auth/register?intended=${encodeURIComponent(intended)}`,
    },

    dashboard: {
        index: '/dashboard',
        profile: '/dashboard/profile',
        profileAvatar: '/dashboard/profile/avatar',
        profilePassword: '/dashboard/profile/password',
    },

    admin: {
        index: ADMIN_DASHBOARD,
        recruitment: `${ADMIN_DASHBOARD}/recruitment`,
        events: {
            index: `${ADMIN_DASHBOARD}/events`,
            create: `${ADMIN_DASHBOARD}/events/create`,
            show: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}`,
            edit: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}/edit`,
            scan: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}/scan`,
            registrants: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}/registrants`,
            laporan: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}/laporan`,
            exports: {
                registrations: (eventId: string | number) =>
                    `${ADMIN_DASHBOARD}/events/${eventId}/exports/registrations.csv`,
                attendance: (eventId: string | number) =>
                    `${ADMIN_DASHBOARD}/events/${eventId}/exports/attendance.csv`,
            },
            forms: {
                index: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}/forms`,
                create: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}/forms/create`,
                show: (eventId: string | number, formId: string | number) =>
                    `${ADMIN_DASHBOARD}/events/${eventId}/forms/${formId}`,
                store: (eventId: string | number) => `${ADMIN_DASHBOARD}/events/${eventId}/forms`,
                destroy: (eventId: string | number, formId: string | number) =>
                    `${ADMIN_DASHBOARD}/events/${eventId}/forms/${formId}`,
                submissions: (eventId: string | number, formId: string | number) =>
                    `${ADMIN_DASHBOARD}/events/${eventId}/forms/${formId}/submissions`,
            },
        },
    },

    member: {
        joined: MEMBER_JOINED,
        browse: '/browse/events',
        checkEmail: `${MEMBER_JOINED}/users/check-email`,
        event: {
            show: (segment: string | number) => `${MEMBER_JOINED}/events/${segment}`,
            register: (segment: string | number) => `${MEMBER_JOINED}/events/${segment}/register`,
            registration: (segment: string | number) => `${MEMBER_JOINED}/events/${segment}/registration`,
            formFill: (eventId: string | number, formId: string | number) =>
                `${MEMBER_JOINED}/events/${eventId}/forms/${formId}/fill`,
        },
        teamInvitation: (token: string) => `${MEMBER_JOINED}/team-invitations/${token}`,
    },

    secret: {
        party: '/party',
        balloons: '/balloons',
    },
} as const

export function pathWithoutQuery(url: string): string {
    return url.split('?')[0] ?? ''
}

export function isSidebarNavActive(href: string, currentUrl: string): boolean {
    const path = pathWithoutQuery(currentUrl)

    if (href === routes.admin.index) {
        return path === routes.admin.index
    }
    if (href === routes.dashboard.index) {
        return path === routes.dashboard.index
    }
    if (href === routes.member.browse) {
        return path === routes.member.browse
    }
    if (href === routes.member.joined) {
        return path === routes.member.joined
    }

    return path.startsWith(href)
}

export function resolveNavbarFallbackBackHref(currentUrl: string): string {
    const path = pathWithoutQuery(currentUrl)

    if (path.startsWith(routes.admin.events.index) && path !== routes.admin.events.index) {
        return routes.admin.events.index
    }
    if (path.startsWith(routes.admin.index) && path !== routes.admin.index) {
        return routes.dashboard.index
    }
    if (path === routes.dashboard.profile) {
        return routes.dashboard.index
    }
    if (path === routes.member.browse) {
        return routes.member.joined
    }
    if (path.startsWith(`${routes.member.joined}/events`)) {
        return routes.member.joined
    }
    if (path.startsWith(routes.member.joined) && path !== routes.member.joined) {
        return routes.member.joined
    }

    return routes.home
}
