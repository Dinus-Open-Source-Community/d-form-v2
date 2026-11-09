/**
 * Adapter tipis di atas route Wayfinder (`@/routes/**`).
 * Satu-satunya sumber path adalah generated Wayfinder; API `routes.*` dipertahankan
 * agar call site lama tidak berubah.
 */

import { dashboard as dashboardRoute, docs, features, home } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { github, google, login as authLogin, logout as authLogout, register as authRegister } from '@/routes/auth';
import { request as forgotPasswordRequest, resetLink as passwordResetLink } from '@/routes/auth/password';
import { profile as dashboardProfile } from '@/routes/dashboard';
import {
    create as eventCreate,
    edit as eventEdit,
    index as eventsIndexAdmin,
    laporan as eventLaporan,
    registrants as eventRegistrants,
    show as eventShowAdmin,
} from '@/routes/dashboard/events';
import { attendanceCsv, registrationsCsv } from '@/routes/dashboard/events/exports';
import {
    create as formCreate,
    destroy as formDestroy,
    fill as formFill,
    index as formIndex,
    show as formShow,
    store as formStore,
    submissions as formSubmissions,
    submitted as formSubmitted,
} from '@/routes/dashboard/events/forms';
import { index as recruitmentIndex } from '@/routes/dashboard/recruitment';
import { index as activityLogsIndex } from '@/routes/dashboard/recruitment/activity-logs';
import {
    resendEmail as applicationResendEmail,
    resendTracking as applicationResendTracking,
    verify as applicationVerify,
} from '@/routes/dashboard/recruitment/applications';
import { download as applicationDocument } from '@/routes/dashboard/recruitment/applications/documents';
import { override as applicationEvaluationOverride } from '@/routes/dashboard/recruitment/applications/evaluation';
import { accept as finalAccept, reject as finalReject } from '@/routes/dashboard/recruitment/applications/final';
import {
    pass as screeningPass,
    reject as screeningReject,
    revision as screeningRevision,
} from '@/routes/dashboard/recruitment/applications/screening';
import { approve as correctionApprove, reject as correctionReject } from '@/routes/dashboard/recruitment/corrections';
import { update as divisionUpdate } from '@/routes/dashboard/recruitment/divisions';
import { assign as interviewAssign } from '@/routes/dashboard/recruitment/interviews';
import {
    destroy as sessionDestroy,
    show as sessionShow,
    store as sessionStore,
    update as sessionUpdate,
} from '@/routes/dashboard/recruitment/interview-sessions';
import {
    assign as interviewerAssign,
    store as interviewerStore,
    unassign as interviewerUnassign,
} from '@/routes/dashboard/recruitment/interviewers';
import {
    evaluate as myInterviewEvaluate,
    index as myInterviewsIndex,
    primaryClaim,
    secondaryClaim,
    show as myInterviewShow,
} from '@/routes/dashboard/recruitment/my-interviews';
import {
    close as periodClose,
    create as periodCreate,
    destroy as periodDestroy,
    open as periodOpen,
    sendGroupLink as periodSendGroupLink,
    sendQr as periodSendQr,
    show as periodShow,
    store as periodStore,
    update as periodUpdate,
} from '@/routes/dashboard/recruitment/periods';
import { exportMethod as periodExportInterviews } from '@/routes/dashboard/recruitment/periods/interviews';
import { applicants as applicantsExport, funnel as funnelExport } from '@/routes/dashboard/recruitment/reports/export';
import {
    exportMethod as scanExport,
    feed as scanFeed,
    index as scanIndex,
    store as scanStore,
} from '@/routes/dashboard/scan';
import { events as memberJoined } from '@/routes/dashboard/user';
import {
    browse as memberBrowse,
    register as memberRegister,
    registration as memberRegistration,
    show as memberEventShow,
} from '@/routes/dashboard/user/events';
import { show as teamInvitationShow } from '@/routes/dashboard/user/team-invitations';
import {
    checkEmail,
    create as userCreate,
    destroy as userDestroy,
    edit as userEdit,
    index as usersIndex,
    show as userShow,
    store as userStore,
    update as userUpdate,
} from '@/routes/dashboard/users';
import { update as profileAvatarUpdate } from '@/routes/dashboard/profile/avatar';
import { update as profilePasswordUpdate } from '@/routes/dashboard/profile/password';
import { index as eventsIndex, show as eventShow } from '@/routes/events';
import { reset as resetPasswordRoute } from '@/routes/password';
import {
    apply as recruitmentApply,
    attendance as recruitmentAttendance,
    success as recruitmentSuccess,
} from '@/routes/recruitment';
import {
    correction as trackCorrection,
    edit as trackEdit,
    feedback as trackFeedback,
    login as trackLogin,
    logout as trackLogout,
    show as trackDashboard,
    update as trackUpdate,
} from '@/routes/recruitment/track';
import { balloons, party } from '@/routes/secret';

/**
 * Broadcast dorman: `FEATURE_BROADCAST=false` membuat route `/admin/broadcasts/*`
 * tidak terdaftar sehingga Wayfinder tidak men-generate-nya. Ini satu-satunya
 * literal path yang diizinkan; hapus blok ini saat flag diaktifkan permanen.
 */
