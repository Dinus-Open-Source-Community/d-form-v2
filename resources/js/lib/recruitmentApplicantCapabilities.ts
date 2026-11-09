import type { IApplicationDetail } from '@/types/recruitment';

type RecruitmentAuthUser = {
    roles?: string[];
    can_manage_users?: boolean;
    can_screen_recruitment_applications?: boolean;
};

/**
 * Boleh tidaknya kirim ulang link tracking: flag eksplisit backend diutamakan,
 * bila tidak ada jatuh ke syarat email pribadi terisi.
 */
export function applicantAllowsTrackingResend(application: IApplicationDetail): boolean {
    if (application.can_resend_tracking === true) {
        return true;
    }

    if (application.can_resend_tracking === false) {
        return false;
    }

    return application.personal_email.trim().length > 0;
}

/** Hak user mengirim ulang link tracking: super-admin/admin user atau pemegang izin screening. */
export function userAllowsTrackingResend(user: RecruitmentAuthUser | null | undefined): boolean {
    if (!user) {
        return false;
    }

    if (user.roles?.includes('super-admin') === true || user.can_manage_users === true) {
        return true;
    }

    return user.can_screen_recruitment_applications === true;
}
