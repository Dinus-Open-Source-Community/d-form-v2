import { ref, type Ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { handleInertiaFormErrors } from '@/lib/error-message'
import { routes } from '@/lib/routes'
import {
    emailResendLabelOf,
    resolveEmailResendDisabled,
    type IEmailResendPrereq,
    type IEmailResendTypeStatus,
    type TRecruitmentEmailResendType,
} from '@/lib/recruitmentEmailResend'

/** State emailing applicant (konteks + izin + callback selesai). */
export interface IApplicantEmailingState {
    applicationId: string
    applicantName: string
    statusMap: Partial<Record<TRecruitmentEmailResendType, IEmailResendTypeStatus>> | null
    prereq: IEmailResendPrereq | null
    canResend: boolean
    onSent: () => void
}

/** Kirim-ulang email applicant: gerbang per jenis + konfirmasi + POST resend. */
export function useApplicantEmailing(state: IApplicantEmailingState): {
    pendingType: Ref<TRecruitmentEmailResendType | null>
    confirmOpen: Ref<boolean>
    isSending: Ref<boolean>
    statusOf: (type: TRecruitmentEmailResendType) => IEmailResendTypeStatus | null
    reasonOf: (type: TRecruitmentEmailResendType) => string | null
    requestSend: (type: TRecruitmentEmailResendType) => void
    cancelSend: () => void
    confirmSend: () => void
} {
    const pendingType = ref<TRecruitmentEmailResendType | null>(null)
    const confirmOpen = ref(false)
    const isSending = ref(false)

    function statusOf(type: TRecruitmentEmailResendType): IEmailResendTypeStatus | null {
        return state.statusMap?.[type] ?? null
    }

    function reasonOf(type: TRecruitmentEmailResendType): string | null {
        return resolveEmailResendDisabled({ type, status: statusOf(type), prereq: state.prereq, canResend: state.canResend })
    }

    function requestSend(type: TRecruitmentEmailResendType): void {
        if (reasonOf(type) !== null || isSending.value) return
        pendingType.value = type
        confirmOpen.value = true
    }

    function cancelSend(): void {
        confirmOpen.value = false
        pendingType.value = null
    }

    function confirmSend(): void {
        const type = pendingType.value
        if (!type || isSending.value) return
        isSending.value = true
        router.post(
            routes.admin.recruitment.applications.resendEmail(state.applicationId),
            { type },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Email ${emailResendLabelOf(type).toLowerCase()} telah dikirim ulang.`)
                    confirmOpen.value = false
                    pendingType.value = null
                    state.onSent()
                },
                onError: (errors) => {
                    handleInertiaFormErrors(errors, { title: 'Gagal mengirim ulang email' })
                },
                onFinish: () => {
                    isSending.value = false
                },
            },
        )
    }

    return { pendingType, confirmOpen, isSending, statusOf, reasonOf, requestSend, cancelSend, confirmSend }
}