const BROADCAST_BASE = '/admin/broadcasts';

export const routes = {
    home: home.url(),

    landing: {
        features: features.url(),
        docs: docs.url(),
        events: {
            index: eventsIndex.url(),
            show: (slug: string): string => eventShow.url({ event: slug }),
        },
    },

    // `landing` adalah form apply (nama route BE: `recruitment.apply`), bukan halaman landing terpisah.
    recruitment: {
        landing: recruitmentApply.url(),
        success: recruitmentSuccess.url(),
        track: {
            login: trackLogin.url(),
            dashboard: trackDashboard.url(),
            edit: trackEdit.url(),
            update: trackUpdate.url(),
            correction: trackCorrection.url(),
            feedback: trackFeedback.url(),
            logout: trackLogout.url(),
        },
        attendance: recruitmentAttendance.url(),
    },

    auth: {
        login: authLogin.url(),
        register: authRegister.url(),
        forgotPassword: forgotPasswordRequest.url(),
        resetPassword: (token: string): string => resetPasswordRoute.url({ token }),
        passwordResetLink: passwordResetLink.url(),
        google: google.url(),
        github: github.url(),
        logout: authLogout.url(),
        registerWithIntended: (intended: string): string => authLogin.url({ query: { intended } }),
    },

    dashboard: {
        index: dashboardRoute.url(),
        profile: dashboardProfile.url(),
        profileAvatar: profileAvatarUpdate.url(),
        profilePassword: profilePasswordUpdate.url(),
    },

    admin: {
        index: adminIndex.url(),
        recruitment: {
            index: recruitmentIndex.url(),
            reports: {
                exportFunnel: (periodId?: string): string =>
                    funnelExport.url(periodId ? { query: { period_id: periodId } } : undefined),
                exportApplicants: (periodId?: string): string =>
                    applicantsExport.url(periodId ? { query: { period_id: periodId } } : undefined),
            },
            activityLogs: {
                index: activityLogsIndex.url(),
            },
            periods: {
                index: recruitmentIndex.url(),
                create: periodCreate.url(),
                store: periodStore.url(),
                show: (id: string): string => periodShow.url({ period: id }),
                update: (id: string): string => periodUpdate.url({ period: id }),
                open: (id: string): string => periodOpen.url({ period: id }),
                close: (id: string): string => periodClose.url({ period: id }),
                destroy: (id: string): string => periodDestroy.url({ period: id }),
                sendGroupLink: (id: string): string => periodSendGroupLink.url({ period: id }),
                sendQr: (id: string): string => periodSendQr.url({ period: id }),
                exportInterviews: (id: string): string => periodExportInterviews.url({ period: id }),
            },
            divisions: {
                update: (id: string): string => divisionUpdate.url({ division: id }),
            },
            interviewers: {
                store: interviewerStore.url(),
                assign: interviewerAssign.url(),
                unassign: (id: string): string => interviewerUnassign.url({ assignment: id }),
            },
            applications: {
                document: (id: string, type: 'cv' | 'portfolio' | 'instagram_follow', preview: boolean = false): string =>
                    applicationDocument.url({ application: id, type }, preview ? { query: { preview: 1 } } : undefined),
                screening: {
                    pass: (id: string): string => screeningPass.url({ application: id }),
                    revision: (id: string): string => screeningRevision.url({ application: id }),
                    reject: (id: string): string => screeningReject.url({ application: id }),
                },
                verify: (id: string): string => applicationVerify.url({ application: id }),
                resendEmail: (id: string): string => applicationResendEmail.url({ application: id }),
                resendTracking: (id: string): string => applicationResendTracking.url({ application: id }),
                evaluationOverride: (id: string): string => applicationEvaluationOverride.url({ application: id }),
                final: {
                    accept: (id: string): string => finalAccept.url({ application: id }),
                    reject: (id: string): string => finalReject.url({ application: id }),
                },
            },
            corrections: {
                approve: (id: string): string => correctionApprove.url({ correction: id }),
                reject: (id: string): string => correctionReject.url({ correction: id }),
            },
            interviewSessions: {
                store: sessionStore.url(),
                show: (id: string): string => sessionShow.url({ session: id }),
                update: (id: string): string => sessionUpdate.url({ session: id }),
                destroy: (id: string): string => sessionDestroy.url({ session: id }),
            },
            myInterviews: {
                index: myInterviewsIndex.url(),
                show: (id: string): string => myInterviewShow.url({ interview: id }),
                evaluate: (id: string): string => myInterviewEvaluate.url({ interview: id }),
                secondaryClaim: secondaryClaim.url(),
                primaryClaim: primaryClaim.url(),
                primaryAssign: (id: string): string => interviewAssign.url({ interview: id }),
            },
        },
        events: {
            index: eventsIndexAdmin.url(),
            create: eventCreate.url(),
            show: (eventId: string | number): string => eventShowAdmin.url({ event: String(eventId) }),
            edit: (eventId: string | number): string => eventEdit.url({ event: String(eventId) }),
            registrants: (eventId: string | number): string => eventRegistrants.url({ event: String(eventId) }),
            laporan: (eventId: string | number): string => eventLaporan.url({ event: String(eventId) }),
            exports: {
                registrations: (eventId: string | number): string => registrationsCsv.url({ event: String(eventId) }),
                attendance: (eventId: string | number): string => attendanceCsv.url({ event: String(eventId) }),
            },
            forms: {
                /** @deprecated primary UI now inline di Event Detail, keep untuk redirect/bookmark */
                index: (eventId: string | number): string => formIndex.url({ event: String(eventId) }),
                create: (eventId: string | number): string => formCreate.url({ event: String(eventId) }),
                show: (eventId: string | number, formId: string | number): string =>
                    formShow.url({ event: String(eventId), form: String(formId) }),
                store: (eventId: string | number): string => formStore.url({ event: String(eventId) }),
                destroy: (eventId: string | number, formId: string | number): string =>
                    formDestroy.url({ event: String(eventId), form: String(formId) }),
                submissions: (eventId: string | number, formId: string | number): string =>
                    formSubmissions.url({ event: String(eventId), form: String(formId) }),
            },
        },
        scan: {
            index: scanIndex.url(),
            store: scanStore.url(),
            feed: scanFeed.url(),
            export: (params: { kind: 'event' | 'oprec'; target: string; format: 'csv' | 'xlsx' }): string =>
                scanExport.url({ query: { kind: params.kind, target: params.target, format: params.format } }),
        },
        users: {
            index: usersIndex.url(),
            create: userCreate.url(),
            store: userStore.url(),
            show: (id: string): string => userShow.url({ user: id }),
            edit: (id: string): string => userEdit.url({ user: id }),
            update: (id: string): string => userUpdate.url({ user: id }),
            destroy: (id: string): string => userDestroy.url({ user: id }),
        },
        broadcasts: {
            index: BROADCAST_BASE,
            create: `${BROADCAST_BASE}/create`,
            store: BROADCAST_BASE,
            show: (id: string): string => `${BROADCAST_BASE}/${id}`,
            destroy: (id: string): string => `${BROADCAST_BASE}/${id}`,
            content: (id: string): string => `${BROADCAST_BASE}/${id}/content`,
            snapshot: (id: string): string => `${BROADCAST_BASE}/${id}/snapshot`,
            recipients: (id: string): string => `${BROADCAST_BASE}/${id}/recipients`,
            attachments: (id: string): string => `${BROADCAST_BASE}/${id}/attachments`,
            preview: (id: string): string => `${BROADCAST_BASE}/${id}/preview`,
            test: (id: string): string => `${BROADCAST_BASE}/${id}/test`,
            schedule: (id: string): string => `${BROADCAST_BASE}/${id}/schedule`,
            cancel: (id: string): string => `${BROADCAST_BASE}/${id}/cancel`,
            retry: (id: string): string => `${BROADCAST_BASE}/${id}/retry-failed`,
            datasetsPreview: `${BROADCAST_BASE}/datasets/preview`,
        },
    },

    member: {
        joined: memberJoined.url(),
        browse: memberBrowse.url(),
        checkEmail: checkEmail.url(),
        event: {
            show: (segment: string | number): string => memberEventShow.url({ event_segment: segment }),
            register: (segment: string | number): string => memberRegister.url({ event_segment: segment }),
            registration: (segment: string | number): string => memberRegistration.url({ event_segment: segment }),
            formFill: (eventId: string | number, formId: string | number): string =>
                formFill.url({ event: String(eventId), form: String(formId) }),
            formSubmitted: (eventId: string | number, formId: string | number): string =>
                formSubmitted.url({ event: String(eventId), form: String(formId) }),
        },
        teamInvitation: (token: string): string => teamInvitationShow.url({ token }),
    },

    secret: {
        party: party.url(),
        balloons: balloons.url(),
    },
};

/** Buang query string dari URL agar path bisa dibandingkan. */
export function pathWithoutQuery(url: string): string {
    return url.split('?')[0] ?? '';
}

/** Apakah `href` sidebar menunjuk halaman aktif untuk `currentUrl` (query diabaikan). */
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
        // Daftar periode kini menyatu di Pusat kerja; halaman detail/create
        // periode tetap menandai Pusat kerja sebagai aktif.
        if (path === routes.admin.recruitment.index) return true;
        if (path.startsWith(`${routes.admin.recruitment.index}/periods`)) return true;
        return false;
    }
    if (href.startsWith(routes.admin.recruitment.myInterviews.index)) {
        return path.startsWith(routes.admin.recruitment.myInterviews.index);
    }
    if (href.startsWith(routes.admin.recruitment.activityLogs.index)) {
        return path.startsWith(routes.admin.recruitment.activityLogs.index);
    }
    if (href === routes.admin.scan.index) {
        return path === routes.admin.scan.index;
    }

    return path.startsWith(href);
}

/** Tujuan tombol back navbar saat tidak ada history; fallback per domain halaman. */
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
