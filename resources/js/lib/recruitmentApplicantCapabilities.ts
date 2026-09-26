import type { ApplicationDetail } from '@/components/modules/dashboard/recruitment/ApplicantDetailContent.vue'

type RecruitmentAuthUser = {
    roles?: string[]
    can_manage_users?: boolean
    can_screen_recruitment_applications?: boolean
}

export function applicantAllowsTrackingResend(application: ApplicationDetail): boolean {
    if (application.can_resend_tracking === true) {
        return true
    }

    if (application.can_resend_tracking === false) {
        return false
    }

    return application.personal_email.trim().length > 0
}

export function userAllowsTrackingResend(user: RecruitmentAuthUser | null | undefined): boolean {
    if (!user) {
        return false
    }

    if (user.roles?.includes('super-admin') === true || user.can_manage_users === true) {
        return true
    }

    return user.can_screen_recruitment_applications === true
}
